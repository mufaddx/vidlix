<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignApplication extends Model
{
    protected $fillable = [
        'campaign_id', 'creator_profile_id', 'status', 'proposed_fee_minor',
        'message', 'availability', 'analytics_snapshot',
    ];

    protected function casts(): array
    {
        return ['analytics_snapshot' => 'array'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(CreatorProfile::class, 'creator_profile_id');
    }
}
