<?php

namespace App\Services\Deals;

use App\Models\Campaign;
use App\Models\Negotiation;
use App\Models\NegotiationOffer;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Offers, counter-offers, and the moment one of them becomes a deal.
 *
 * Two rules run through all of it.
 *
 * The first is that offers are never edited. A change of mind is the next
 * offer, so the terms that were accepted are still readable exactly as they
 * were accepted — a deal whose terms can be rewritten afterwards is not a deal.
 *
 * The second is that you cannot accept your own offer. Every acceptance here is
 * somebody agreeing to what the *other* side proposed, which is the only reading
 * of "accepted" that means anything.
 */
class NegotiationService
{
    public function __construct(
        private AuditLogger $audit,
        private Notifier $notifier,
    ) {}

    /**
     * Open a negotiation and put the first offer on the table.
     *
     * @param  array<string, mixed>  $terms
     */
    public function open(User $from, User $to, array $terms, ?Campaign $campaign = null, ?string $scope = null): Negotiation
    {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages([
                'counterparty' => __('You cannot negotiate with yourself.'),
            ]);
        }

        return DB::transaction(function () use ($from, $to, $terms, $campaign, $scope) {
            $negotiation = Negotiation::query()->create([
                'uuid' => (string) Str::uuid(),
                'campaign_id' => $campaign?->id,
                'initiator_user_id' => $from->id,
                'counterparty_user_id' => $to->id,
                'counterparty_scope' => $scope,
                'status' => Negotiation::OFFER_SENT,
                // Offers do not hang around forever. An open-ended one has to be
                // chased rather than answered, which is worse for both sides.
                'expires_at' => now()->addDays(14),
            ]);

            $this->addOffer($negotiation, $from, $terms);

            $this->audit->record('negotiation.opened', $negotiation, [
                'counterparty_user_id' => $to->id,
            ], $from->id);

            return $negotiation->fresh();
        });
    }

    /**
     * Counter with different terms.
     *
     * @param  array<string, mixed>  $terms
     */
    public function counter(Negotiation $negotiation, User $actor, array $terms): NegotiationOffer
    {
        $this->assertOpen($negotiation);
        $this->assertParticipant($negotiation, $actor);

        return DB::transaction(function () use ($negotiation, $actor, $terms) {
            $offer = $this->addOffer($negotiation, $actor, $terms);

            $negotiation->update([
                'status' => $negotiation->offers()->count() > 1
                    ? Negotiation::COUNTER_OFFER
                    : Negotiation::OFFER_SENT,
            ]);

            $this->notifyOtherSide($negotiation, $actor, __('New offer'), __('The terms have changed.'));

            $this->audit->record('negotiation.countered', $negotiation, [
                'sequence' => $offer->sequence,
            ], $actor->id);

            return $offer;
        });
    }

    /**
     * Accept whatever is currently on the table.
     *
     * The offer id is not taken from the caller. Accepting is always accepting
     * the latest offer, so there is no way to accept a stale one that has since
     * been countered.
     */
    public function accept(Negotiation $negotiation, User $actor): Project
    {
        $this->assertOpen($negotiation);
        $this->assertParticipant($negotiation, $actor);

        $offer = $negotiation->latestOffer();

        if ($offer === null) {
            throw ValidationException::withMessages([
                'negotiation' => __('There is nothing to accept yet.'),
            ]);
        }

        if ($offer->offered_by_user_id === $actor->id) {
            throw ValidationException::withMessages([
                'negotiation' => __('You cannot accept your own offer. Wait for the other side.'),
            ]);
        }

        return DB::transaction(function () use ($negotiation, $actor, $offer) {
            $project = Project::query()->create([
                'name' => $this->projectName($negotiation),
                'status' => 'active',
                'total_amount_minor' => $offer->amount_minor,
                'deadline' => $offer->deadline,
                'revision_limit' => $offer->revision_limit ?? 2,
            ]);

            $this->seedMilestones($project, $offer);

            $negotiation->update([
                'status' => Negotiation::ACCEPTED,
                'accepted_offer_id' => $offer->id,
                'accepted_at' => now(),
                'project_id' => $project->id,
            ]);

            $this->notifyOtherSide(
                $negotiation,
                $actor,
                __('Offer accepted'),
                __('Your offer was accepted and the project has started.'),
            );

            $this->audit->record('negotiation.accepted', $negotiation, [
                'offer_id' => $offer->id,
                'amount_minor' => $offer->amount_minor,
                'project_id' => $project->id,
            ], $actor->id);

            return $project;
        });
    }

    public function reject(Negotiation $negotiation, User $actor, ?string $reason = null): void
    {
        $this->assertOpen($negotiation);
        $this->assertParticipant($negotiation, $actor);

        $negotiation->update(['status' => Negotiation::REJECTED]);

        $this->notifyOtherSide($negotiation, $actor, __('Offer declined'), $reason ?: __('The offer was declined.'));

        $this->audit->record('negotiation.rejected', $negotiation, ['reason' => $reason], $actor->id);
    }

    /** Cancelling is for the person who opened it; rejecting is for the other side. */
    public function cancel(Negotiation $negotiation, User $actor): void
    {
        $this->assertOpen($negotiation);

        if ($negotiation->initiator_user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'negotiation' => __('Only the person who started this can cancel it.'),
            ]);
        }

        $negotiation->update(['status' => Negotiation::CANCELLED]);

        $this->audit->record('negotiation.cancelled', $negotiation, [], $actor->id);
    }

    /**
     * Close anything nobody answered in time.
     *
     * Run from the scheduler. Expiry is a status change rather than a deletion,
     * because "they never replied" is itself worth being able to see.
     */
    public function expireOverdue(): int
    {
        $overdue = Negotiation::query()
            ->whereNotIn('status', Negotiation::CLOSED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($overdue as $negotiation) {
            $negotiation->update(['status' => Negotiation::EXPIRED]);
            $this->audit->record('negotiation.expired', $negotiation);
        }

        return $overdue->count();
    }

    /** @param array<string, mixed> $terms */
    private function addOffer(Negotiation $negotiation, User $actor, array $terms): NegotiationOffer
    {
        $amount = (int) ($terms['amount_minor'] ?? 0);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount_minor' => __('Enter the amount you are offering.'),
            ]);
        }

        return NegotiationOffer::query()->create([
            'negotiation_id' => $negotiation->id,
            // max + 1 rather than count + 1: offers are never deleted, but
            // counting would still be the wrong instinct to leave in place.
            'sequence' => ((int) $negotiation->offers()->max('sequence')) + 1,
            'offered_by_user_id' => $actor->id,
            'amount_minor' => $amount,
            'currency' => $terms['currency'] ?? config('vidlix.currency', 'INR'),
            'deliverables' => $terms['deliverables'] ?? null,
            'deadline' => $terms['deadline'] ?? null,
            'revision_limit' => $terms['revision_limit'] ?? null,
            'usage_rights' => $terms['usage_rights'] ?? null,
            'note' => $terms['note'] ?? null,
        ]);
    }

    /**
     * Turn the accepted deliverables into milestones.
     *
     * An even split is a starting point, not a claim to be fair — it is
     * something both sides can see and adjust, which beats one lump sum at the
     * end with nothing to point at in between.
     */
    private function seedMilestones(Project $project, NegotiationOffer $offer): void
    {
        $deliverables = $offer->deliverableList();

        if ($deliverables === []) {
            ProjectMilestone::query()->create([
                'project_id' => $project->id,
                'position' => 0,
                'title' => __('Completed work'),
                'amount_minor' => $offer->amount_minor,
                'due_on' => $offer->deadline,
            ]);

            return;
        }

        $count = count($deliverables);
        $each = intdiv($offer->amount_minor, $count);
        // The rounding remainder goes on the last milestone, so the parts add
        // up to the agreed total exactly rather than approximately.
        $remainder = $offer->amount_minor - ($each * $count);

        foreach ($deliverables as $index => $deliverable) {
            ProjectMilestone::query()->create([
                'project_id' => $project->id,
                'position' => $index,
                'title' => $deliverable,
                'amount_minor' => $each + ($index === $count - 1 ? $remainder : 0),
                'due_on' => $offer->deadline,
            ]);
        }
    }

    private function projectName(Negotiation $negotiation): string
    {
        $name = $negotiation->campaign_id
            ? Campaign::query()->whereKey($negotiation->campaign_id)->value('name')
            : null;

        return is_string($name) && $name !== '' ? $name : __('Project');
    }

    private function notifyOtherSide(Negotiation $negotiation, User $actor, string $title, string $body): void
    {
        $otherId = $negotiation->otherSideOf($actor->id);

        if ($otherId === null) {
            return;
        }

        $other = User::query()->find($otherId);

        if ($other !== null) {
            $this->notifier->send($other, 'negotiation', $title, $body, [
                'negotiation_uuid' => (string) $negotiation->uuid,
            ]);
        }
    }

    private function assertOpen(Negotiation $negotiation): void
    {
        if (! $negotiation->isOpen()) {
            throw ValidationException::withMessages([
                'negotiation' => __('This negotiation is already closed.'),
            ]);
        }
    }

    private function assertParticipant(Negotiation $negotiation, User $actor): void
    {
        // A 404 rather than a 403: somebody who is not in this negotiation
        // should not learn that it exists.
        abort_unless($negotiation->involves($actor->id), 404);
    }
}
