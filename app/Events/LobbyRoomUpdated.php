<?php

namespace App\Events;

use App\Models\GameRoom;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LobbyRoomUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public GameRoom $room, public string $action = 'updated') {}

    public function broadcastOn(): array
    {
        return [new Channel('lobby')];
    }

    public function broadcastWith(): array
    {
        $gameId = null;
        if ($this->room->status === 'playing') {
            $gameId = $this->room->currentGame?->id;
        }

        return [
            'action' => $this->action,
            'room' => [
                'id' => $this->room->id,
                'name' => $this->room->name,
                'status' => $this->room->status,
                'boardSize' => $this->room->board_size,
                'komi' => $this->room->komi,
                'handicap' => $this->room->handicap,
                'clockDescription' => $this->room->getClockDescription(),
                'creator' => $this->room->creator?->getDisplayName(),
                'creatorRank' => $this->room->creator?->rank,
                'isPrivate' => $this->room->is_private,
                'gameId' => $gameId,
            ],
        ];
    }
}
