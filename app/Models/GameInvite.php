<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameInvite extends Model
{
    protected $fillable = ['room_id', 'inviter_id', 'invitee_id', 'status', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(GameRoom::class, 'room_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast() || $this->status === 'expired';
    }
}
