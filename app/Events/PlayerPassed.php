<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerPassed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Game $game, public string $color) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("game.{$this->game->id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'color' => $this->color,
            'nextColor' => $this->game->current_color,
            'moveNumber' => $this->game->move_number,
            'consecutivePasses' => $this->game->consecutive_passes,
            'blackTimeLeft' => $this->game->black_time_left,
            'whiteTimeLeft' => $this->game->white_time_left,
            'blackPeriodsLeft' => $this->game->black_periods_left,
            'whitePeriodsLeft' => $this->game->white_periods_left,
        ];
    }
}
