<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = ['chat_room_id', 'user_id', 'body', 'is_deleted'];

    protected function casts(): array
    {
        return ['is_deleted' => 'boolean'];
    }

    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisible($query)
    {
        return $query->where(function ($q) {
            $q->where('is_deleted', false)->orWhereNull('is_deleted');
        });
    }
}
