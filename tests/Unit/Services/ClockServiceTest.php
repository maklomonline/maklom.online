<?php

namespace Tests\Unit\Services;

use App\Models\Game;
use App\Services\ClockService;
use App\Services\ClockTimeoutException;
use PHPUnit\Framework\TestCase;

class ClockServiceTest extends TestCase
{
    private ClockService $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new ClockService();
    }

    private function makeGame(array $attrs = []): Game
    {
        $game = new Game();
        $game->clock_type = $attrs['clock_type'] ?? 'byoyomi';
        $game->main_time = $attrs['main_time'] ?? 300;
        $game->byoyomi_periods = $attrs['byoyomi_periods'] ?? 5;
        $game->byoyomi_seconds = $attrs['byoyomi_seconds'] ?? 30;
        $game->fischer_increment = $attrs['fischer_increment'] ?? 10;
        $game->black_time_left = $attrs['black_time_left'] ?? 300;
        $game->white_time_left = $attrs['white_time_left'] ?? 300;
        $game->black_periods_left = $attrs['black_periods_left'] ?? 5;
        $game->white_periods_left = $attrs['white_periods_left'] ?? 5;

        return $game;
    }

    // ─── Byoyomi ───────────────────────────────────────────────────────────────

    public function test_byoyomi_deducts_main_time(): void
    {
        $game = $this->makeGame(['black_time_left' => 120]);
        $result = $this->clock->recordMove($game, 'black', 30);

        $this->assertEquals(90, $result['black_time_left']);
        $this->assertEquals(5, $result['black_periods_left']); // unchanged
    }

    public function test_byoyomi_period_resets_on_fast_move(): void
    {
        $game = $this->makeGame(['black_time_left' => 0, 'black_periods_left' => 3]);
        $result = $this->clock->recordMove($game, 'black', 20); // within 30s period

        $this->assertEquals(0, $result['black_time_left']);
        $this->assertEquals(3, $result['black_periods_left']); // period resets, count stays
    }

    public function test_byoyomi_loses_period_on_slow_move(): void
    {
        $game = $this->makeGame(['black_time_left' => 0, 'black_periods_left' => 3]);
        $result = $this->clock->recordMove($game, 'black', 35); // exceeds 30s period

        $this->assertEquals(0, $result['black_time_left']);
        $this->assertLessThan(3, $result['black_periods_left']);
    }

    public function test_byoyomi_timeout_when_all_periods_used(): void
    {
        $game = $this->makeGame(['black_time_left' => 0, 'black_periods_left' => 1]);

        $this->expectException(ClockTimeoutException::class);
        $this->clock->recordMove($game, 'black', 35); // uses last period
    }

    public function test_byoyomi_overflow_from_main_to_periods(): void
    {
        $game = $this->makeGame(['black_time_left' => 5, 'black_periods_left' => 3]);
        // Spends 25s: 5s main + 20s in byoyomi (within period)
        $result = $this->clock->recordMove($game, 'black', 25);

        $this->assertEquals(0, $result['black_time_left']);
        $this->assertEquals(3, $result['black_periods_left']); // still 3 periods (used within 30s)
    }

    // ─── Fischer ──────────────────────────────────────────────────────────────

    public function test_fischer_deducts_time_and_adds_increment(): void
    {
        $game = $this->makeGame(['clock_type' => 'fischer', 'black_time_left' => 120, 'fischer_increment' => 10]);
        $result = $this->clock->recordMove($game, 'black', 30);

        $this->assertEquals(100, $result['black_time_left']); // 120 - 30 + 10
    }

    public function test_fischer_timeout_when_time_runs_out(): void
    {
        $game = $this->makeGame(['clock_type' => 'fischer', 'black_time_left' => 10, 'fischer_increment' => 5]);

        $this->expectException(ClockTimeoutException::class);
        $this->clock->recordMove($game, 'black', 20); // 10 - 20 + 5 = -5 <= 0
    }

    public function test_fischer_does_not_affect_opponent_time(): void
    {
        $game = $this->makeGame(['clock_type' => 'fischer', 'black_time_left' => 120, 'white_time_left' => 200]);
        $result = $this->clock->recordMove($game, 'black', 10);

        $this->assertEquals(200, $result['white_time_left']); // unchanged
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function test_format_time(): void
    {
        $this->assertEquals('05:00', $this->clock->formatTime(300));
        $this->assertEquals('01:30', $this->clock->formatTime(90));
        $this->assertEquals('00:05', $this->clock->formatTime(5));
    }

    public function test_has_timed_out_byoyomi(): void
    {
        $game = $this->makeGame(['black_time_left' => 0, 'black_periods_left' => 0]);
        $this->assertTrue($this->clock->hasTimedOut($game, 'black'));
    }

    public function test_has_not_timed_out_with_periods_remaining(): void
    {
        $game = $this->makeGame(['black_time_left' => 0, 'black_periods_left' => 1]);
        $this->assertFalse($this->clock->hasTimedOut($game, 'black'));
    }

    public function test_has_timed_out_fischer(): void
    {
        $game = $this->makeGame(['clock_type' => 'fischer', 'black_time_left' => 0]);
        $this->assertTrue($this->clock->hasTimedOut($game, 'black'));
    }
}
