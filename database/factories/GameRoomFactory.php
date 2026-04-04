<?php

namespace Database\Factories;

use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameRoom>
 */
class GameRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'creator_id' => User::factory(),
            'board_size' => fake()->randomElement([9, 13, 19]),
            'clock_type' => 'byoyomi',
            'main_time' => 300,
            'byoyomi_periods' => 5,
            'byoyomi_seconds' => 30,
            'fischer_increment' => 10,
            'komi' => 6.5,
            'handicap' => 0,
            'is_private' => false,
            'password' => null,
            'status' => 'waiting',
            'max_observers' => 10,
        ];
    }

    public function fischer(): static
    {
        return $this->state(fn (array $attributes) => [
            'clock_type' => 'fischer',
            'fischer_increment' => 10,
        ]);
    }

    public function playing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'playing',
        ]);
    }
}
