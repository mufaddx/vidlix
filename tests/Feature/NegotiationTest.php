<?php

namespace Tests\Feature;

use App\Models\BrandProfile;
use App\Models\Campaign;
use App\Models\Negotiation;
use App\Models\ProjectMilestone;
use App\Models\Role;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use App\Services\Deals\MilestoneService;
use App\Services\Deals\NegotiationService;
use App\Services\Deals\ShortlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Offers, counter-offers, and the moment one becomes a deal.
 *
 * The two things worth defending: an accepted offer must still read exactly as
 * it read when it was accepted, and nobody may accept their own offer.
 */
class NegotiationTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $roleSlug = 'creator'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $role = Role::query()->firstOrCreate(['slug' => $roleSlug], ['name' => ucfirst($roleSlug)]);
        $user->roles()->attach($role);

        return $user->fresh();
    }

    private function service(): NegotiationService
    {
        return app(NegotiationService::class);
    }

    /** @param array<string, mixed> $overrides */
    private function terms(array $overrides = []): array
    {
        return array_merge([
            'amount_minor' => 500000,
            'deliverables' => ['2 reels', '4 stories'],
            'deadline' => now()->addWeeks(2)->toDateString(),
            'revision_limit' => 2,
        ], $overrides);
    }

    /* ------------------------------------------------------------- offering */

    public function test_an_offer_opens_a_negotiation(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        $negotiation = $this->service()->open($brand, $creator, $this->terms());

        $this->assertSame(Negotiation::OFFER_SENT, $negotiation->status);
        $this->assertCount(1, $negotiation->offers);
        $this->assertSame(500000, $negotiation->latestOffer()->amount_minor);
    }

    public function test_you_cannot_negotiate_with_yourself(): void
    {
        $user = $this->member();

        $this->expectException(ValidationException::class);

        $this->service()->open($user, $user, $this->terms());
    }

    public function test_an_offer_needs_an_amount(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->open(
            $this->member('brand'),
            $this->member('creator'),
            $this->terms(['amount_minor' => 0]),
        );
    }

    public function test_a_counter_is_a_new_offer_rather_than_an_edit(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        $negotiation = $this->service()->open($brand, $creator, $this->terms());
        $first = $negotiation->latestOffer();

        $this->service()->counter($negotiation->fresh(), $creator, $this->terms(['amount_minor' => 750000]));

        $negotiation = $negotiation->fresh();

        // The original is untouched. Two offers exist, and the history is the
        // record of what each side actually asked for.
        $this->assertCount(2, $negotiation->offers);
        $this->assertSame(500000, $first->fresh()->amount_minor);
        $this->assertSame(750000, $negotiation->latestOffer()->amount_minor);
        $this->assertSame(Negotiation::COUNTER_OFFER, $negotiation->status);
    }

    /* ------------------------------------------------------------ accepting */

    public function test_you_cannot_accept_your_own_offer(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        $negotiation = $this->service()->open($brand, $creator, $this->terms());

        // Agreeing with yourself is not agreement.
        $this->expectException(ValidationException::class);

        $this->service()->accept($negotiation, $brand);
    }

    public function test_accepting_creates_a_project_on_the_agreed_terms(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        $negotiation = $this->service()->open($brand, $creator, $this->terms());
        $project = $this->service()->accept($negotiation->fresh(), $creator);

        $this->assertSame(500000, $project->total_amount_minor);
        $this->assertSame(2, $project->revision_limit);

        $negotiation = $negotiation->fresh();
        $this->assertSame(Negotiation::ACCEPTED, $negotiation->status);
        $this->assertSame($project->id, $negotiation->project_id);
        $this->assertNotNull($negotiation->accepted_offer_id);
    }

    public function test_the_accepted_terms_stay_readable_exactly_as_accepted(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        $negotiation = $this->service()->open($brand, $creator, $this->terms());
        $this->service()->counter($negotiation->fresh(), $creator, $this->terms(['amount_minor' => 750000]));
        $this->service()->accept($negotiation->fresh(), $brand);

        $accepted = $negotiation->fresh()->acceptedOffer();

        // A deal whose terms can be rewritten afterwards is not a deal.
        $this->assertSame(750000, $accepted->amount_minor);
        $this->assertSame(2, $accepted->sequence);
    }

    public function test_accepting_always_takes_the_offer_on_the_table(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        $negotiation = $this->service()->open($brand, $creator, $this->terms(['amount_minor' => 100000]));
        $this->service()->counter($negotiation->fresh(), $creator, $this->terms(['amount_minor' => 900000]));

        $project = $this->service()->accept($negotiation->fresh(), $brand);

        // No offer id is accepted from the caller, so the superseded 100000
        // offer is not reachable.
        $this->assertSame(900000, $project->total_amount_minor);
    }

    public function test_a_closed_negotiation_takes_no_further_offers(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        $negotiation = $this->service()->open($brand, $creator, $this->terms());
        $this->service()->accept($negotiation->fresh(), $creator);

        $this->expectException(ValidationException::class);

        $this->service()->counter($negotiation->fresh(), $brand, $this->terms(['amount_minor' => 1]));
    }

    /* ----------------------------------------------------------- milestones */

    public function test_each_deliverable_becomes_a_milestone(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        $negotiation = $this->service()->open($brand, $creator, $this->terms([
            'amount_minor' => 900000,
            'deliverables' => ['2 reels', '4 stories', '1 grid post'],
        ]));

        $project = $this->service()->accept($negotiation->fresh(), $creator);

        $milestones = ProjectMilestone::query()->where('project_id', $project->id)->orderBy('position')->get();

        $this->assertCount(3, $milestones);
        $this->assertSame('2 reels', $milestones[0]->title);

        // The parts add up to the agreed total exactly, not approximately.
        $this->assertSame(900000, $milestones->sum('amount_minor'));
    }

    public function test_a_rounding_remainder_lands_on_the_last_milestone(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        // 100001 over three ways does not divide evenly.
        $negotiation = $this->service()->open($brand, $creator, $this->terms([
            'amount_minor' => 100001,
            'deliverables' => ['a', 'b', 'c'],
        ]));

        $project = $this->service()->accept($negotiation->fresh(), $creator);

        $this->assertSame(
            100001,
            (int) ProjectMilestone::query()->where('project_id', $project->id)->sum('amount_minor'),
        );
    }

    public function test_a_milestone_cannot_skip_straight_to_paid(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        $negotiation = $this->service()->open($brand, $creator, $this->terms());
        $project = $this->service()->accept($negotiation->fresh(), $creator);

        $milestone = ProjectMilestone::query()->where('project_id', $project->id)->firstOrFail();

        // Work is submitted before it is approved, and approved before it is
        // paid. The order is the whole point.
        $this->expectException(ValidationException::class);

        app(MilestoneService::class)->transition($milestone, $brand, ProjectMilestone::PAID);
    }

    public function test_a_milestone_walks_the_whole_way_in_order(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        $negotiation = $this->service()->open($brand, $creator, $this->terms(['deliverables' => ['one']]));
        $project = $this->service()->accept($negotiation->fresh(), $creator);

        $milestone = ProjectMilestone::query()->where('project_id', $project->id)->firstOrFail();
        $milestones = app(MilestoneService::class);

        foreach ([
            ProjectMilestone::IN_PROGRESS,
            ProjectMilestone::SUBMITTED,
            ProjectMilestone::APPROVED,
            ProjectMilestone::PAID,
        ] as $step) {
            $milestone = $milestones->transition($milestone, $creator, $step);
        }

        $this->assertSame(ProjectMilestone::PAID, $milestone->status);
        $this->assertNotNull($milestone->submitted_at);
        $this->assertNotNull($milestone->approved_at);
        $this->assertNotNull($milestone->paid_at);

        $this->assertSame(0, $milestones->remainingMinor($project));
        $this->assertSame(500000, $milestones->paidMinor($project));
    }

    /* -------------------------------------------------------------- expiry */

    public function test_an_offer_nobody_answered_expires(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        $negotiation = $this->service()->open($brand, $creator, $this->terms());
        $negotiation->update(['expires_at' => now()->subDay()]);

        $this->assertSame(1, $this->service()->expireOverdue());

        // Expiry is a status, not a deletion: "they never replied" is itself
        // worth being able to see.
        $this->assertSame(Negotiation::EXPIRED, $negotiation->fresh()->status);
    }

    public function test_an_accepted_negotiation_is_not_expired_later(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        $negotiation = $this->service()->open($brand, $creator, $this->terms());
        $this->service()->accept($negotiation->fresh(), $creator);

        $negotiation->update(['expires_at' => now()->subDay()]);

        $this->assertSame(0, $this->service()->expireOverdue());
        $this->assertSame(Negotiation::ACCEPTED, $negotiation->fresh()->status);
    }

    /* ------------------------------------------------------- authorisation */

    public function test_a_stranger_cannot_see_or_touch_a_negotiation(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');
        $stranger = $this->member('editor');

        $negotiation = $this->service()->open($brand, $creator, $this->terms());

        // A 404 rather than a 403: a stranger should not learn that a deal
        // between two other people exists, let alone what it is worth.
        $this->actingAs($stranger)
            ->get(route('app.negotiations.show', $negotiation->uuid))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->post(route('app.negotiations.accept', $negotiation->uuid))
            ->assertNotFound();
    }

    public function test_only_the_person_who_started_it_can_cancel_it(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        $negotiation = $this->service()->open($brand, $creator, $this->terms());

        $this->expectException(ValidationException::class);

        $this->service()->cancel($negotiation->fresh(), $creator);
    }

    /* ----------------------------------------------- favourites and shortlists */

    public function test_a_favourite_is_a_real_row_that_toggles(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');
        $profile = app(CreatorOnboardingService::class)->provision($creator->id, 'Mira');

        $shortlists = app(ShortlistService::class);

        $this->assertTrue($shortlists->toggleFavorite($brand, 'creator', $profile->id));
        $this->assertTrue($shortlists->hasFavorited($brand, 'creator', $profile->id));

        $this->assertFalse($shortlists->toggleFavorite($brand, 'creator', $profile->id));
        $this->assertFalse($shortlists->hasFavorited($brand, 'creator', $profile->id));
    }

    public function test_you_cannot_favourite_somebody_who_does_not_exist(): void
    {
        // A saved id nobody owns would still be counted on every listing, and a
        // fake number arrived at honestly is still a fake number.
        $this->expectException(HttpException::class);

        app(ShortlistService::class)->toggleFavorite($this->member('brand'), 'creator', 99999);
    }

    public function test_a_shortlist_belongs_to_one_campaign(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');
        $profile = app(CreatorOnboardingService::class)->provision($creator->id, 'Mira');

        $campaign = $this->campaignFor($brand);
        $other = $this->campaignFor($brand);

        $shortlists = app(ShortlistService::class);
        $shortlists->shortlist($campaign, $brand, 'creator', $profile->id);

        $this->assertTrue($shortlists->isShortlisted($campaign, 'creator', $profile->id));

        // A decision about one campaign must not carry into the next.
        $this->assertFalse($shortlists->isShortlisted($other, 'creator', $profile->id));
    }

    public function test_shortlisting_twice_does_not_duplicate(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');
        $profile = app(CreatorOnboardingService::class)->provision($creator->id, 'Mira');
        $campaign = $this->campaignFor($brand);

        $shortlists = app(ShortlistService::class);
        $shortlists->shortlist($campaign, $brand, 'creator', $profile->id);
        $shortlists->shortlist($campaign, $brand, 'creator', $profile->id);

        $this->assertCount(1, $shortlists->forCampaign($campaign));
    }

    private function campaignFor(User $brand): Campaign
    {
        // firstOrCreate, because a brand may run more than one campaign and
        // the relation on a stale model does not know about the last one.
        $profile = BrandProfile::query()->firstOrCreate(
            ['user_id' => $brand->id],
            [
                'company_name' => 'Brand '.$brand->id,
                'slug' => 'brand-'.$brand->id,
                'business_email' => $brand->email,
                'verification_status' => 'verified',
            ],
        );

        return Campaign::query()->create([
            'brand_profile_id' => $profile->id,
            'name' => 'Campaign '.uniqid(),
            'slug' => 'campaign-'.uniqid(),
            'status' => 'published',
        ]);
    }
}
