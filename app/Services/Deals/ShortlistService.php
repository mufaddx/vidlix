<?php

namespace App\Services\Deals;

use App\Models\Campaign;
use App\Models\CreatorProfile;
use App\Models\EditorProfile;
use App\Models\Favorite;
use App\Models\Shortlist;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Saving somebody for later, and putting them in the running for a campaign.
 *
 * Two different acts, kept apart on purpose. A favourite is personal and
 * outlives any one piece of work; a shortlist belongs to a campaign and ends
 * with it. Folding them together would mean either losing your saved people
 * when a campaign closes, or carrying a campaign decision into the next one.
 *
 * Both are real rows. Nothing here counts anything it has not stored.
 */
class ShortlistService
{
    public function __construct(private AuditLogger $audit) {}

    /* ------------------------------------------------------------ favourites */

    public function toggleFavorite(User $user, string $type, int $subjectId): bool
    {
        $this->assertType($type);
        $this->assertSubjectExists($type, $subjectId);

        $existing = Favorite::query()
            ->where('user_id', $user->id)
            ->where('subject_type', $type)
            ->where('subject_id', $subjectId)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        Favorite::query()->create([
            'user_id' => $user->id,
            'subject_type' => $type,
            'subject_id' => $subjectId,
        ]);

        return true;
    }

    public function hasFavorited(User $user, string $type, int $subjectId): bool
    {
        return Favorite::query()
            ->where('user_id', $user->id)
            ->where('subject_type', $type)
            ->where('subject_id', $subjectId)
            ->exists();
    }

    /** @return Collection<int, Favorite> */
    public function favorites(User $user, ?string $type = null): Collection
    {
        return Favorite::query()
            ->where('user_id', $user->id)
            ->when($type !== null, fn ($q) => $q->where('subject_type', $type))
            ->latest()
            ->get();
    }

    /* ------------------------------------------------------------ shortlists */

    /**
     * Put somebody on a campaign's shortlist.
     *
     * The campaign is passed in already authorised — this service does not
     * decide who owns a campaign, and quietly re-checking it here would put
     * that decision in two places.
     */
    public function shortlist(Campaign $campaign, User $actor, string $type, int $subjectId, ?string $note = null): Shortlist
    {
        $this->assertType($type);
        $this->assertSubjectExists($type, $subjectId);

        $shortlist = Shortlist::query()->firstOrCreate(
            [
                'campaign_id' => $campaign->id,
                'subject_type' => $type,
                'subject_id' => $subjectId,
            ],
            [
                'user_id' => $actor->id,
                'note' => $note,
            ],
        );

        // A shortlisting is a decision about somebody's livelihood, so who made
        // it and when is recorded rather than inferred from a timestamp.
        $this->audit->record('campaign.shortlisted', $campaign, [
            'subject_type' => $type,
            'subject_id' => $subjectId,
        ], $actor->id);

        return $shortlist;
    }

    public function removeFromShortlist(Campaign $campaign, User $actor, string $type, int $subjectId): void
    {
        $removed = Shortlist::query()
            ->where('campaign_id', $campaign->id)
            ->where('subject_type', $type)
            ->where('subject_id', $subjectId)
            ->delete();

        if ($removed > 0) {
            $this->audit->record('campaign.unshortlisted', $campaign, [
                'subject_type' => $type,
                'subject_id' => $subjectId,
            ], $actor->id);
        }
    }

    /** @return Collection<int, Shortlist> */
    public function forCampaign(Campaign $campaign): Collection
    {
        return Shortlist::query()
            ->where('campaign_id', $campaign->id)
            ->latest()
            ->get();
    }

    public function isShortlisted(Campaign $campaign, string $type, int $subjectId): bool
    {
        return Shortlist::query()
            ->where('campaign_id', $campaign->id)
            ->where('subject_type', $type)
            ->where('subject_id', $subjectId)
            ->exists();
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, Favorite::TYPES, true)) {
            throw ValidationException::withMessages([
                'subject_type' => __('You can only save creators and editors.'),
            ]);
        }
    }

    /**
     * The subject has to be real.
     *
     * Without this, an id nobody owns could be saved and would then be counted
     * on every listing — a fake number arrived at honestly is still a fake
     * number.
     */
    private function assertSubjectExists(string $type, int $subjectId): void
    {
        $exists = $type === 'creator'
            ? CreatorProfile::query()->whereKey($subjectId)->exists()
            : EditorProfile::query()->whereKey($subjectId)->exists();

        abort_unless($exists, 404);
    }
}
