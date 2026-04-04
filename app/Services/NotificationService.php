<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\AppNotification;
use App\Models\User;

class NotificationService
{
    public function send(User $recipient, string $type, string $title, ?string $body = null, array $data = []): AppNotification
    {
        $notification = AppNotification::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        broadcast(new NotificationCreated($notification))->toOthers();

        return $notification;
    }

    public function markAllRead(User $user): void
    {
        AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function getUnreadCount(User $user): int
    {
        return AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }
}
