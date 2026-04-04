<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatRoom extends Model
{
    protected $fillable = ['type', 'reference_id', 'name'];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    public static function forGlobal(): self
    {
        return static::firstOrCreate(['type' => 'global'], ['name' => 'ห้องแชทสาธารณะ']);
    }

    public static function forGame(int $gameId): self
    {
        return static::firstOrCreate(['type' => 'game', 'reference_id' => $gameId]);
    }

    public static function forGroup(int $groupId): self
    {
        return static::firstOrCreate(['type' => 'group', 'reference_id' => $groupId]);
    }
}
