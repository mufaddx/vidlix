<?php

namespace App\Services\Marketplace;

use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A campaign's life, as a set of permitted moves.
 *
 * Explicit transitions rather than "set whatever status you were sent", because
 * the order carries meaning: a campaign is reviewed before it is published, and
 * a closed one should not quietly reopen without somebody deciding to. A status
 * column anybody can write freely is not a lifecycle, it is a text field.
 *
 * Some moves are the brand's and some are the platform's. Publishing is a
 * review decision; pausing is the brand's own. Keeping them in one table makes
 * that distinction visible rather than scattered across two controllers.
 */
class CampaignLifecycle
{
    public const DRAFT = 'draft';

    public const PENDING_REVIEW = 'pending_review';

    public const PUBLISHED = 'published';

    public const PAUSED = 'paused';

    public const CLOSED = 'closed';

    public const CANCELLED = 'cancelled';

    public const COMPLETED = 'completed';

    /** What may follow what. Anything not listed is refused. */
    private const TRANSITIONS = [
        self::DRAFT => [self::PENDING_REVIEW, self::CANCELLED],
        self::PENDING_REVIEW => [self::PUBLISHED, self::DRAFT, self::CANCELLED],
        // Reopening is publishing again; there is no separate "reopened" state,
        // because a reopened campaign behaves exactly like a published one and
        // a second word for it would only be a second thing to check for.
        self::PUBLISHED => [self::PAUSED, self::CLOSED, self::CANCELLED],
        self::PAUSED => [self::PUBLISHED, self::CLOSED, self::CANCELLED],
        self::CLOSED => [self::PUBLISHED, self::COMPLETED],
        self::COMPLETED => [],
        self::CANCELLED => [],
    ];

    /** Moves a brand may make on its own campaign. */
    private const BRAND_MOVES = [
        self::PENDING_REVIEW, self::PAUSED, self::CLOSED, self::PUBLISHED,
        self::CANCELLED, self::COMPLETED, self::DRAFT,
    ];

    /** Moves only a reviewer may make. */
    private const REVIEW_MOVES = [self::PUBLISHED];

    public function __construct(
        private AuditLogger $audit,
        private Notifier $notifier,
    ) {}

    /** @return list<string> what this campaign could move to next */
    public function availableTo(Campaign $campaign): array
    {
        return self::TRANSITIONS[$campaign->status] ?? [];
    }

    public function canMove(Campaign $campaign, string $to): bool
    {
        return in_array($to, $this->availableTo($campaign), true);
    }

    /**
     * Move a campaign, as the brand.
     *
     * A brand publishing its own campaign from review would be approving
     * itself, so that one move is refused here and belongs to a reviewer.
     */
    public function transition(Campaign $campaign, User $actor, string $to): Campaign
    {
        $this->assertPermitted($campaign, $to);

        if ($campaign->status === self::PENDING_REVIEW && $to === self::PUBLISHED) {
            throw ValidationException::withMessages([
                'status' => __('A campaign in review is published by Vidlix, not by you.'),
            ]);
        }

        if (! in_array($to, self::BRAND_MOVES, true)) {
            throw ValidationException::withMessages([
                'status' => __('That is not a change you can make.'),
            ]);
        }

        return $this->apply($campaign, $to, $actor, 'campaign.transitioned');
    }

    /** Move a campaign as a reviewer. Only publishing lives here. */
    public function review(Campaign $campaign, User $reviewer, string $to, ?string $note = null): Campaign
    {
        $this->assertPermitted($campaign, $to);

        if (! in_array($to, self::REVIEW_MOVES, true) && $to !== self::DRAFT) {
            throw ValidationException::withMessages([
                'status' => __('A reviewer may publish a campaign or send it back as a draft.'),
            ]);
        }

        $campaign = $this->apply($campaign, $to, $reviewer, 'campaign.reviewed', $note);

        $this->notifyBrand(
            $campaign,
            $to === self::PUBLISHED ? __('Campaign published') : __('Campaign sent back'),
            $note ?: ($to === self::PUBLISHED
                ? __('Your campaign is live and creators can apply.')
                : __('Your campaign needs changes before it can go live.')),
        );

        return $campaign;
    }

    /**
     * Close a campaign and decline anything still waiting on it.
     *
     * Leaving applications pending against a campaign that has ended is the
     * cruellest possible default: the applicant sees "pending" forever and
     * never learns the answer was no.
     */
    public function close(Campaign $campaign, User $actor): Campaign
    {
        return DB::transaction(function () use ($campaign, $actor) {
            $campaign = $this->transition($campaign, $actor, self::CLOSED);

            CampaignApplication::query()
                ->where('campaign_id', $campaign->id)
                // 'rejected' rather than a new word, because that is the state
                // the rest of the system already understands and displays.
                ->whereIn('status', ['applied', 'viewed', 'shortlisted'])
                ->get()
                ->each(function (CampaignApplication $application) use ($campaign) {
                    $application->update(['status' => 'rejected']);

                    $this->audit->record('application.rejected_on_close', $application, [
                        'campaign_id' => $campaign->id,
                    ]);
                });

            return $campaign;
        });
    }

    private function apply(
        Campaign $campaign,
        string $to,
        User $actor,
        string $event,
        ?string $note = null,
    ): Campaign {
        $from = $campaign->status;

        $campaign->status = $to;

        // Stamped once, when it first happens. Reopening a closed campaign must
        // not rewrite when it was originally published.
        if ($to === self::PUBLISHED) {
            $campaign->published_at ??= now();
            $campaign->closed_at = null;
        }

        if (in_array($to, [self::CLOSED, self::COMPLETED, self::CANCELLED], true)) {
            $campaign->closed_at ??= now();
        }

        $campaign->save();

        $this->audit->record($event, $campaign, [
            'from' => $from,
            'to' => $to,
            'note' => $note,
        ], $actor->id);

        return $campaign;
    }

    private function assertPermitted(Campaign $campaign, string $to): void
    {
        if (! $this->canMove($campaign, $to)) {
            throw ValidationException::withMessages([
                'status' => __('A campaign cannot go from :from to :to.', [
                    'from' => str_replace('_', ' ', $campaign->status),
                    'to' => str_replace('_', ' ', $to),
                ]),
            ]);
        }
    }

    private function notifyBrand(Campaign $campaign, string $title, string $body): void
    {
        $owner = $campaign->brandProfile?->user;

        if ($owner !== null) {
            $this->notifier->send($owner, 'campaign', $title, $body, [
                'campaign_id' => (string) $campaign->id,
            ]);
        }
    }
}
