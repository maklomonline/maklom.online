<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStat extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'games_played', 'games_won', 'games_lost', 'games_drawn',
        'win_streak', 'best_win_streak', 'total_moves',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
