<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameMove extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'game_id', 'move_number', 'color', 'coordinate',
        'captured_stones', 'time_spent', 'time_left_after', 'periods_left_after',
    ];

    protected function casts(): array
    {
        return [
            'captured_stones' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
