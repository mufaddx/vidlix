<?php

namespace App\Services\Instagram;

use App\Jobs\SyncInstagramProfile;
use App\Models\InstagramAccount;
use App\Services\AutoDm\AutomationEngine;
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
    public function __construct(private AutomationEngine $autodm) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, detail: string}
     */
    public function handle(array $payload): array
    {
        if (($payload['object'] ?? null) !== 'instagram') {
            return ['status' => 'ignored', 'detail' => 'Not an Instagram subscription event.'];
        }

        $queued = 0;
        $unknown = 0;
        $comments = 0;

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

            // A comment is the one change that does something beyond queuing a
            // sync. It is handled here rather than on its own endpoint because
            // Meta delivers everything to one URL, and that URL is already the
            // one whose signature we verify.
            $comments += $this->handleComments($igUserId, (array) ($entry['changes'] ?? []));
        }

        if ($unknown > 0) {
            Log::info('meta.webhook.unknown_accounts', ['count' => $unknown]);
        }

        return [
            'status' => $queued > 0 ? 'sync_queued' : 'no_linked_accounts',
            'detail' => $queued.' linked account(s) queued for a Graph API sync, '
                .$comments.' comment event(s) handled. No metric came from the webhook body.',
        ];
    }

    /**
     * Hand each comment change to the AutoDM engine.
     *
     * The engine is idempotent on the event id, so a Meta retry — which does
     * happen — cannot produce a second reply to the same person. Nothing is
     * deduplicated here, because doing it in two places means neither is the
     * one that can be trusted.
     *
     * @param  list<mixed>  $changes
     */
    private function handleComments(string $igUserId, array $changes): int
    {
        $handled = 0;

        foreach ($changes as $change) {
            if (! is_array($change) || ($change['field'] ?? null) !== 'comments') {
                continue;
            }

            $value = (array) ($change['value'] ?? []);
            $commentId = (string) ($value['id'] ?? '');

            if ($commentId === '') {
                continue;
            }

            $this->autodm->handleComment([
                // Meta sends no event id, so the comment id is the natural key:
                // one comment can only ever be answered once.
                'event_id' => 'ig_comment_'.$commentId,
                'ig_user_id' => $igUserId,
                'media_id' => isset($value['media']['id']) ? (string) $value['media']['id'] : null,
                'comment_id' => $commentId,
                'commenter_id' => isset($value['from']['id']) ? (string) $value['from']['id'] : null,
                'text' => isset($value['text']) ? (string) $value['text'] : null,
                'commented_at' => isset($value['timestamp']) ? (string) $value['timestamp'] : null,
            ]);

            $handled++;
        }

        return $handled;
    }
}
