<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'display_name', 'email', 'password',
        'avatar', 'rank', 'rank_points', 'bio', 'is_admin',
        'is_banned', 'ban_reason', 'banned_until', 'last_seen_at', 'locale',
        'is_bot', 'bot_level', 'bot_api_token', 'bot_online', 'bot_last_heartbeat',
        'confirm_move',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'   => 'datetime',
            'password'            => 'hashed',
            'is_admin'            => 'boolean',
            'is_banned'           => 'boolean',
            'is_bot'              => 'boolean',
            'confirm_move'        => 'boolean',
            'bot_online'          => 'boolean',
            'banned_until'        => 'datetime',
            'last_seen_at'        => 'datetime',
            'bot_last_heartbeat'  => 'datetime',
        ];
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOnline($query)
    {
        return $query->where('last_seen_at', '>=', now()->subMinutes(5));
    }

    public function scopeNotBanned($query)
    {
        return $query->where('is_banned', false);
    }

    public function scopeAdmin($query)
    {
        return $query->where('is_admin', true);
    }

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function stats(): HasOne
    {
        return $this->hasOne(UserStat::class);
    }

    public function createdRooms(): HasMany
    {
        return $this->hasMany(GameRoom::class, 'creator_id');
    }

    public function gamesAsBlack(): HasMany
    {
        return $this->hasMany(Game::class, 'black_player_id');
    }

    public function gamesAsWhite(): HasMany
    {
        return $this->hasMany(Game::class, 'white_player_id');
    }

    public function sentFriendships(): HasMany
    {
        return $this->hasMany(Friendship::class, 'requester_id');
    }

    public function receivedFriendships(): HasMany
    {
        return $this->hasMany(Friendship::class, 'addressee_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot('role', 'joined_at');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function sentInvites(): HasMany
    {
        return $this->hasMany(GameInvite::class, 'inviter_id');
    }

    public function receivedInvites(): HasMany
    {
        return $this->hasMany(GameInvite::class, 'invitee_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    // ─── Helper Methods ────────────────────────────────────────────────────────

    public function getDisplayName(): string
    {
        return $this->display_name ?? $this->name;
    }

    public function isFriendWith(User $user): bool
    {
        return Friendship::where('status', 'accepted')
            ->where(function ($q) use ($user) {
                $q->where(function ($inner) use ($user) {
                    $inner->where('requester_id', $this->id)->where('addressee_id', $user->id);
                })->orWhere(function ($inner) use ($user) {
                    $inner->where('requester_id', $user->id)->where('addressee_id', $this->id);
                });
            })->exists();
    }

    public function hasPendingFriendRequestFrom(User $user): bool
    {
        return Friendship::where('requester_id', $user->id)
            ->where('addressee_id', $this->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function isBlockedBy(User $user): bool
    {
        return Friendship::where('requester_id', $user->id)
            ->where('addressee_id', $this->id)
            ->where('status', 'blocked')
            ->exists();
    }

    public function isAdminOf(Group $group): bool
    {
        return $group->members()
            ->where('user_id', $this->id)
            ->wherePivotIn('role', ['owner', 'moderator'])
            ->exists();
    }

    public function isCurrentlyBanned(): bool
    {
        if (! $this->is_banned) {
            return false;
        }
        if ($this->banned_until === null) {
            return true;
        }

        return $this->banned_until->isFuture();
    }

    /**
     * ตรวจสอบว่าบอทพร้อมรับเกม
     *
     * - Server-side bots (ไม่มี bot_api_token): KataGo รันบนเซิร์ฟเวอร์ → พร้อมเสมอ
     * - External bots (มี bot_api_token): ต้องการ heartbeat ล่าสุดไม่เกิน 2 นาที
     */
    public function isBotOnline(): bool
    {
        if (! $this->is_bot) {
            return false;
        }

        // Server-side KataGo bots — ไม่ต้องการ heartbeat เพราะรันบนเซิร์ฟเวอร์โดยตรง
        if (! $this->bot_api_token) {
            return true;
        }

        // External bot client — ต้องการ heartbeat ล่าสุด
        if (! $this->bot_online) {
            return false;
        }

        return $this->bot_last_heartbeat !== null
            && $this->bot_last_heartbeat->gt(now()->subMinutes(2));
    }

    public function getAvatarUrl(): string
    {
        if ($this->avatar) {
            return asset('storage/'.$this->avatar);
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->getDisplayName()).'&background=4f46e5&color=fff&size=128';
    }
}
