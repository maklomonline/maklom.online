<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Hash;

class GameRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'creator_id', 'board_size', 'clock_type',
        'main_time', 'byoyomi_periods', 'byoyomi_seconds', 'fischer_increment',
        'komi', 'handicap', 'is_private', 'password', 'status',
        'group_id', 'max_observers',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'komi' => 'float',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class, 'room_id');
    }

    public function currentGame(): HasOne
    {
        return $this->hasOne(Game::class, 'room_id')->latest();
    }

    public function invites(): HasMany
    {
        return $this->hasMany(GameInvite::class, 'room_id');
    }

    public function isPasswordProtected(): bool
    {
        return $this->is_private && $this->password !== null;
    }

    public function checkPassword(string $password): bool
    {
        return Hash::check($password, $this->password);
    }

    public function getClockDescription(): string
    {
        $main = gmdate('i:s', $this->main_time);
        if ($this->clock_type === 'byoyomi') {
            return "{$main} + {$this->byoyomi_periods}×{$this->byoyomi_seconds}วิ เบียวโยมิ";
        }

        return "{$main} + {$this->fischer_increment}วิ ฟิชเชอร์";
    }
}
