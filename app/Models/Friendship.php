<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    protected $fillable = ['requester_id', 'addressee_id', 'status', 'blocked_by'];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    public function blockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }

    public function getOtherUser(User $user): User
    {
        return $this->requester_id === $user->id ? $this->addressee : $this->requester;
    }
}
