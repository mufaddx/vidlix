<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorSocialLink extends Model
{
    protected $fillable = [
        'creator_profile_id', 'social_platform_id', 'input_mode',
        'input_value', 'resolved_url', 'is_visible', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean'];
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(SocialPlatform::class, 'social_platform_id');
    }
}
