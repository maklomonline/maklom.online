<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Challenge extends Model
{
    protected $fillable = [
        'challenger_id',
        'challenged_id',
        'board_size',
        'clock_type',
        'main_time',
        'byoyomi_periods',
        'byoyomi_seconds',
        'fischer_increment',
        'handicap',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    public function challenged(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenged_id');
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->expires_at && $this->expires_at->isPast());
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
