<?php

namespace Database\Factories;

use App\Models\User;
use App\Services\RatingService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        // สุ่ม rank แล้วคำนวณ rank_points ให้สอดคล้องกัน
        $rank       = fake()->randomElement(RatingService::allRanks());
        $rankPoints = RatingService::initialRatingForRank($rank);

        return [
            'name'               => fake()->name(),
            'username'           => fake()->unique()->userName(),
            'display_name'       => fake()->optional()->name(),
            'email'              => fake()->unique()->safeEmail(),
            'email_verified_at'  => now(),
            'password'           => static::$password ??= Hash::make('password'),
            'remember_token'     => Str::random(10),
            'rank'               => $rank,
            'rank_points'        => $rankPoints,
            'locale'             => 'th',
            'is_admin'           => false,
            'is_banned'          => false,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin'          => true,
            'email_verified_at' => now(),
        ]);
    }

    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_banned'         => true,
            'ban_reason'        => 'ละเมิดกฎการใช้งาน',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * สร้าง user ที่มี rank เฉพาะเจาะจง (rank_points เป็น midpoint)
     */
    public function withRank(string $rank): static
    {
        return $this->state(fn (array $attributes) => [
            'rank'        => $rank,
            'rank_points' => RatingService::initialRatingForRank($rank),
        ]);
    }
}
