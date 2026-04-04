<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\GoEngine\BoardService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    public function definition(): array
    {
        $size = 9;
        $board = new BoardService();
        $boardState = $board->createEmptyBoard($size);

        return [
            'room_id' => GameRoom::factory(),
            'black_player_id' => User::factory(),
            'white_player_id' => User::factory(),
            'board_size' => $size,
            'komi' => 6.5,
            'handicap' => 0,
            'clock_type' => 'byoyomi',
            'main_time' => 300,
            'byoyomi_periods' => 5,
            'byoyomi_seconds' => 30,
            'fischer_increment' => 10,
            'black_time_left' => 300,
            'white_time_left' => 300,
            'black_periods_left' => 5,
            'white_periods_left' => 5,
            'current_color' => 'black',
            'move_number' => 0,
            'board_state' => $boardState,
            'captures_black' => 0,
            'captures_white' => 0,
            'ko_point' => null,
            'consecutive_passes' => 0,
            'status' => 'active',
            'started_at' => now(),
        ];
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'finished',
            'result' => 'B+R',
            'end_reason' => 'resign',
            'finished_at' => now(),
        ]);
    }

    public function scoring(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scoring',
        ]);
    }
}
