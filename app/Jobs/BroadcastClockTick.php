<?php

namespace App\Jobs;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastClockTick implements ShouldQueue, ShouldBroadcast
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(public int $gameId) {}

    public function broadcastOn(): array
    {
        return [new Channel("game.{$this->gameId}")];
    }

    public function broadcastAs(): string
    {
        return 'ClockTick';
    }

    public function broadcastWith(): array
    {
        $game = Game::find($this->gameId);
        if (! $game) {
            return [];
        }

        return [
            'blackTimeLeft' => $game->black_time_left,
            'whiteTimeLeft' => $game->white_time_left,
            'blackPeriodsLeft' => $game->black_periods_left,
            'whitePeriodsLeft' => $game->white_periods_left,
            'currentColor' => $game->current_color,
        ];
    }

    public function handle(): void
    {
        $game = Game::find($this->gameId);
        if (! $game || $game->status !== 'active') {
            return;
        }

        // Schedule next tick
        static::dispatch($this->gameId)->delay(now()->addSecond());
    }
}
