<?php

namespace App\Services\AutoDm;

use App\Models\AutodmAutomation;
use App\Models\AutodmAutomationVersion;
use App\Models\AutodmRun;
use App\Models\InstagramAccount;
use App\Models\InstagramMedium;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Building and activating an automation.
 *
 * Activation is the only interesting method. Everything is revalidated at that
 * moment — permissions, media ownership, provider capability — rather than
 * trusted from when the automation was drafted, because all three can change
 * between drafting and switching on, and the failure would otherwise surface as
 * a reply that never arrives.
 *
 * Terms are frozen into a version at activation. A later edit is a new version,
 * so a run from months ago can still say exactly which rules produced it.
 */
class AutomationBuilder
{
    public const MAX_KEYWORDS = 40;

    public function __construct(
        private Capabilities $capabilities,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $user, InstagramAccount $account, array $input): AutodmAutomation
    {
        return DB::transaction(function () use ($user, $account, $input) {
            $automation = AutodmAutomation::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'instagram_account_id' => $account->id,
                'instagram_media_id' => $this->resolveMediaId($account, $input['instagram_media_id'] ?? null),
                'name' => $this->name($input['name'] ?? ''),
                'status' => AutodmAutomation::DRAFT,
            ]);

            $this->saveDraft($automation, $input);

            $this->audit->record('autodm.created', $automation, [], $user->id);

            return $automation->fresh();
        });
    }

    /**
     * Write the terms as a new version, without activating it.
     *
     * @param  array<string, mixed>  $input
     */
    public function saveDraft(AutodmAutomation $automation, array $input): AutodmAutomationVersion
    {
        $trigger = $input['trigger_type'] ?? AutodmAutomationVersion::KEYWORDS;

        if (! in_array($trigger, [AutodmAutomationVersion::ANY_COMMENT, AutodmAutomationVersion::KEYWORDS], true)) {
            throw ValidationException::withMessages(['trigger_type' => __('Choose a trigger.')]);
        }

        $keywords = $this->keywords($input['keywords'] ?? '');

        if ($trigger === AutodmAutomationVersion::KEYWORDS && $keywords === []) {
            // A keyword trigger with no keywords matches nothing, which reads
            // as a broken automation rather than an empty one.
            throw ValidationException::withMessages([
                'keywords' => __('Add at least one word to watch for, or switch to replying to any comment.'),
            ]);
        }

        $publicEnabled = (bool) ($input['public_reply_enabled'] ?? false);
        $privateEnabled = (bool) ($input['private_reply_enabled'] ?? false);

        if (! $publicEnabled && ! $privateEnabled) {
            throw ValidationException::withMessages([
                'action' => __('Choose what should happen when it matches.'),
            ]);
        }

        $publicText = trim((string) ($input['public_reply_text'] ?? ''));
        $privateText = trim((string) ($input['private_reply_text'] ?? ''));

        if ($publicEnabled && $publicText === '') {
            throw ValidationException::withMessages(['public_reply_text' => __('Write the public reply.')]);
        }

        if ($privateEnabled && $privateText === '') {
            throw ValidationException::withMessages(['private_reply_text' => __('Write the private reply.')]);
        }

        $url = trim((string) ($input['private_reply_url'] ?? ''));

        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages(['private_reply_url' => __('That is not a valid link.')]);
        }

        $next = ((int) AutodmAutomationVersion::query()
            ->where('autodm_automation_id', $automation->id)
            ->max('version_number')) + 1;

        return AutodmAutomationVersion::query()->create([
            'autodm_automation_id' => $automation->id,
            'version_number' => $next,
            'trigger_type' => $trigger,
            'keywords' => $keywords,
            'whole_word' => (bool) ($input['whole_word'] ?? false),
            'public_reply_enabled' => $publicEnabled,
            'public_reply_text' => $publicText ?: null,
            'private_reply_enabled' => $privateEnabled,
            'private_reply_text' => $privateText ?: null,
            'private_reply_url' => $url ?: null,
        ]);
    }

    /**
     * Switch it on.
     *
     * Everything is checked again here rather than relying on what was true
     * when the automation was drafted. A permission can be revoked, a post can
     * be deleted, and app review can still be pending — and each of those turns
     * an automation that looks armed into one that silently never fires.
     */
    public function activate(AutodmAutomation $automation, User $actor): AutodmAutomation
    {
        $version = $automation->draftVersion();

        if ($version === null) {
            throw ValidationException::withMessages([
                'automation' => __('There is nothing to activate yet.'),
            ]);
        }

        $account = $automation->account;

        if ($account === null || $account->status !== 'connected') {
            throw ValidationException::withMessages([
                'automation' => __('Reconnect Instagram before switching this on.'),
            ]);
        }

        // Media ownership, revalidated. A post that has been deleted, or one
        // that was never on this account, must not be automated against.
        if ($automation->instagram_media_id !== null) {
            $owns = InstagramMedium::query()
                ->whereKey($automation->instagram_media_id)
                ->where('instagram_account_id', $account->id)
                ->exists();

            if (! $owns) {
                throw ValidationException::withMessages([
                    'automation' => __('That post is no longer on this account. Refresh your media and pick another.'),
                ]);
            }
        }

        $this->assertActionsPermitted($account, $version);

        return DB::transaction(function () use ($automation, $version, $actor) {
            $version->update(['activated_at' => now()]);

            $automation->update([
                'status' => AutodmAutomation::ACTIVE,
                'active_version_id' => $version->id,
                'activated_at' => now(),
            ]);

            $this->audit->record('autodm.activated', $automation, [
                'version' => $version->version_number,
            ], $actor->id);

            return $automation->fresh();
        });
    }

    public function deactivate(AutodmAutomation $automation, User $actor): void
    {
        $automation->update(['status' => AutodmAutomation::INACTIVE]);

        $this->audit->record('autodm.deactivated', $automation, [], $actor->id);
    }

    /** A copy, always as a draft — duplicating something must not switch it on. */
    public function duplicate(AutodmAutomation $automation, User $actor): AutodmAutomation
    {
        $version = $automation->draftVersion();

        return DB::transaction(function () use ($automation, $version, $actor) {
            $copy = AutodmAutomation::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $automation->user_id,
                'instagram_account_id' => $automation->instagram_account_id,
                'instagram_media_id' => $automation->instagram_media_id,
                'name' => Str::limit($automation->name.' (copy)', 120, ''),
                'status' => AutodmAutomation::DRAFT,
            ]);

            if ($version !== null) {
                AutodmAutomationVersion::query()->create([
                    'autodm_automation_id' => $copy->id,
                    'version_number' => 1,
                    'trigger_type' => $version->trigger_type,
                    'keywords' => $version->keywords,
                    'whole_word' => $version->whole_word,
                    'public_reply_enabled' => $version->public_reply_enabled,
                    'public_reply_text' => $version->public_reply_text,
                    'private_reply_enabled' => $version->private_reply_enabled,
                    'private_reply_text' => $version->private_reply_text,
                    'private_reply_url' => $version->private_reply_url,
                ]);
            }

            $this->audit->record('autodm.duplicated', $copy, [
                'from' => $automation->uuid,
            ], $actor->id);

            return $copy->fresh();
        });
    }

    /**
     * What the review screen should say before anybody commits.
     *
     * Includes the limitations, not only the settings. Somebody about to switch
     * on a private reply deserves to read that Instagram bounds it to a window
     * before they find out from an empty log.
     *
     * @return array<string, mixed>
     */
    public function review(AutodmAutomation $automation): array
    {
        $version = $automation->draftVersion();
        $account = $automation->account;

        return [
            'automation' => $automation,
            'version' => $version,
            'account' => $account,
            'capabilities' => $account ? $this->capabilities->summaryFor($account) : [],
            'limitations' => $this->limitations($version),
        ];
    }

    /** @return list<string> */
    private function limitations(?AutodmAutomationVersion $version): array
    {
        $notes = [
            __('Vidlix only ever acts through official Instagram APIs. Nothing is scraped, and your password is never asked for.'),
        ];

        if ($version?->private_reply_enabled) {
            $notes[] = __('Instagram allows one private reply to somebody who commented, within roughly :hours hours of their comment. It is not a way to message people who have not written to you, and there are no follow-ups.', [
                'hours' => Capabilities::PRIVATE_REPLY_WINDOW_HOURS,
            ]);
        }

        if ($version?->public_reply_enabled) {
            $notes[] = __('Public replies appear under the comment, where everybody can read them.');
        }

        return $notes;
    }

    private function assertActionsPermitted(InstagramAccount $account, AutodmAutomationVersion $version): void
    {
        if ($version->public_reply_enabled) {
            $check = $this->capabilities->check($account, AutodmRun::PUBLIC_REPLY);

            if (! $check['allowed']) {
                throw ValidationException::withMessages(['automation' => (string) $check['reason']]);
            }
        }

        if ($version->private_reply_enabled) {
            $check = $this->capabilities->check($account, AutodmRun::PRIVATE_REPLY);

            if (! $check['allowed']) {
                // Refused here rather than at 2am when a comment arrives. An
                // automation that cannot run should not look armed.
                throw ValidationException::withMessages(['automation' => (string) $check['reason']]);
            }
        }
    }

    private function resolveMediaId(InstagramAccount $account, mixed $mediaId): ?int
    {
        if (blank($mediaId)) {
            return null;
        }

        $medium = InstagramMedium::query()
            ->whereKey((int) $mediaId)
            ->where('instagram_account_id', $account->id)
            ->first();

        // Somebody else's post id must not become somebody's automation.
        abort_unless($medium !== null, 404);

        return $medium->id;
    }

    private function name(string $name): string
    {
        $clean = trim(strip_tags($name));

        return $clean !== '' ? mb_substr($clean, 0, 120) : __('Untitled automation');
    }

    /**
     * One keyword or phrase per line.
     *
     * @return list<string>
     */
    private function keywords(mixed $input): array
    {
        if (is_array($input)) {
            $lines = $input;
        } else {
            $lines = preg_split('/\r\n|\r|\n|,/', (string) $input) ?: [];
        }

        $words = [];

        foreach ($lines as $line) {
            if (! is_scalar($line)) {
                continue;
            }

            $word = trim((string) $line);

            if ($word !== '' && ! in_array($word, $words, true)) {
                $words[] = mb_substr($word, 0, 80);
            }
        }

        return array_slice($words, 0, self::MAX_KEYWORDS);
    }
}
