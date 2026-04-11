<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameAnnotation extends Model
{
    protected $fillable = ['game_id', 'user_id', 'title', 'payload', 'positions_count', 'last_position_key'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
