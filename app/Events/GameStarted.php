<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Game $game) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("room.{$this->game->room_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'gameId' => $this->game->id,
            'blackPlayer' => ['id' => $this->game->black_player_id, 'name' => $this->game->blackPlayer?->getDisplayName()],
            'whitePlayer' => ['id' => $this->game->white_player_id, 'name' => $this->game->whitePlayer?->getDisplayName()],
        ];
    }
}
