<?php

namespace App\Services;

use App\Events\FriendRequestAccepted;
use App\Events\FriendRequestSent;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class FriendService
{
    public function sendRequest(User $from, User $to): Friendship
    {
        // Check if relationship already exists in any direction
        $existing = Friendship::where(function ($q) use ($from, $to) {
            $q->where('requester_id', $from->id)->where('addressee_id', $to->id);
        })->orWhere(function ($q) use ($from, $to) {
            $q->where('requester_id', $to->id)->where('addressee_id', $from->id);
        })->first();

        if ($existing) {
            return $existing;
        }

        $friendship = Friendship::create([
            'requester_id' => $from->id,
            'addressee_id' => $to->id,
            'status' => 'pending',
        ]);

        broadcast(new FriendRequestSent($friendship))->toOthers();

        return $friendship;
    }

    public function acceptRequest(User $addressee, int $friendshipId): Friendship
    {
        $friendship = Friendship::where('id', $friendshipId)
            ->where('addressee_id', $addressee->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $friendship->update(['status' => 'accepted']);

        broadcast(new FriendRequestAccepted($friendship))->toOthers();

        return $friendship;
    }

    public function declineRequest(User $addressee, int $friendshipId): void
    {
        Friendship::where('id', $friendshipId)
            ->where('addressee_id', $addressee->id)
            ->where('status', 'pending')
            ->firstOrFail()
            ->delete();
    }

    public function block(User $blocker, User $blocked): Friendship
    {
        $friendship = Friendship::where(function ($q) use ($blocker, $blocked) {
            $q->where('requester_id', $blocker->id)->where('addressee_id', $blocked->id);
        })->orWhere(function ($q) use ($blocker, $blocked) {
            $q->where('requester_id', $blocked->id)->where('addressee_id', $blocker->id);
        })->first();

        if ($friendship) {
            $friendship->update(['status' => 'blocked', 'blocked_by' => $blocker->id]);
        } else {
            $friendship = Friendship::create([
                'requester_id' => $blocker->id,
                'addressee_id' => $blocked->id,
                'status' => 'blocked',
                'blocked_by' => $blocker->id,
            ]);
        }

        return $friendship;
    }

    public function unblock(User $blocker, User $blocked): void
    {
        Friendship::where('status', 'blocked')
            ->where('blocked_by', $blocker->id)
            ->where(function ($q) use ($blocker, $blocked) {
                $q->where('requester_id', $blocker->id)->where('addressee_id', $blocked->id);
            })->orWhere(function ($q) use ($blocker, $blocked) {
                $q->where('requester_id', $blocked->id)->where('addressee_id', $blocker->id)
                    ->where('blocked_by', $blocker->id);
            })->delete();
    }

    public function removeFriend(User $user, User $friend): void
    {
        Friendship::where('status', 'accepted')
            ->where(function ($q) use ($user, $friend) {
                $q->where('requester_id', $user->id)->where('addressee_id', $friend->id);
            })->orWhere(function ($q) use ($user, $friend) {
                $q->where('requester_id', $friend->id)->where('addressee_id', $user->id)
                    ->where('status', 'accepted');
            })->delete();
    }

    public function getFriends(User $user): Collection
    {
        $friendships = Friendship::with(['requester', 'addressee'])
            ->where('status', 'accepted')
            ->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)->orWhere('addressee_id', $user->id);
            })->get();

        return $friendships->map(fn ($f) => $f->getOtherUser($user))->values();
    }

    public function getPendingRequests(User $user): Collection
    {
        return Friendship::with('requester')
            ->where('addressee_id', $user->id)
            ->where('status', 'pending')
            ->get();
    }

    public function areBlocked(User $a, User $b): bool
    {
        return Friendship::where('status', 'blocked')
            ->where(function ($q) use ($a, $b) {
                $q->where('requester_id', $a->id)->where('addressee_id', $b->id);
            })->orWhere(function ($q) use ($a, $b) {
                $q->where('requester_id', $b->id)->where('addressee_id', $a->id);
            })->exists();
    }
}
