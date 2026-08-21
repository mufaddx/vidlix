<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $published_at
 * @property Carbon|null $closed_at
 * @property BrandProfile|null $brandProfile
 */
class Campaign extends Model
{
    protected $fillable = [
        'brand_profile_id', 'name', 'slug', 'status', 'objective', 'brief',
        'budget_minor', 'application_deadline', 'delivery_deadline',
        'platform', 'creator_count', 'location', 'language', 'usage_rights',
        'campaign_type', 'deliverables', 'work_mode',
        'min_followers', 'max_followers', 'min_engagement_bps',
        'revision_terms', 'payment_terms', 'additional_requirements',
    ];

    /*
     | published_at and closed_at are deliberately absent from $fillable. They
     | are stamped by CampaignLifecycle when a move actually happens, and a
     | mass-assignable date is a date somebody can post.
     */
    protected function casts(): array
    {
        return [
            'application_deadline' => 'datetime',
            'delivery_deadline' => 'datetime',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'deliverables' => 'array',
        ];
    }

    /** @return BelongsTo<BrandProfile, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(BrandProfile::class, 'brand_profile_id');
    }

    /** Alias, because "the brand's profile" reads better at some call sites. */
    public function brandProfile(): BelongsTo
    {
        return $this->brand();
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
