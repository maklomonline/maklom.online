<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotRequest extends Model
{
    protected $fillable = [
        'requester_id',
        'username',
        'display_name',
        'rank',
        'password_hash',
        'bio',
        'status',
        'reviewed_by',
        'rejection_reason',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
