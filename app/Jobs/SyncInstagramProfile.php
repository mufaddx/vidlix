<?php

namespace App\Jobs;

use App\Contracts\InstagramProviderInterface;
use App\Models\CreatorProfile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pulls whatever the Meta Graph API is willing to return for a linked account.
 *
 * The provider writes the insights it actually received; this job only records
 * the resulting connection state, and records a failure as a failure.
 */
class SyncInstagramProfile implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $creatorProfileId) {}

    public function handle(InstagramProviderInterface $instagram): void
    {
        $profile = CreatorProfile::query()->find($this->creatorProfileId);
        if (! $profile) {
            return;
        }

        $result = $instagram->syncPermittedData($profile);

        $profile->update(['instagram_connection_status' => match ($result['status']) {
            'synced' => 'connected',
            'reauth_required' => 'reauth_required',
            'not_connected', 'provider_not_configured' => 'disconnected',
            default => 'sync_failed',
        }]);

        if ($result['status'] !== 'synced') {
            $profile->instagramAccount?->update(['last_error' => $result['detail']]);
        }
    }
}
