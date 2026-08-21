<?php

namespace App\Models;

use App\Models\Concerns\RegistersUsername;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property User|null $user
 */
class CreatorProfile extends Model
{
    use RegistersUsername;

    public function registryProfileType(): string
    {
        return 'creator';
    }

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
        'follower_count',
        'follower_count_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'niches' => 'array',
            'follower_count_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<CreatorPublicPage, $this> */
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
        // The second check needs no nullsafe: reaching it means the first one
        // matched a real status, so the page is there.
        return $this->visibility === 'public'
            && $this->publicPage?->status === 'published'
            && (bool) $this->publicPage->published_payload;
    }
}
