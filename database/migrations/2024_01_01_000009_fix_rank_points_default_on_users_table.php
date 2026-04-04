<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // เปลี่ยน default ของ rank_points จาก 0 เป็น 25 (midpoint ของ 30k: 0–50)
        Schema::table('users', function (Blueprint $table) {
            $table->integer('rank_points')->default(25)->change();
        });

        // Sync rank ของ users ที่มีอยู่ให้สอดคล้องกับ rank_points
        // (ใช้ CASE WHEN แทนการเรียก RatingService เพื่อให้ migration รันได้อิสระ)
        DB::statement("
            UPDATE users SET rank = CASE
                WHEN rank_points BETWEEN 0    AND 50   THEN '30k'
                WHEN rank_points BETWEEN 51   AND 100  THEN '29k'
                WHEN rank_points BETWEEN 101  AND 150  THEN '28k'
                WHEN rank_points BETWEEN 151  AND 200  THEN '27k'
                WHEN rank_points BETWEEN 201  AND 250  THEN '26k'
                WHEN rank_points BETWEEN 251  AND 300  THEN '25k'
                WHEN rank_points BETWEEN 301  AND 350  THEN '24k'
                WHEN rank_points BETWEEN 351  AND 400  THEN '23k'
                WHEN rank_points BETWEEN 401  AND 450  THEN '22k'
                WHEN rank_points BETWEEN 451  AND 500  THEN '21k'
                WHEN rank_points BETWEEN 501  AND 550  THEN '20k'
                WHEN rank_points BETWEEN 551  AND 600  THEN '19k'
                WHEN rank_points BETWEEN 601  AND 650  THEN '18k'
                WHEN rank_points BETWEEN 651  AND 700  THEN '17k'
                WHEN rank_points BETWEEN 701  AND 750  THEN '16k'
                WHEN rank_points BETWEEN 751  AND 800  THEN '15k'
                WHEN rank_points BETWEEN 801  AND 850  THEN '14k'
                WHEN rank_points BETWEEN 851  AND 900  THEN '13k'
                WHEN rank_points BETWEEN 901  AND 950  THEN '12k'
                WHEN rank_points BETWEEN 951  AND 1000 THEN '11k'
                WHEN rank_points BETWEEN 1001 AND 1075 THEN '10k'
                WHEN rank_points BETWEEN 1076 AND 1150 THEN '9k'
                WHEN rank_points BETWEEN 1151 AND 1225 THEN '8k'
                WHEN rank_points BETWEEN 1226 AND 1300 THEN '7k'
                WHEN rank_points BETWEEN 1301 AND 1375 THEN '6k'
                WHEN rank_points BETWEEN 1376 AND 1450 THEN '5k'
                WHEN rank_points BETWEEN 1451 AND 1525 THEN '4k'
                WHEN rank_points BETWEEN 1526 AND 1600 THEN '3k'
                WHEN rank_points BETWEEN 1601 AND 1675 THEN '2k'
                WHEN rank_points BETWEEN 1676 AND 1750 THEN '1k'
                WHEN rank_points BETWEEN 1751 AND 1875 THEN '1d'
                WHEN rank_points BETWEEN 1876 AND 2000 THEN '2d'
                WHEN rank_points BETWEEN 2001 AND 2125 THEN '3d'
                WHEN rank_points BETWEEN 2126 AND 2250 THEN '4d'
                WHEN rank_points BETWEEN 2251 AND 2375 THEN '5d'
                ELSE '6d'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('rank_points')->default(0)->change();
        });
    }
};
