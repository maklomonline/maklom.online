<?php

use App\Models\ChatRoom;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\Group;
use Illuminate\Support\Facades\Broadcast;

// Private user channel - only the user themselves
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private game channel - players and observers
Broadcast::channel('game.{gameId}', function ($user, $gameId) {
    $game = Game::find($gameId);
    if (! $game) {
        return false;
    }

    return $user->id === $game->black_player_id
        || $user->id === $game->white_player_id
        || $game->observers()->where('user_id', $user->id)->exists();
});

// Presence room channel
Broadcast::channel('room.{roomId}', function ($user, $roomId) {
    $room = GameRoom::find($roomId);
    if (! $room) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->getDisplayName(), 'rank' => $user->rank];
});

// Presence chat channel
Broadcast::channel('chat.{chatRoomId}', function ($user, $chatRoomId) {
    $chatRoom = ChatRoom::find($chatRoomId);
    if (! $chatRoom) {
        return false;
    }

    if ($chatRoom->type === 'global') {
        return ['id' => $user->id, 'name' => $user->getDisplayName()];
    }

    if ($chatRoom->type === 'game') {
        $game = Game::find($chatRoom->reference_id);
        if (! $game) {
            return false;
        }
        $isParticipant = $user->id === $game->black_player_id
            || $user->id === $game->white_player_id
            || $game->observers()->where('user_id', $user->id)->exists();

        return $isParticipant ? ['id' => $user->id, 'name' => $user->getDisplayName()] : false;
    }

    if ($chatRoom->type === 'group') {
        $group = Group::find($chatRoom->reference_id);

        return $group?->isMember($user) ? ['id' => $user->id, 'name' => $user->getDisplayName()] : false;
    }

    return false;
});

// Lobby public channel - all authenticated users
Broadcast::channel('lobby', function ($user) {
    return ['id' => $user->id, 'name' => $user->getDisplayName(), 'rank' => $user->rank];
});

// Presence channel for real-time online status tracking
// Echo.join('online') → channel name becomes 'presence-online'
Broadcast::channel('online', function ($user) {
    return ['id' => $user->id, 'name' => $user->getDisplayName(), 'rank' => $user->rank];
});
