<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

/**
 * Who may manage a campaign.
 *
 * Managing belongs to the brand that owns it. Viewing a published campaign is
 * deliberately not modelled here: a published campaign is public, and routing
 * that through a policy would imply a restriction that does not exist.
 */
class CampaignPolicy
{
    public function manage(User $user, Campaign $campaign): bool
    {
        $brandProfileId = $user->brandProfile?->id;

        return $brandProfileId !== null
            && $campaign->brand_profile_id === $brandProfileId;
    }
}
