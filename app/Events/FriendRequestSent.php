<?php

namespace App\Events;

use App\Models\Friendship;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendRequestSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Friendship $friendship) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->friendship->addressee_id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'friendshipId' => $this->friendship->id,
            'requester' => [
                'id' => $this->friendship->requester_id,
                'name' => $this->friendship->requester?->getDisplayName(),
                'avatar' => $this->friendship->requester?->getAvatarUrl(),
                'rank' => $this->friendship->requester?->rank,
            ],
        ];
    }
}
