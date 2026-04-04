<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserStat;
use App\Services\RatingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BotSeeder extends Seeder
{
    private const BOTS = [
        [
            'name'         => 'มาก-8k',
            'username'     => 'bot_8k',
            'display_name' => 'มาก [BOT]',
            'rank'         => '8k',
            'bot_level'    => '8k',
            'bio'          => 'บอทฝึกหัดระดับ 8 คิว เหมาะสำหรับมือใหม่ (KataGo)',
        ],
        [
            'name'         => 'เก่ง-5k',
            'username'     => 'bot_5k',
            'display_name' => 'เก่ง [BOT]',
            'rank'         => '5k',
            'bot_level'    => '5k',
            'bio'          => 'บอทระดับ 5 คิว มีกลยุทธ์พื้นฐาน (KataGo)',
        ],
        [
            'name'         => 'ฉลาด-2k',
            'username'     => 'bot_2k',
            'display_name' => 'ฉลาด [BOT]',
            'rank'         => '2k',
            'bot_level'    => '2k',
            'bio'          => 'บอทระดับ 2 คิว ขับเคลื่อนด้วย KataGo Neural Network',
        ],
        [
            'name'         => 'แกร่ง-1d',
            'username'     => 'bot_1d',
            'display_name' => 'แกร่ง [BOT]',
            'rank'         => '1d',
            'bot_level'    => '1d',
            'bio'          => 'บอทระดับ 1 ดั้ง ขับเคลื่อนด้วย KataGo Neural Network',
        ],
        [
            'name'         => 'อัจฉริยะ-3d',
            'username'     => 'bot_3d',
            'display_name' => 'อัจฉริยะ [BOT]',
            'rank'         => '3d',
            'bot_level'    => '3d',
            'bio'          => 'บอทระดับ 3 ดั้ง ขับเคลื่อนด้วย KataGo Neural Network ระดับสูง',
        ],
    ];

    public function run(): void
    {
        foreach (self::BOTS as $botData) {
            $bot = User::firstOrCreate(
                ['username' => $botData['username']],
                [
                    'name'              => $botData['name'],
                    'display_name'      => $botData['display_name'],
                    'email'             => $botData['username'] . '@bot.maklom.local',
                    'password'          => Hash::make(\Illuminate\Support\Str::random(64)),
                    'rank'              => $botData['rank'],
                    'rank_points'       => RatingService::initialRatingForRank($botData['rank']),
                    'bio'               => $botData['bio'],
                    'is_bot'            => true,
                    'bot_level'         => $botData['bot_level'],
                    'email_verified_at' => now(),
                    'locale'            => 'th',
                ]
            );

            UserStat::firstOrCreate(['user_id' => $bot->id], [
                'games_played' => 0,
                'games_won'    => 0,
                'games_lost'   => 0,
                'games_drawn'  => 0,
                'win_streak'   => 0,
                'best_win_streak' => 0,
                'total_moves'  => 0,
            ]);
        }
    }
}
