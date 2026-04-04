<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel("chat.{$this->message->chat_room_id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'user' => [
                'id' => $this->message->user_id,
                'name' => $this->message->user?->getDisplayName(),
                'avatar' => $this->message->user?->getAvatarUrl(),
                'rank' => $this->message->user?->rank,
            ],
            'body' => $this->message->body,
            'createdAt' => $this->message->created_at->toISOString(),
        ];
    }
}
