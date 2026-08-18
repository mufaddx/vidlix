<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorManagerRelationship extends Model
{
    protected $fillable = [
        'creator_user_id', 'manager_user_id', 'status', 'permissions', 'accepted_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }
}
