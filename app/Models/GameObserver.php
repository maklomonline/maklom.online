<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameObserver extends Model
{
    public $timestamps = false;

    protected $fillable = ['game_id', 'user_id'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
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
