<?php

namespace App\Services;

class RatingService
{
    /**
     * Rank => [min_rating, max_rating]
     * 6d ไม่มีเพดานบน ใช้ 9999 แทน
     */
    private const RANK_RANGES = [
        '30k' => [0,    50],
        '29k' => [51,   100],
        '28k' => [101,  150],
        '27k' => [151,  200],
        '26k' => [201,  250],
        '25k' => [251,  300],
        '24k' => [301,  350],
        '23k' => [351,  400],
        '22k' => [401,  450],
        '21k' => [451,  500],
        '20k' => [501,  550],
        '19k' => [551,  600],
        '18k' => [601,  650],
        '17k' => [651,  700],
        '16k' => [701,  750],
        '15k' => [751,  800],
        '14k' => [801,  850],
        '13k' => [851,  900],
        '12k' => [901,  950],
        '11k' => [951,  1000],
        '10k' => [1001, 1075],
        '9k'  => [1076, 1150],
        '8k'  => [1151, 1225],
        '7k'  => [1226, 1300],
        '6k'  => [1301, 1375],
        '5k'  => [1376, 1450],
        '4k'  => [1451, 1525],
        '3k'  => [1526, 1600],
        '2k'  => [1601, 1675],
        '1k'  => [1676, 1750],
        '1d'  => [1751, 1875],
        '2d'  => [1876, 2000],
        '3d'  => [2001, 2125],
        '4d'  => [2126, 2250],
        '5d'  => [2251, 2375],
        '6d'  => [2376, 9999],
    ];

    /**
     * Rank ที่ผู้เล่นใหม่เลือกได้เมื่อสมัคร (30k ถึง 1d)
     */
    public static function registrableRanks(): array
    {
        $ranks = [];
        foreach (self::RANK_RANGES as $rank => $_) {
            $ranks[] = $rank;
            if ($rank === '1d') {
                break;
            }
        }
        return $ranks;
    }

    /**
     * Rank ทั้งหมดในระบบ (30k ถึง 6d)
     */
    public static function allRanks(): array
    {
        return array_keys(self::RANK_RANGES);
    }

    /**
     * คะแนนเริ่มต้นของแต่ละ rank (จุดกึ่งกลางของช่วง)
     */
    public static function initialRatingForRank(string $rank): int
    {
        if (! isset(self::RANK_RANGES[$rank])) {
            return 25;
        }
        [$min, $max] = self::RANK_RANGES[$rank];
        // 6d ไม่มีเพดานบน — ใช้ค่าเริ่มต้น + 62 (ครึ่งหนึ่งของ 125)
        if ($rank === '6d') {
            return 2438;
        }
        return (int) (($min + $max) / 2);
    }

    /**
     * แปลง rating point เป็น rank string
     */
    public static function getRankFromRating(int $rating): string
    {
        $rating = max(0, $rating);
        foreach (self::RANK_RANGES as $rank => [$min, $max]) {
            if ($rating >= $min && $rating <= $max) {
                return $rank;
            }
        }
        return '6d';
    }

    /**
     * คำนวณการเปลี่ยนแปลง rating หลังเกมจบ
     * คืนค่า [$winnerChange, $loserChange] (loserChange เป็นลบ)
     */
    public static function calculateChanges(int $winnerRating, int $loserRating): array
    {
        $kWinner = self::kFactor($winnerRating);
        $kLoser  = self::kFactor($loserRating);

        // ความน่าจะเป็นที่ผู้ชนะจะชนะตาม Elo
        $expectedWinner = 1 / (1 + pow(10, ($loserRating - $winnerRating) / 400));
        $expectedLoser  = 1 - $expectedWinner;

        $winnerChange = (int) round($kWinner * (1 - $expectedWinner));
        $loserChange  = (int) round($kLoser  * (0 - $expectedLoser));

        return [$winnerChange, $loserChange];
    }

    /**
     * K-factor: ยิ่งแรงค์สูงยิ่งเปลี่ยนช้า
     */
    private static function kFactor(int $rating): int
    {
        if ($rating < 1000) return 40;
        if ($rating < 1500) return 32;
        if ($rating < 1750) return 24;
        return 20;
    }

    /**
     * Label สำหรับแสดงผล เช่น "30k" → "30 คิว (30k)"
     */
    public static function rankLabel(string $rank): string
    {
        if (str_ends_with($rank, 'k')) {
            $num = (int) $rank;
            return "{$num} คิว ({$rank})";
        }
        if (str_ends_with($rank, 'd')) {
            $num = (int) $rank;
            return "{$num} ดั้ง ({$rank})";
        }
        return $rank;
    }
}
