<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagerInvitation extends Model
{
    protected $fillable = [
        'owner_user_id', 'scope', 'email', 'mobile', 'name', 'token', 'source',
        'invited_by_user_id', 'permissions', 'status', 'expires_at', 'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected $hidden = ['token'];

    public function isOpen(): bool
    {
        return $this->status === 'invited'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isCompanyProvided(): bool
    {
        return $this->source === 'company';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
