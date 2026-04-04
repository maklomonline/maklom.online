<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Game $game) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("game.{$this->game->id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'result' => $this->game->result,
            'endReason' => $this->game->end_reason,
            'winnerId' => $this->game->winner_id,
        ];
    }
}
