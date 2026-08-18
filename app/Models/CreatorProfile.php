<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CreatorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'username',
        'display_name',
        'bio',
        'niches',
        'visibility',
        'onboarding_step',
        'profile_completion',
        'instagram_connection_status',
    ];

    protected function casts(): array
    {
        return ['niches' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publicPage(): HasOne
    {
        return $this->hasOne(CreatorPublicPage::class);
    }

    public function socialLinks(): HasMany
    {
        return $this->hasMany(CreatorSocialLink::class)->orderBy('sort_order');
    }

    public function instagramAccount(): HasOne
    {
        return $this->hasOne(InstagramAccount::class);
    }

    public function isPublished(): bool
    {
        return $this->visibility === 'public'
            && $this->publicPage?->status === 'published'
            && $this->publicPage?->published_payload;
    }
}
