<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChatRoom;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\GoEngine\BoardService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ChallengeController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
        private BoardService $boardService,
    ) {}

    /** ส่งคำท้าดวล */
    public function send(User $user, Request $request)
    {
        $from = $request->user();

        if ($user->id === $from->id) {
            abort(403);
        }

        // ถ้า challenged user เป็นบอท ตรวจสอบว่า bot client ออนไลน์หรือไม่
        if ($user->is_bot) {
            if (! $user->isBotOnline()) {
                return response()->json([
                    'error'   => 'บัญชีคอมพิวเตอร์นี้ไม่พร้อมให้เล่นในขณะนี้ กรุณาลองใหม่ภายหลัง',
                    'offline' => true,
                ], 422);
            }
        }

        $validated = $request->validate([
            'board_size'        => ['required', 'integer', 'in:9,13,19'],
            'clock_type'        => ['required', 'string', 'in:byoyomi,fischer'],
            'main_time'         => ['required', 'integer', 'min:60', 'max:3600'],
            'byoyomi_periods'   => ['integer', 'min:1', 'max:10'],
            'byoyomi_seconds'   => ['integer', 'min:10', 'max:300'],
            'fischer_increment' => ['integer', 'min:0', 'max:60'],
            'handicap'          => ['integer', 'min:0', 'max:9'],
        ]);

        // ยกเลิก challenge เก่าที่ค้างอยู่
        Challenge::where('challenger_id', $from->id)
            ->where('challenged_id', $user->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $challenge = Challenge::create([
            'challenger_id'     => $from->id,
            'challenged_id'     => $user->id,
            'board_size'        => (int) $validated['board_size'],
            'clock_type'        => $validated['clock_type'],
            'main_time'         => (int) $validated['main_time'],
            'byoyomi_periods'   => (int) ($validated['byoyomi_periods'] ?? 3),
            'byoyomi_seconds'   => (int) ($validated['byoyomi_seconds'] ?? 30),
            'fischer_increment' => (int) ($validated['fischer_increment'] ?? 10),
            'handicap'          => (int) ($validated['handicap'] ?? 0),
            'status'            => 'pending',
            'expires_at'        => now()->addMinutes(10),
        ]);

        $boardLabel = "{$challenge->board_size}×{$challenge->board_size}";

        if (! $user->is_bot) {
            $this->notificationService->send(
                $user,
                'challenge',
                "{$from->getDisplayName()} ท้าดวลคุณ",
                "กระดาน {$boardLabel} · {$challenge->clock_type}",
                ['challenge_id' => $challenge->id, 'challenger_name' => $from->getDisplayName()]
            );
        }

        return response()->json([
            'success' => true,
            'message' => $user->is_bot
                ? "ส่งคำท้าดวลไปยัง {$user->getDisplayName()} แล้ว รอ bot client รับ..."
                : 'ส่งคำท้าดวลแล้ว',
        ]);
    }

    /** รับคำท้าดวล → สร้างเกม */
    public function accept(Challenge $challenge, Request $request)
    {
        $user = $request->user();

        if ($challenge->challenged_id !== $user->id) {
            abort(403);
        }

        if ($challenge->status !== 'pending' || $challenge->isExpired()) {
            return response()->json(['error' => 'คำท้าดวลหมดอายุหรือถูกยกเลิกแล้ว'], 422);
        }

        $challenge->update(['status' => 'accepted']);

        $challenger = $challenge->challenger;
        $challenged = $user;
        $boardSize  = $challenge->board_size;
        $handicap   = $challenge->handicap;
        $komi       = ($handicap >= 2) ? 0.5 : 6.5;

        $room = GameRoom::create([
            'name'              => "{$challenger->getDisplayName()} vs {$challenged->getDisplayName()}",
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
            'white_player_id'    => $challenged->id,
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

        $gameUrl = route('games.show', $game);

        $this->notificationService->send(
            $challenger,
            'challenge_accepted',
            "{$challenged->getDisplayName()} รับคำท้าดวลแล้ว!",
            "เกมเริ่มแล้ว — กระดาน {$boardSize}×{$boardSize}",
            ['game_url' => $gameUrl]
        );

        return response()->json(['success' => true, 'game_url' => $gameUrl]);
    }

    /** ปฏิเสธคำท้าดวล */
    public function decline(Challenge $challenge, Request $request)
    {
        $user = $request->user();

        if ($challenge->challenged_id !== $user->id) {
            abort(403);
        }

        if ($challenge->status !== 'pending') {
            return response()->json(['error' => 'ไม่สามารถปฏิเสธได้'], 422);
        }

        $challenge->update(['status' => 'declined']);

        $this->notificationService->send(
            $challenge->challenger,
            'challenge_declined',
            "{$user->getDisplayName()} ปฏิเสธคำท้าดวล",
            null,
            []
        );

        return response()->json(['success' => true]);
    }
}
