<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public const TYPES = ['creator', 'editor', 'brand'];

    /** A creator may pick at most this many. Brands search against them. */
    public const MAX_PER_CREATOR = 3;

    protected $fillable = ['type', 'name', 'slug', 'status', 'proposed_by_user_id', 'sort_order'];

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /** Publicly listable. A pending proposal still works for whoever proposed it. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CategoryAssignment::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
