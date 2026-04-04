<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['admin_id', 'action', 'target_type', 'target_id', 'notes', 'ip_address'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
