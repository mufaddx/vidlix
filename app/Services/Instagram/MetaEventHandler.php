<?php

namespace App\Services\Instagram;

use App\Jobs\SyncInstagramProfile;
use App\Models\InstagramAccount;
use Illuminate\Support\Facades\Log;

/**
 * Meta webhook fan-out.
 *
 * A webhook tells us something changed; it is not a source of metrics. The only
 * action taken here is to queue a Graph API sync for the affected account, so
 * every number the UI shows still comes from an authoritative API read.
 */
class MetaEventHandler
{
    /**
     * @return array{status: string, detail: string}
     */
    public function handle(array $payload): array
    {
        if (($payload['object'] ?? null) !== 'instagram') {
            return ['status' => 'ignored', 'detail' => 'Not an Instagram subscription event.'];
        }

        $queued = 0;
        $unknown = 0;

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            $igUserId = (string) ($entry['id'] ?? '');
            if ($igUserId === '') {
                continue;
            }

            $account = InstagramAccount::query()->where('ig_user_id', $igUserId)->first();
            if (! $account) {
                $unknown++;

                continue;
            }

            SyncInstagramProfile::dispatch((int) $account->creator_profile_id);
            $queued++;
        }

        if ($unknown > 0) {
            Log::info('meta.webhook.unknown_accounts', ['count' => $unknown]);
        }

        return [
            'status' => $queued > 0 ? 'sync_queued' : 'no_linked_accounts',
            'detail' => $queued.' linked account(s) queued for a Graph API sync. No metric came from the webhook body.',
        ];
    }
}
