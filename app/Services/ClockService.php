<?php

namespace App\Services;

use App\Models\Game;

class ClockTimeoutException extends \Exception
{
    public function __construct(string $color)
    {
        parent::__construct("ผู้เล่น{$color}หมดเวลา");
    }
}

class ClockService
{
    /**
     * Deduct time for a move and return updated clock fields.
     * Throws ClockTimeoutException if player has run out of time.
     *
     * @return array{black_time_left: int, white_time_left: int, black_periods_left: ?int, white_periods_left: ?int}
     *
     * @throws ClockTimeoutException
     */
    public function recordMove(Game $game, string $color, int $secondsSpent): array
    {
        $timeLeft    = $color === 'black' ? $game->black_time_left    : $game->white_time_left;
        $periodsLeft = $color === 'black' ? $game->black_periods_left : $game->white_periods_left;

        if ($game->clock_type === 'byoyomi') {
            $byoyomiSeconds = (int) ($game->byoyomi_seconds ?? 30);
            [$timeLeft, $periodsLeft] = $this->processByoyomi(
                $timeLeft, $periodsLeft, $secondsSpent, $byoyomiSeconds
            );
        } else {
            $increment = (int) ($game->fischer_increment ?? 0);
            $timeLeft  = $this->processFischer($timeLeft, $secondsSpent, $increment);
            $periodsLeft = null;
        }

        if ($timeLeft <= 0 && ($periodsLeft === null || $periodsLeft <= 0)) {
            throw new ClockTimeoutException($color === 'black' ? 'ดำ' : 'ขาว');
        }

        $result = [
            'black_time_left'    => $game->black_time_left,
            'white_time_left'    => $game->white_time_left,
            'black_periods_left' => $game->black_periods_left,
            'white_periods_left' => $game->white_periods_left,
        ];

        $result["{$color}_time_left"]    = $timeLeft;
        $result["{$color}_periods_left"] = $periodsLeft;

        return $result;
    }

    /**
     * Process byo-yomi clock deduction.
     *
     * Rules:
     * - Player has mainTimeLeft seconds of main time.
     * - When main time runs out, each move must be made within byoyomiSeconds.
     * - If the move exceeds byoyomiSeconds, periods are consumed.
     * - periodsLeft stays the same if move is made within the period.
     *
     * @return array{int, ?int} [timeLeft, periodsLeft]
     */
    private function processByoyomi(int $mainTimeLeft, ?int $periodsLeft, int $secondsSpent, int $byoyomiSeconds): array
    {
        // Prevent division-by-zero if byoyomi_seconds is misconfigured.
        if ($byoyomiSeconds <= 0) {
            $byoyomiSeconds = 30;
        }

        if ($mainTimeLeft > 0) {
            $remaining = $mainTimeLeft - $secondsSpent;
            if ($remaining > 0) {
                // Still in main time, time remaining.
                return [$remaining, $periodsLeft];
            }
            // Main time exhausted — overflow into byo-yomi.
            $secondsSpent  = -$remaining; // only the overflow counts against byo-yomi
            $mainTimeLeft  = 0;
        }

        // Reached byo-yomi phase.
        // If no periods configured, player has timed out.
        if ($periodsLeft === null || $periodsLeft <= 0) {
            return [0, 0];
        }

        if ($secondsSpent <= $byoyomiSeconds) {
            // Move made within the period — period resets, count unchanged.
            return [0, $periodsLeft];
        }

        // More than one period worth of time was used.
        // floor() because a partially-used period only consumes 1 period.
        $periodsUsed = (int) floor($secondsSpent / $byoyomiSeconds);
        $periodsLeft -= $periodsUsed;

        if ($periodsLeft <= 0) {
            return [0, 0];
        }

        return [0, $periodsLeft];
    }

    private function processFischer(int $timeLeft, int $secondsSpent, int $increment): int
    {
        return max(0, $timeLeft - $secondsSpent + $increment);
    }

    public function hasTimedOut(Game $game, string $color): bool
    {
        $timeLeft    = $color === 'black' ? $game->black_time_left    : $game->white_time_left;
        $periodsLeft = $color === 'black' ? $game->black_periods_left : $game->white_periods_left;

        if ($game->clock_type === 'byoyomi') {
            return $timeLeft <= 0 && ($periodsLeft !== null && $periodsLeft <= 0);
        }

        return $timeLeft <= 0;
    }

    public function formatTime(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $secs    = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $secs);
    }
}
