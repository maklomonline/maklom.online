<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerResigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Game $game, public string $color) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("game.{$this->game->id}")];
    }

    public function broadcastWith(): array
    {
        return ['color' => $this->color];
    }
}
