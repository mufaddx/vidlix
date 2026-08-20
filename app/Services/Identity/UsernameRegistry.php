<?php

namespace App\Services\Identity;

use App\Models\CreatorProfile;
use App\Models\EditorProfile;
use App\Models\ReservedUsername;
use App\Models\User;
use App\Models\Username;
use App\Models\UsernameHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one place a public handle is normalised, checked and claimed.
 *
 * Every path into a username goes through here — signup, onboarding, a rename,
 * and the vidlix.in/{username} resolver — so the rules cannot disagree with
 * each other. In particular, normalisation happens before *every* comparison:
 * a reserved-word check against the raw input would let "Admin" or "ADMIN"
 * through a list that only contains "admin".
 */
class UsernameRegistry
{
    /**
     * Deliberately narrow. Letters, digits, one separator between them, 3–30
     * characters. Unicode is excluded on purpose: homoglyphs make two visually
     * identical handles that are different strings, which is impersonation with
     * extra steps.
     */
    public const PATTERN = '/^[a-z0-9](?:[a-z0-9._-]{1,28}[a-z0-9])$/';

    public const MIN_LENGTH = 3;

    public const MAX_LENGTH = 30;

    /**
     * Lowercase, trimmed, and with the separators collapsed.
     *
     * Confusable separators are folded together so "john.doe", "john-doe" and
     * "john_doe" cannot be three different people. The stored form keeps the
     * separator the person chose; this value is only ever used for comparison.
     */
    public function normalise(string $username): string
    {
        return mb_strtolower(trim($username));
    }

    /** The form used to test whether two handles are confusable with each other. */
    public function comparable(string $username): string
    {
        return preg_replace('/[._-]+/', '-', $this->normalise($username)) ?? '';
    }

    public function isReserved(string $username): bool
    {
        $name = $this->normalise($username);

        return ReservedUsername::query()->where('username', $name)->exists();
    }

    public function isWellFormed(string $username): bool
    {
        $name = $this->normalise($username);

        return mb_strlen($name) >= self::MIN_LENGTH
            && mb_strlen($name) <= self::MAX_LENGTH
            && preg_match(self::PATTERN, $name) === 1;
    }

    /**
     * Is this name free for the given user to take?
     *
     * A name already held by the same user is available to them — otherwise
     * saving a profile without changing the handle would fail its own check.
     */
    public function isAvailable(string $username, ?int $forUserId = null): bool
    {
        if (! $this->isWellFormed($username) || $this->isReserved($username)) {
            return false;
        }

        $comparable = $this->comparable($username);

        $clash = Username::query()
            ->whereIn('status', [Username::ACTIVE, Username::RESERVED, Username::RETIRED])
            ->get(['user_id', 'username'])
            ->first(fn (Username $row) => $this->comparable($row->username) === $comparable
                && $row->user_id !== $forUserId);

        return $clash === null;
    }

    /**
     * Explain a refusal in the words the person needs, or return null if the
     * name is fine. Kept separate from isAvailable() so a form can say why.
     */
    public function refusalReason(string $username, ?int $forUserId = null): ?string
    {
        $name = $this->normalise($username);

        if (mb_strlen($name) < self::MIN_LENGTH) {
            return __('Usernames are at least :count characters.', ['count' => self::MIN_LENGTH]);
        }

        if (mb_strlen($name) > self::MAX_LENGTH) {
            return __('Usernames are at most :count characters.', ['count' => self::MAX_LENGTH]);
        }

        if (preg_match(self::PATTERN, $name) !== 1) {
            return __('Use letters and numbers, with dots, dashes or underscores in between.');
        }

        if ($this->isReserved($name)) {
            return __('That username is reserved.');
        }

        if (! $this->isAvailable($name, $forUserId)) {
            return __('That username is already taken.');
        }

        return null;
    }

    /**
     * Claim a name for a profile, releasing whatever that profile held before.
     *
     * The insert relies on the unique index rather than on the check above: two
     * requests can both pass the check at the same moment, and only the index
     * settles which one actually gets the name.
     */
    public function claim(User $user, string $profileType, int $profileId, string $username): Username
    {
        $name = $this->normalise($username);

        if ($reason = $this->refusalReason($name, $user->id)) {
            throw ValidationException::withMessages(['username' => $reason]);
        }

        return DB::transaction(function () use ($user, $profileType, $profileId, $name) {
            $existing = Username::query()
                ->where('profile_type', $profileType)
                ->where('profile_id', $profileId)
                ->where('status', Username::ACTIVE)
                ->first();

            if ($existing !== null) {
                if ($existing->username === $name) {
                    return $existing;
                }

                // Retired rather than deleted: the old link is out in the world
                // already, and it should redirect rather than 404.
                $existing->update([
                    'status' => Username::RETIRED,
                    'released_at' => now(),
                ]);

                UsernameHistory::query()->create([
                    'username' => $existing->username,
                    'user_id' => $user->id,
                    'profile_type' => $profileType,
                    'held_from' => $existing->created_at,
                    'held_until' => now(),
                ]);
            }

            return Username::query()->create([
                'username' => $name,
                'user_id' => $user->id,
                'profile_type' => $profileType,
                'profile_id' => $profileId,
                'status' => Username::ACTIVE,
            ]);
        });
    }

    /** The active registry row for a name, or null. */
    public function lookup(string $username): ?Username
    {
        return Username::query()
            ->where('username', $this->normalise($username))
            ->where('status', Username::ACTIVE)
            ->first();
    }

    /**
     * The profile a handle points at, or null.
     *
     * Returns the profile whatever its state — published, private, suspended.
     * Deciding what a visitor may see is the caller's job, and doing it here
     * would mean the resolver silently conflated "no such person" with "that
     * person's page is off", which are different answers to different questions
     * even though both end in a 404.
     */
    public function resolveProfile(string $username): ?Model
    {
        $row = $this->lookup($username);

        if ($row === null) {
            return null;
        }

        return match ($row->profile_type) {
            'creator' => CreatorProfile::query()->find($row->profile_id),
            'editor' => EditorProfile::query()->find($row->profile_id),
            default => null,
        };
    }

    /** A name somebody used to hold, for redirecting an old link. */
    public function previousHolder(string $username): ?UsernameHistory
    {
        return UsernameHistory::query()
            ->where('username', $this->normalise($username))
            ->latest('held_until')
            ->first();
    }

    /**
     * A free handle built from a display name, for suggesting one during
     * onboarding. Falls back to a numeric suffix rather than failing.
     */
    public function suggestFrom(string $name, string $fallback = 'user'): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(trim($name))) ?? '';
        $base = trim($base, '-') ?: $fallback;
        $base = mb_substr($base, 0, self::MAX_LENGTH - 4);

        if (mb_strlen($base) < self::MIN_LENGTH) {
            $base = str_pad($base, self::MIN_LENGTH, '0');
        }

        if ($this->isAvailable($base)) {
            return $base;
        }

        for ($i = 2; $i < 1000; $i++) {
            $candidate = $base.$i;
            if ($this->isAvailable($candidate)) {
                return $candidate;
            }
        }

        return $base.bin2hex(random_bytes(3));
    }
}
