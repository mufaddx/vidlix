<?php

namespace App\Services\AutoDm;

use App\Models\AutodmAutomation;
use App\Models\AutodmAutomationVersion;
use App\Models\AutodmEvent;
use App\Models\AutodmRun;
use App\Models\InstagramAccount;
use App\Models\InstagramMedium;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What happens when somebody comments.
 *
 * The order is deliberate and each step can stop the whole thing:
 *
 *   verify → record → resolve account → match automations → check capability
 *   → check the window → record a run → send
 *
 * Two properties matter more than the rest.
 *
 * Idempotency: Instagram retries deliveries, and a retry must not produce a
 * second reply to the same person. `autodm_events.provider_event_id` is unique
 * and so is the run per automation-event-action, so duplicates collide at the
 * database rather than at a check somebody can race.
 *
 * Honesty: an action the provider does not permit is recorded as skipped with a
 * reason, never as sent and never as failed. Calling it a failure would invite
 * a retry that can never succeed; calling it sent would be a lie in the log the
 * owner reads to find out what happened.
 */
class AutomationEngine
{
    public function __construct(
        private Capabilities $capabilities,
        private KeywordMatcher $matcher,
        private AutoDmSender $sender,
        private AuditLogger $audit,
    ) {}

    /**
     * Handle one comment webhook.
     *
     * @param  array<string, mixed>  $payload  straight off the wire, so nothing in it is assumed
     * @return array{status: string, runs: int, detail: string}
     */
    public function handleComment(array $payload): array
    {
        $eventId = trim((string) ($payload['event_id'] ?? ''));

        if ($eventId === '') {
            return ['status' => 'ignored', 'runs' => 0, 'detail' => 'The delivery carried no event id.'];
        }

        // The unique index is the idempotency guarantee, not this lookup — but
        // returning early keeps a retry cheap instead of merely safe.
        $existing = AutodmEvent::query()->where('provider_event_id', $eventId)->first();

        if ($existing !== null) {
            return ['status' => 'duplicate', 'runs' => 0, 'detail' => 'Already handled.'];
        }

        $account = $this->resolveAccount($payload['ig_user_id'] ?? null);

        try {
            $event = AutodmEvent::query()->create([
                'provider_event_id' => $eventId,
                'instagram_account_id' => $account?->id,
                'media_id' => $payload['media_id'] ?? null,
                'comment_id' => $payload['comment_id'] ?? null,
                'commenter_id' => $payload['commenter_id'] ?? null,
                'comment_text' => $payload['text'] ?? null,
                'status' => 'received',
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two deliveries arrived at once. The index settled it; nothing to do.
            return ['status' => 'duplicate', 'runs' => 0, 'detail' => 'Already handled.'];
        }

        if ($account === null) {
            $event->update(['status' => 'ignored']);

            return ['status' => 'ignored', 'runs' => 0, 'detail' => 'No connected account matches this delivery.'];
        }

        $automations = $this->matchingAutomations($account, $payload);

        if ($automations === []) {
            $event->update(['status' => 'unmatched']);

            return ['status' => 'unmatched', 'runs' => 0, 'detail' => 'No automation matched.'];
        }

        $event->update(['status' => 'matched']);

        $commentedAt = isset($payload['commented_at'])
            ? Carbon::parse($payload['commented_at'])
            : now();

        $runs = 0;

        foreach ($automations as $automation) {
            $runs += $this->execute($automation, $account, $event, $commentedAt, $payload);
        }

        return ['status' => 'matched', 'runs' => $runs, 'detail' => 'Handled.'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<AutodmAutomation>
     */
    private function matchingAutomations(InstagramAccount $account, array $payload): array
    {
        $mediaId = $payload['media_id'] ?? null;

        $candidates = AutodmAutomation::query()
            ->where('instagram_account_id', $account->id)
            ->where('status', AutodmAutomation::ACTIVE)
            ->whereNotNull('active_version_id')
            ->get();

        $matched = [];

        foreach ($candidates as $automation) {
            // An automation bound to one post must not fire on another. A null
            // media binding means the whole account, which is the broader and
            // therefore more dangerous default — so it is never inferred.
            if ($automation->instagram_media_id !== null) {
                $bound = InstagramMedium::query()->find($automation->instagram_media_id);

                if ($bound === null || $bound->media_id !== $mediaId) {
                    continue;
                }
            }

            $version = $automation->activeVersion();

            if ($version === null) {
                continue;
            }

            if ($this->matcher->matches($version, (string) ($payload['text'] ?? ''))) {
                $matched[] = $automation;
            }
        }

        return $matched;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return int how many runs were recorded
     */
    private function execute(
        AutodmAutomation $automation,
        InstagramAccount $account,
        AutodmEvent $event,
        \DateTimeInterface $commentedAt,
        array $payload,
    ): int {
        $version = $automation->activeVersion();

        if ($version === null) {
            return 0;
        }

        $recorded = 0;

        foreach ($this->plannedActions($version) as $action) {
            $run = $this->startRun($automation, $version, $event, $action);

            if ($run === null) {
                // Already run for this comment. The unique index caught it.
                continue;
            }

            $recorded++;

            $capability = $this->capabilities->check($account, $action);

            if (! $capability['allowed']) {
                $this->skip($run, (string) $capability['reason_code'], (string) $capability['reason']);

                continue;
            }

            if ($action === AutodmRun::PRIVATE_REPLY
                && ! $this->capabilities->withinPrivateReplyWindow($commentedAt)) {
                $this->skip(
                    $run,
                    'outside_messaging_window',
                    __('Instagram only allows a private reply shortly after the comment, and that window has closed.'),
                );

                continue;
            }

            $this->send($run, $account, $version, $action, $payload);
        }

        $automation->update(['last_run_at' => now()]);

        return $recorded;
    }

    /** @return list<string> */
    private function plannedActions(AutodmAutomationVersion $version): array
    {
        $actions = [];

        if ($version->public_reply_enabled) {
            $actions[] = AutodmRun::PUBLIC_REPLY;
        }

        if ($version->private_reply_enabled) {
            $actions[] = AutodmRun::PRIVATE_REPLY;
        }

        return $actions;
    }

    /**
     * Claim this action for this comment, or return null if somebody already has.
     *
     * The insert relies on the unique index rather than on a preceding check:
     * two deliveries can pass a check in the same instant, and only the index
     * settles which one gets to send.
     */
    private function startRun(
        AutodmAutomation $automation,
        AutodmAutomationVersion $version,
        AutodmEvent $event,
        string $action,
    ): ?AutodmRun {
        try {
            return DB::transaction(fn () => AutodmRun::query()->create([
                'autodm_automation_id' => $automation->id,
                'autodm_automation_version_id' => $version->id,
                'autodm_event_id' => $event->id,
                'action' => $action,
                'status' => AutodmRun::MATCHED,
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /** @param array<string, mixed> $payload */
    private function send(
        AutodmRun $run,
        InstagramAccount $account,
        AutodmAutomationVersion $version,
        string $action,
        array $payload,
    ): void {
        $run->update([
            'status' => AutodmRun::QUEUED,
            'attempts' => $run->attempts + 1,
        ]);

        $result = $this->sender->send($account, $version, $action, $payload);

        $run->update([
            'status' => $result['status'],
            'reason_code' => $result['reason_code'],
            'detail' => $result['detail'],
            'provider_response_id' => $result['provider_response_id'],
            // Only a transient failure earns another go. A refusal on policy or
            // capability grounds will refuse again just as firmly.
            'next_attempt_at' => $result['status'] === AutodmRun::RETRY_SCHEDULED
                ? now()->addMinutes(5 * max(1, $run->attempts))
                : null,
        ]);

        $this->audit->record('autodm.run', $run, [
            'action' => $action,
            'status' => $result['status'],
            'reason_code' => $result['reason_code'],
        ]);
    }

    private function skip(AutodmRun $run, string $code, string $detail): void
    {
        $run->update([
            'status' => AutodmRun::SKIPPED,
            'reason_code' => $code,
            'detail' => $detail,
            'next_attempt_at' => null,
        ]);

        $this->audit->record('autodm.skipped', $run, ['reason_code' => $code]);
    }

    private function resolveAccount(?string $igUserId): ?InstagramAccount
    {
        if ($igUserId === null || $igUserId === '') {
            return null;
        }

        return InstagramAccount::query()
            ->where('ig_user_id', $igUserId)
            ->where('status', 'connected')
            ->first();
    }
}
