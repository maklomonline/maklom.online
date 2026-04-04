<?php

namespace Database\Seeders;

use App\Models\ChatRoom;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create global chat room
        ChatRoom::forGlobal();

        // Create bot accounts
        $this->call(BotSeeder::class);

        // Create admin user
        $admin = User::factory()->admin()->create([
            'name' => 'ผู้ดูแลระบบ',
            'username' => 'admin',
            'email' => 'admin@maklom.online',
            'display_name' => 'ผู้ดูแลระบบ',
        ]);
        UserStat::create(['user_id' => $admin->id]);

        // Create some test users
        User::factory(10)->create()->each(function ($user) {
            UserStat::create(['user_id' => $user->id]);
        });
    }
}
