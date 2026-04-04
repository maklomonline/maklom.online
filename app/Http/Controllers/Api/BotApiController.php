<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChatRoom;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\GameService;
use App\Services\GoEngine\BoardService;
use App\Services\GoEngine\GoRuleException;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class BotApiController extends Controller
{
    public function __construct(
        private GameService $gameService,
        private BoardService $boardService,
        private NotificationService $notificationService,
    ) {}

    // ─── Auth ────────────────────────────────────────────────────────────────────

    /**
     * POST /api/bot/auth
     * Bot client เข้าสู่ระบบด้วย username + password → ได้รับ API token
     */
    public function auth(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $bot = User::where('username', $request->username)
            ->where('is_bot', true)
            ->whereNotNull('bot_api_token')
            ->first();

        if (! $bot || ! Hash::check($request->password, $bot->password)) {
            return response()->json(['error' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'], 401);
        }

        // Mark as online immediately
        $bot->update([
            'bot_online'         => true,
            'bot_last_heartbeat' => now(),
        ]);

        return response()->json([
            'token'        => $bot->bot_api_token,
            'username'     => $bot->username,
            'display_name' => $bot->display_name,
            'rank'         => $bot->rank,
        ]);
    }

    // ─── Heartbeat ───────────────────────────────────────────────────────────────

    /**
     * POST /api/bot/heartbeat
     * ส่งสัญญาณว่า bot client ยังออนไลน์อยู่ (ทุก 30 วินาที)
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $bot = $this->resolveBotFromToken($request);
        if (! $bot) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $bot->update([
            'bot_online'         => true,
            'bot_last_heartbeat' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/bot/offline
     * Bot client แจ้งว่ากำลังจะออฟไลน์
     */
    public function goOffline(Request $request): JsonResponse
    {
        $bot = $this->resolveBotFromToken($request);
        if (! $bot) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $bot->update(['bot_online' => false]);

        return response()->json(['ok' => true]);
    }

    // ─── Challenges ──────────────────────────────────────────────────────────────

    /**
     * GET /api/bot/challenges
     * ดึงรายการคำท้าดวลที่รอการตอบรับของบอท
     */
    public function pendingChallenges(Request $request): JsonResponse
    {
        $bot = $this->resolveBotFromToken($request);
        if (! $bot) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $challenges = Challenge::where('challenged_id', $bot->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with('challenger:id,username,display_name,rank')
            ->get()
            ->map(fn ($c) => [
                'id'               => $c->id,
                'challenger'       => $c->challenger->only('id', 'username', 'display_name', 'rank'),
                'board_size'       => $c->board_size,
                'clock_type'       => $c->clock_type,
                'main_time'        => $c->main_time,
                'byoyomi_periods'  => $c->byoyomi_periods,
                'byoyomi_seconds'  => $c->byoyomi_seconds,
                'fischer_increment' => $c->fischer_increment,
                'handicap'         => $c->handicap,
                'expires_at'       => $c->expires_at->toISOString(),
            ]);

        return response()->json(['challenges' => $challenges]);
    }

    /**
     * POST /api/bot/challenges/{challenge}/accept
     * Bot client รับคำท้าดวลและสร้างเกม
     */
    public function acceptChallenge(Challenge $challenge, Request $request): JsonResponse
    {
        $bot = $this->resolveBotFromToken($request);
        if (! $bot) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($challenge->challenged_id !== $bot->id) {
            return response()->json(['error' => 'ไม่ใช่คำท้าดวลของคุณ'], 403);
        }

        if ($challenge->status !== 'pending' || $challenge->isExpired()) {
            return response()->json(['error' => 'คำท้าดวลหมดอายุหรือถูกยกเลิกแล้ว'], 422);
        }

        $challenge->update(['status' => 'accepted']);

        $challenger = $challenge->challenger;
        $boardSize  = $challenge->board_size;
        $handicap   = $challenge->handicap;
        $komi       = ($handicap >= 2) ? 0.5 : 6.5;

        $room = GameRoom::create([
            'name'              => "{$challenger->getDisplayName()} vs {$bot->getDisplayName()}",
            'creator_id'        => $challenger->id,
            'board_size'        => $boardSize,
            'komi'              => $komi,
            'handicap'          => $handicap,
            'clock_type'        => $challenge->clock_type,
            'main_time'         => $challenge->main_time,
            'byoyomi_periods'   => $challenge->byoyomi_periods,
            'byoyomi_seconds'   => $challenge->byoyomi_seconds,
            'fischer_increment' => $challenge->fischer_increment,
            'is_private'        => true,
            'status'            => 'playing',
        ]);

        $boardState = $this->boardService->createEmptyBoard($boardSize);
        if ($handicap >= 2) {
            $boardState = $this->boardService->applyHandicap($boardState, $boardSize, $handicap);
        }

        $game = Game::create([
            'room_id'            => $room->id,
            'black_player_id'    => $challenger->id,
            'white_player_id'    => $bot->id,
            'board_size'         => $boardSize,
            'komi'               => $komi,
            'handicap'           => $handicap,
            'clock_type'         => $challenge->clock_type,
            'main_time'          => $challenge->main_time,
            'byoyomi_periods'    => $challenge->byoyomi_periods,
            'byoyomi_seconds'    => $challenge->byoyomi_seconds,
            'fischer_increment'  => $challenge->fischer_increment,
            'black_time_left'    => $challenge->main_time,
            'white_time_left'    => $challenge->main_time,
            'black_periods_left' => $challenge->byoyomi_periods,
            'white_periods_left' => $challenge->byoyomi_periods,
            'current_color'      => ($handicap >= 2) ? 'white' : 'black',
            'board_state'        => $boardState,
            'status'             => 'active',
            'started_at'         => now(),
        ]);

        ChatRoom::firstOrCreate([
            'type'         => 'game',
            'reference_id' => $game->id,
        ]);

        $this->notificationService->send(
            $challenger,
            'challenge_accepted',
            "{$bot->getDisplayName()} (BOT) รับคำท้าดวลแล้ว!",
            "เกมเริ่มแล้ว — กระดาน {$boardSize}×{$boardSize}",
            ['game_url' => route('games.show', $game)]
        );

        // ถ้าบอทเป็นฝ่ายดำ (เริ่มก่อน) ให้ตอบกลับข้อมูลเกมเพื่อให้ bot client เล่นทันที
        $botColor  = ($game->black_player_id === $bot->id) ? 'black' : 'white';
        $myTurn    = $game->current_color === $botColor && $game->status === 'active';

        return response()->json([
            'game_id'   => $game->id,
            'game_url'  => route('games.show', $game),
            'bot_color' => $botColor,
            'my_turn'   => $myTurn,
        ]);
    }

    // ─── Games ───────────────────────────────────────────────────────────────────

    /**
     * GET /api/bot/games
     * ดึงเกมที่ active และถึงตาของบอท
     */
    public function activeGames(Request $request): JsonResponse
    {
        $bot = $this->resolveBotFromToken($request);
        if (! $bot) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $games = Game::where('status', 'active')
            ->where(function ($q) use ($bot) {
                $q->where(function ($q2) use ($bot) {
                    $q2->where('black_player_id', $bot->id)->where('current_color', 'black');
                })->orWhere(function ($q2) use ($bot) {
                    $q2->where('white_player_id', $bot->id)->where('current_color', 'white');
                });
            })
            ->get()
            ->map(fn ($g) => $this->gameToArray($g, $bot));

        // Also include scoring games where bot needs to confirm
        $scoringGames = Game::where('status', 'scoring')
            ->where(function ($q) use ($bot) {
                $q->where('black_player_id', $bot->id)
                  ->orWhere('white_player_id', $bot->id);
            })
            ->get()
            ->map(fn ($g) => $this->gameToArray($g, $bot));

        return response()->json([
            'games'         => $games,
            'scoring_games' => $scoringGames,
        ]);
    }

    /**
     * GET /api/bot/games/{game}
     * ดึงสถานะเกมเต็ม
     */
    public function gameState(Game $game, Request $request): JsonResponse
    {
        $bot = $this->resolveBotFromToken($request);
        if (! $bot) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($game->black_player_id !== $bot->id && $game->white_player_id !== $bot->id) {
            return response()->json(['error' => 'ไม่ใช่เกมของคุณ'], 403);
        }

        return response()->json($this->gameToArray($game, $bot, full: true));
    }

    /**
     * POST /api/bot/games/{game}/move
     * Bot ส่งหมาก
     */
    public function makeMove(Game $game, Request $request): JsonResponse
    {
        $bot = $this->resolveBotFromToken($request);
        if (! $bot) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'coordinate' => ['required', 'string'],
        ]);

        if ($game->black_player_id !== $bot->id && $game->white_player_id !== $bot->id) {
            return response()->json(['error' => 'ไม่ใช่เกมของคุณ'], 403);
        }

        try {
            $game = $this->gameService->makeMove($game, $bot, $request->coordinate);
        } catch (GoRuleException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\App\Services\ClockTimeoutException $e) {
            $this->gameService->handleTimeout($game, $game->current_color);
            return response()->json(['error' => $e->getMessage(), 'timeout' => true], 422);
        }

        return response()->json(['success' => true, 'game' => $this->gameToArray($game, $bot)]);
    }

    /**
     * POST /api/bot/games/{game}/pass
     * Bot ผ่านตา
     */
    public function pass(Game $game, Request $request): JsonResponse
    {
        $bot = $this->resolveBotFromToken($request);
        if (! $bot) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($game->black_player_id !== $bot->id && $game->white_player_id !== $bot->id) {
            return response()->json(['error' => 'ไม่ใช่เกมของคุณ'], 403);
        }

        try {
            $game = $this->gameService->passMove($game, $bot);
        } catch (GoRuleException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\App\Services\ClockTimeoutException $e) {
            $this->gameService->handleTimeout($game, $game->current_color);
            return response()->json(['error' => $e->getMessage(), 'timeout' => true], 422);
        }

        return response()->json(['success' => true, 'game' => $this->gameToArray($game, $bot)]);
    }

    /**
     * POST /api/bot/games/{game}/resign
     * Bot ยอมแพ้
     */
    public function resign(Game $game, Request $request): JsonResponse
    {
        $bot = $this->resolveBotFromToken($request);
        if (! $bot) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($game->black_player_id !== $bot->id && $game->white_player_id !== $bot->id) {
            return response()->json(['error' => 'ไม่ใช่เกมของคุณ'], 403);
        }

        try {
            $game = $this->gameService->resignGame($game, $bot);
        } catch (GoRuleException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'result' => $game->result]);
    }

    /**
     * POST /api/bot/games/{game}/scoring/dead-stones
     * Bot ส่งรายการหมากที่ตายแล้ว
     */
    public function submitDeadStones(Game $game, Request $request): JsonResponse
    {
        $bot = $this->resolveBotFromToken($request);
        if (! $bot) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'dead_stones'   => ['array'],
            'dead_stones.*' => ['array', 'size:2'],
        ]);

        if ($game->black_player_id !== $bot->id && $game->white_player_id !== $bot->id) {
            return response()->json(['error' => 'ไม่ใช่เกมของคุณ'], 403);
        }

        if ($game->status !== 'scoring') {
            return response()->json(['error' => 'ไม่ได้อยู่ในขั้นตอนนับคะแนน'], 422);
        }

        $this->gameService->submitDeadStones($game, $bot, $request->dead_stones ?? []);

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/bot/games/{game}/confirm-score
     * Bot ยืนยันคะแนน
     */
    public function confirmScore(Game $game, Request $request): JsonResponse
    {
        $bot = $this->resolveBotFromToken($request);
        if (! $bot) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($game->black_player_id !== $bot->id && $game->white_player_id !== $bot->id) {
            return response()->json(['error' => 'ไม่ใช่เกมของคุณ'], 403);
        }

        if ($game->status !== 'scoring') {
            return response()->json(['error' => 'ไม่ได้อยู่ในขั้นตอนนับคะแนน'], 422);
        }

        try {
            $game = $this->gameService->confirmScore($game);
        } catch (GoRuleException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'result' => $game->result]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    private function resolveBotFromToken(Request $request): ?User
    {
        $token = $request->bearerToken();
        if (! $token) {
            return null;
        }

        return User::where('bot_api_token', $token)
            ->where('is_bot', true)
            ->first();
    }

    private function gameToArray(Game $game, User $bot, bool $full = false): array
    {
        $botColor = ($game->black_player_id === $bot->id) ? 'black' : 'white';
        $myTurn   = $game->current_color === $botColor && $game->status === 'active';

        $data = [
            'id'               => $game->id,
            'status'           => $game->status,
            'board_size'       => $game->board_size,
            'komi'             => $game->komi,
            'handicap'         => $game->handicap,
            'clock_type'       => $game->clock_type,
            'current_color'    => $game->current_color,
            'bot_color'        => $botColor,
            'my_turn'          => $myTurn,
            'consecutive_passes' => $game->consecutive_passes,
            'captures_black'   => $game->captures_black,
            'captures_white'   => $game->captures_white,
            'black_time_left'  => $game->black_time_left,
            'white_time_left'  => $game->white_time_left,
            'black_periods_left' => $game->black_periods_left,
            'white_periods_left' => $game->white_periods_left,
            'move_number'      => $game->move_number,
        ];

        if ($full) {
            $data['board_state'] = $game->board_state;
        }

        return $data;
    }
}
