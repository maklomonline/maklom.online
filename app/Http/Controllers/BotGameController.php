<?php

namespace App\Http\Controllers;

use App\Jobs\BotMoveJob;
use App\Models\ChatRoom;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\User;
use App\Models\UserStat;
use App\Services\GameService;
use App\Services\GoEngine\BoardService;
use Illuminate\Http\Request;

class BotGameController extends Controller
{
    public function __construct(private GameService $gameService) {}

    /** หน้าเลือกบอท */
    public function index()
    {
        $bots = User::where('is_bot', true)
            ->orderByRaw("CASE bot_level
                WHEN '8k' THEN 1
                WHEN '5k' THEN 2
                WHEN '2k' THEN 3
                WHEN '1d' THEN 4
                WHEN '3d' THEN 5
                ELSE 6 END")
            ->get();

        return view('bots.index', compact('bots'));
    }

    /** สร้างเกมกับบอท */
    public function play(User $bot, Request $request)
    {
        if (! $bot->is_bot) {
            abort(404);
        }

        $validated = $request->validate([
            'board_size'        => ['required', 'integer', 'in:9,13,19'],
            'player_color'      => ['required', 'string', 'in:black,white,random'],
            'handicap'          => ['integer', 'min:0', 'max:9'],
            'clock_type'        => ['required', 'string', 'in:byoyomi,fischer'],
            'main_time'         => ['required', 'integer', 'min:60', 'max:3600'],
            'byoyomi_periods'   => ['integer', 'min:1', 'max:10'],
            'byoyomi_seconds'   => ['integer', 'min:10', 'max:300'],
            'fischer_increment' => ['integer', 'min:0', 'max:60'],
        ]);

        $human     = $request->user();
        $boardSize = (int) $validated['board_size'];
        $handicap  = (int) ($validated['handicap'] ?? 0);
        $komi      = ($handicap >= 2) ? 0.5 : 6.5;

        // สุ่มสีถ้าเลือก random
        $playerColor = $validated['player_color'];
        if ($playerColor === 'random') {
            $playerColor = (mt_rand(0, 1) === 0) ? 'black' : 'white';
        }

        $black = ($playerColor === 'black') ? $human : $bot;
        $white = ($playerColor === 'white') ? $human : $bot;

        // สร้าง room สำหรับเกมนี้ (ไม่แสดงใน lobby)
        $room = GameRoom::create([
            'name'              => "vs {$bot->display_name}",
            'creator_id'        => $human->id,
            'board_size'        => $boardSize,
            'komi'              => $komi,
            'handicap'          => $handicap,
            'clock_type'        => $validated['clock_type'],
            'main_time'         => (int) $validated['main_time'],
            'byoyomi_periods'   => (int) ($validated['byoyomi_periods'] ?? 3),
            'byoyomi_seconds'   => (int) ($validated['byoyomi_seconds'] ?? 30),
            'fischer_increment' => (int) ($validated['fischer_increment'] ?? 10),
            'is_private'        => true,
            'status'            => 'playing',
        ]);

        // สร้างเกมทันที
        $boardState = app(BoardService::class)->createEmptyBoard($boardSize);
        if ($handicap >= 2) {
            $boardState = app(BoardService::class)->applyHandicap($boardState, $boardSize, $handicap);
        }

        $game = Game::create([
            'room_id'           => $room->id,
            'black_player_id'   => $black->id,
            'white_player_id'   => $white->id,
            'board_size'        => $boardSize,
            'komi'              => $komi,
            'handicap'          => $handicap,
            'clock_type'        => $validated['clock_type'],
            'main_time'         => (int) $validated['main_time'],
            'byoyomi_periods'   => (int) ($validated['byoyomi_periods'] ?? 3),
            'byoyomi_seconds'   => (int) ($validated['byoyomi_seconds'] ?? 30),
            'fischer_increment' => (int) ($validated['fischer_increment'] ?? 10),
            'black_time_left'   => (int) $validated['main_time'],
            'white_time_left'   => (int) $validated['main_time'],
            'black_periods_left' => (int) ($validated['byoyomi_periods'] ?? 3),
            'white_periods_left' => (int) ($validated['byoyomi_periods'] ?? 3),
            'current_color'     => ($handicap >= 2) ? 'white' : 'black',
            'board_state'       => $boardState,
            'status'            => 'active',
            'started_at'        => now(),
        ]);

        // สร้าง chat room สำหรับเกมนี้
        ChatRoom::firstOrCreate([
            'type'         => 'game',
            'reference_id' => $game->id,
        ]);

        // ตรวจว่าบอทเป็นฝ่ายเริ่มก่อนหรือไม่
        $firstColor = ($handicap >= 2) ? 'white' : 'black';
        $botColor   = ($bot->id === $black->id) ? 'black' : 'white';

        if ($botColor === $firstColor) {
            BotMoveJob::dispatch($game->id, $botColor)->delay(now()->addSeconds(2));
        }

        return redirect()->route('games.show', $game)
            ->with('success', "เริ่มเกมกับ {$bot->getDisplayName()} แล้ว!");
    }
}
