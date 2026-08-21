<?php

namespace App\Services\Profiles;

use App\Models\User;

/**
 * What profiles a person has, and what state each one is in.
 *
 * One account holds up to three profiles: influencer, editor and brand. They
 * are not separate logins, they do not share data, and holding one says nothing
 * about the others.
 *
 * This is the only place that decides whether a profile is usable. Before it
 * existed, "has the role attached" meant "is active", so applying to be an
 * editor made somebody an editor on the spot and the admin review decided
 * nothing.
 */
class ProfileDirectory
{
    public const ACTIVE = 'active';

    /** Sent, and waiting on somebody here. */
    public const PENDING = 'pending';

    /**
     * Started but not sent.
     *
     * Distinct from both: nobody is reviewing it, so it is not pending, and the
     * person has clearly opted in, so it is not not-applied. Collapsing it into
     * either one tells them the wrong thing about whether to wait or to act.
     */
    public const DRAFT = 'draft';

    public const REJECTED = 'rejected';

    public const SUSPENDED = 'suspended';

    public const NOT_APPLIED = 'not_applied';

    /** The three profile types, in the order they are shown. */
    public const TYPES = ['creator', 'editor', 'brand'];

    public const LABELS = [
        'creator' => 'Influencer',
        'editor' => 'Editor',
        'brand' => 'Brand',
    ];

    /**
     * Every profile type for this person, with its state.
     *
     * @return array<string, array{type: string, label: string, status: string, exists: bool, needs_review: bool}>
     */
    public function forUser(User $user): array
    {
        return [
            'creator' => $this->describe('creator', $this->creatorStatus($user), false),
            'editor' => $this->describe('editor', $this->editorStatus($user), true),
            'brand' => $this->describe('brand', $this->brandStatus($user), true),
        ];
    }

    /** @return list<string> the profiles this person may actually switch into */
    public function activeTypes(User $user): array
    {
        return array_keys(array_filter(
            $this->forUser($user),
            fn (array $profile) => $profile['status'] === self::ACTIVE,
        ));
    }

    public function isActive(User $user, string $type): bool
    {
        return ($this->forUser($user)[$type]['status'] ?? null) === self::ACTIVE;
    }

    public function statusOf(User $user, string $type): string
    {
        return $this->forUser($user)[$type]['status'] ?? self::NOT_APPLIED;
    }

    /**
     * An influencer profile needs no review: making media and being findable is
     * not a claim about anybody else. Editor and brand are, which is why those
     * two wait for a person to check them.
     */
    private function creatorStatus(User $user): string
    {
        $profile = $user->creatorProfile()->first();

        if ($profile === null) {
            return self::NOT_APPLIED;
        }

        return $profile->visibility === 'suspended' ? self::SUSPENDED : self::ACTIVE;
    }

    private function editorStatus(User $user): string
    {
        $profile = $user->editorProfile()->first();

        if ($profile === null) {
            return self::NOT_APPLIED;
        }

        return match ($profile->application_status) {
            'approved' => self::ACTIVE,
            // 'submitted' and 'under_review' are separate answers to the
            // applicant but the same answer to the rest of the system: not yet.
            // 'more_info' sits here too — it is still an open application, and
            // treating it as not-applied would lose the reviewer's note.
            'pending_review', 'submitted', 'under_review', 'more_info' => self::PENDING,
            'draft' => self::DRAFT,
            'rejected' => self::REJECTED,
            'suspended' => self::SUSPENDED,
            default => self::NOT_APPLIED,
        };
    }

    private function brandStatus(User $user): string
    {
        $profile = $user->brandProfile()->first();

        if ($profile === null) {
            return self::NOT_APPLIED;
        }

        return match ($profile->verification_status) {
            'verified', 'approved' => self::ACTIVE,
            'pending_review' => self::PENDING,
            'rejected' => self::REJECTED,
            'suspended' => self::SUSPENDED,
            default => self::NOT_APPLIED,
        };
    }

    /** @return array{type: string, label: string, status: string, exists: bool, needs_review: bool} */
    private function describe(string $type, string $status, bool $needsReview): array
    {
        return [
            'type' => $type,
            'label' => self::LABELS[$type],
            'status' => $status,
            // A draft exists — somebody started it — even though nothing has
            // been sent.
            'exists' => $status !== self::NOT_APPLIED,
            'needs_review' => $needsReview,
        ];
    }
}
