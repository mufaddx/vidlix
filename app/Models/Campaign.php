<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'brand_profile_id', 'name', 'slug', 'status', 'objective', 'brief',
        'budget_minor', 'application_deadline', 'delivery_deadline',
        'platform', 'creator_count', 'location', 'language', 'usage_rights',
    ];

    protected function casts(): array
    {
        return [
            'application_deadline' => 'datetime',
            'delivery_deadline' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(BrandProfile::class, 'brand_profile_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CampaignApplication::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
