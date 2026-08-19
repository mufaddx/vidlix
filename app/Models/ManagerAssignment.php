<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manager appointed over one side of somebody's account.
 *
 * Authorisation always reads this table server-side. A client-supplied owner id
 * is never trusted — see WorkspaceContext.
 */
class ManagerAssignment extends Model
{
    public const SCOPES = ['creator', 'brand', 'editor'];

    protected $fillable = [
        'owner_user_id', 'manager_user_id', 'scope', 'status', 'source',
        'assigned_by_user_id', 'permissions', 'accepted_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** True when Vidlix provided this manager rather than the account holder. */
    public function isCompanyProvided(): bool
    {
        return $this->source === 'company';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
