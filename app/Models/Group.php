<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'avatar', 'owner_id', 'is_public', 'max_members'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('role', 'joined_at');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(GameRoom::class);
    }

    public function chatRoom()
    {
        return ChatRoom::where('type', 'group')->where('reference_id', $this->id)->first();
    }

    public function isMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function getMemberCount(): int
    {
        return $this->members()->count();
    }

    public function getAvatarUrl(): string
    {
        if ($this->avatar) {
            return asset('storage/'.$this->avatar);
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=7c3aed&color=fff&size=128';
    }
}
