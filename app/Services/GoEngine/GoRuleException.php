<?php

namespace App\Services\GoEngine;

use Exception;

class GoRuleException extends Exception
{
    public static function occupied(): self
    {
        return new self('จุดนั้นมีหมากอยู่แล้ว');
    }

    public static function suicide(): self
    {
        return new self('ห้ามวางหมากฆ่าตัวเอง (suicide)');
    }

    public static function ko(): self
    {
        return new self('กฎโค: ห้ามวางหมากซ้ำตำแหน่งที่เพิ่งจับไป');
    }

    public static function outOfBounds(): self
    {
        return new self('ตำแหน่งอยู่นอกกระดาน');
    }

    public static function notYourTurn(): self
    {
        return new self('ยังไม่ถึงตาของคุณ');
    }

    public static function gameNotActive(): self
    {
        return new self('เกมนี้ไม่ได้อยู่ในสถานะที่กำลังเล่น');
    }
}
