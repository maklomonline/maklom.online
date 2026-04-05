<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id', 'black_player_id', 'white_player_id',
        'board_size', 'komi', 'handicap', 'clock_type',
        'main_time', 'byoyomi_periods', 'byoyomi_seconds', 'fischer_increment',
        'black_time_left', 'white_time_left', 'black_periods_left', 'white_periods_left',
        'current_color', 'move_number', 'board_state',
        'captures_black', 'captures_white', 'ko_point', 'consecutive_passes',
        'status', 'result', 'winner_id', 'end_reason', 'started_at', 'last_move_at', 'finished_at',
        'dead_stones', 'score_confirmed_black', 'score_confirmed_white',
    ];

    protected function casts(): array
    {
        return [
            'board_state' => 'array',
            'dead_stones' => 'array',
            'komi' => 'float',
            'score_confirmed_black' => 'boolean',
            'score_confirmed_white' => 'boolean',
            'started_at' => 'datetime',
            'last_move_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(GameRoom::class, 'room_id');
    }

    public function blackPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'black_player_id');
    }

    public function whitePlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'white_player_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(GameMove::class)->orderBy('move_number');
    }

    public function observers(): HasMany
    {
        return $this->hasMany(GameObserver::class);
    }

    public function isPlayerTurn(User $user): bool
    {
        if ($this->current_color === 'black') {
            return $this->black_player_id === $user->id;
        }

        return $this->white_player_id === $user->id;
    }

    public function getOpponent(User $user): ?User
    {
        if ($this->black_player_id === $user->id) {
            return $this->whitePlayer;
        }
        if ($this->white_player_id === $user->id) {
            return $this->blackPlayer;
        }

        return null;
    }

    public function getPlayerColor(User $user): ?string
    {
        if ($this->black_player_id === $user->id) {
            return 'black';
        }
        if ($this->white_player_id === $user->id) {
            return 'white';
        }

        return null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function chatRoom()
    {
        return ChatRoom::where('type', 'game')->where('reference_id', $this->id)->first();
    }
}
