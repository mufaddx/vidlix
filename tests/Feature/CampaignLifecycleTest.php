<?php

namespace Tests\Feature;

use App\Models\BrandProfile;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\PortfolioItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use App\Services\Marketplace\CampaignLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A campaign's life, and a portfolio's order.
 *
 * The lifecycle is a set of permitted moves rather than a writable column, so
 * most of what is worth asserting is which moves are refused.
 */
class CampaignLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $roleSlug): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $role = Role::query()->firstOrCreate(['slug' => $roleSlug], ['name' => ucfirst($roleSlug)]);
        $user->roles()->attach($role);

        return $user->fresh();
    }

    private function brandWithCampaign(string $status = CampaignLifecycle::DRAFT): array
    {
        $user = $this->member('brand');

        $profile = BrandProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Brand '.$user->id,
            'slug' => 'brand-'.$user->id,
            'business_email' => $user->email,
            'verification_status' => 'verified',
        ]);

        $campaign = Campaign::query()->create([
            'brand_profile_id' => $profile->id,
            'name' => 'Summer push',
            'slug' => 'summer-'.$user->id,
            'status' => $status,
        ]);

        return [$user->fresh(), $campaign];
    }

    private function lifecycle(): CampaignLifecycle
    {
        return app(CampaignLifecycle::class);
    }

    /* ------------------------------------------------------------ movement */

    public function test_a_draft_goes_to_review_not_straight_live(): void
    {
        [$brand, $campaign] = $this->brandWithCampaign();

        $this->assertSame(
            [CampaignLifecycle::PENDING_REVIEW, CampaignLifecycle::CANCELLED],
            $this->lifecycle()->availableTo($campaign),
        );

        $this->expectException(ValidationException::class);

        $this->lifecycle()->transition($campaign, $brand, CampaignLifecycle::PUBLISHED);
    }

    public function test_a_brand_cannot_publish_its_own_campaign_out_of_review(): void
    {
        [$brand, $campaign] = $this->brandWithCampaign(CampaignLifecycle::PENDING_REVIEW);

        // That would be approving yourself.
        $this->expectException(ValidationException::class);

        $this->lifecycle()->transition($campaign, $brand, CampaignLifecycle::PUBLISHED);
    }

    public function test_a_reviewer_publishes_it(): void
    {
        [, $campaign] = $this->brandWithCampaign(CampaignLifecycle::PENDING_REVIEW);
        $reviewer = $this->member('operations');

        $campaign = $this->lifecycle()->review($campaign, $reviewer, CampaignLifecycle::PUBLISHED);

        $this->assertSame(CampaignLifecycle::PUBLISHED, $campaign->status);
        $this->assertNotNull($campaign->published_at);
    }

    public function test_a_reviewer_can_send_it_back_instead_of_cancelling_it(): void
    {
        [, $campaign] = $this->brandWithCampaign(CampaignLifecycle::PENDING_REVIEW);
        $reviewer = $this->member('operations');

        // Sending back leaves the brand able to fix and resubmit; cancelling
        // forecloses that.
        $campaign = $this->lifecycle()->review($campaign, $reviewer, CampaignLifecycle::DRAFT, 'Budget is missing.');

        $this->assertSame(CampaignLifecycle::DRAFT, $campaign->status);
    }

    public function test_a_published_campaign_can_be_paused_and_resumed(): void
    {
        [$brand, $campaign] = $this->brandWithCampaign(CampaignLifecycle::PUBLISHED);

        $campaign = $this->lifecycle()->transition($campaign, $brand, CampaignLifecycle::PAUSED);
        $this->assertSame(CampaignLifecycle::PAUSED, $campaign->status);

        $campaign = $this->lifecycle()->transition($campaign, $brand, CampaignLifecycle::PUBLISHED);
        $this->assertSame(CampaignLifecycle::PUBLISHED, $campaign->status);
    }

    public function test_reopening_does_not_rewrite_when_it_first_went_live(): void
    {
        [$brand, $campaign] = $this->brandWithCampaign(CampaignLifecycle::PENDING_REVIEW);
        $reviewer = $this->member('operations');

        $campaign = $this->lifecycle()->review($campaign, $reviewer, CampaignLifecycle::PUBLISHED);
        $firstPublished = $campaign->published_at;

        $this->travel(2)->days();

        $campaign = $this->lifecycle()->transition($campaign, $brand, CampaignLifecycle::CLOSED);
        $campaign = $this->lifecycle()->transition($campaign, $brand, CampaignLifecycle::PUBLISHED);

        $this->assertTrue($firstPublished->equalTo($campaign->published_at));
        // Reopening clears the close, because it is open again.
        $this->assertNull($campaign->closed_at);
    }

    public function test_a_completed_campaign_is_the_end_of_it(): void
    {
        [$brand, $campaign] = $this->brandWithCampaign(CampaignLifecycle::CLOSED);

        $campaign = $this->lifecycle()->transition($campaign, $brand, CampaignLifecycle::COMPLETED);

        $this->assertSame([], $this->lifecycle()->availableTo($campaign));
    }

    /* -------------------------------------------------------------- closing */

    public function test_closing_tells_everyone_still_waiting(): void
    {
        [$brand, $campaign] = $this->brandWithCampaign(CampaignLifecycle::PUBLISHED);

        $creator = $this->member('creator');
        $profile = app(CreatorOnboardingService::class)->provision($creator->id, 'Mira');

        $application = CampaignApplication::query()->create([
            'campaign_id' => $campaign->id,
            'creator_profile_id' => $profile->id,
            'status' => 'applied',
            'proposed_fee_minor' => 500000,
            'message' => 'Keen.',
        ]);

        $this->lifecycle()->close($campaign, $brand);

        // Leaving it pending forever is the cruellest default: the applicant
        // never learns the answer was no.
        $this->assertSame('rejected', $application->fresh()->status);
    }

    public function test_closing_leaves_a_decided_application_alone(): void
    {
        [$brand, $campaign] = $this->brandWithCampaign(CampaignLifecycle::PUBLISHED);

        $creator = $this->member('creator');
        $profile = app(CreatorOnboardingService::class)->provision($creator->id, 'Mira');

        $accepted = CampaignApplication::query()->create([
            'campaign_id' => $campaign->id,
            'creator_profile_id' => $profile->id,
            'status' => 'accepted',
            'proposed_fee_minor' => 500000,
            'message' => 'Booked.',
        ]);

        $this->lifecycle()->close($campaign, $brand);

        $this->assertSame('accepted', $accepted->fresh()->status);
    }

    /* -------------------------------------------------------- authorisation */

    public function test_another_brand_cannot_move_your_campaign(): void
    {
        [, $campaign] = $this->brandWithCampaign(CampaignLifecycle::PUBLISHED);
        [$stranger] = $this->brandWithCampaign();

        $this->actingAs($stranger)
            ->post(route('app.campaigns.transition', $campaign), ['status' => 'paused'])
            ->assertNotFound();

        $this->assertSame(CampaignLifecycle::PUBLISHED, $campaign->fresh()->status);
    }

    public function test_another_brand_cannot_read_your_applicants(): void
    {
        [, $campaign] = $this->brandWithCampaign(CampaignLifecycle::PUBLISHED);
        [$stranger] = $this->brandWithCampaign();

        $this->actingAs($stranger)
            ->get(route('app.campaigns.applicants', $campaign))
            ->assertNotFound();
    }

    /* ------------------------------------------------------------ portfolio */

    private function creatorWithPortfolio(): User
    {
        $user = $this->member('creator');
        app(CreatorOnboardingService::class)->provision($user->id, 'Mira Rao');

        return $user->fresh();
    }

    public function test_portfolio_items_can_be_added_edited_and_removed(): void
    {
        $user = $this->creatorWithPortfolio();

        $this->actingAs($user)
            ->post(route('app.portfolio.store'), ['title' => 'A reel'])
            ->assertRedirect();

        $item = PortfolioItem::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('app.portfolio.update', $item), ['title' => 'A better reel'])
            ->assertRedirect();

        $this->assertSame('A better reel', $item->fresh()->title);

        $this->actingAs($user)
            ->delete(route('app.portfolio.destroy', $item))
            ->assertRedirect();

        $this->assertSame(0, PortfolioItem::query()->count());
    }

    public function test_new_items_go_last(): void
    {
        $user = $this->creatorWithPortfolio();

        foreach (['First', 'Second', 'Third'] as $title) {
            $this->actingAs($user)->post(route('app.portfolio.store'), ['title' => $title]);
        }

        $titles = PortfolioItem::query()->orderBy('sort_order')->pluck('title')->all();

        // Where somebody adding one expects to find it.
        $this->assertSame(['First', 'Second', 'Third'], $titles);
    }

    public function test_the_order_can_be_changed(): void
    {
        $user = $this->creatorWithPortfolio();

        foreach (['First', 'Second', 'Third'] as $title) {
            $this->actingAs($user)->post(route('app.portfolio.store'), ['title' => $title]);
        }

        $ids = PortfolioItem::query()->orderBy('sort_order')->pluck('id')->all();

        $this->actingAs($user)
            ->post(route('app.portfolio.reorder'), ['order' => [$ids[2], $ids[0], $ids[1]]])
            ->assertRedirect();

        $this->assertSame(
            ['Third', 'First', 'Second'],
            PortfolioItem::query()->orderBy('sort_order')->pluck('title')->all(),
        );
    }

    public function test_you_cannot_reorder_somebody_elses_items(): void
    {
        $mine = $this->creatorWithPortfolio();
        $theirs = $this->creatorWithPortfolio();

        $this->actingAs($theirs)->post(route('app.portfolio.store'), ['title' => 'Theirs']);
        $theirItem = PortfolioItem::query()->firstOrFail();
        $before = $theirItem->sort_order;

        $this->actingAs($mine)->post(route('app.portfolio.store'), ['title' => 'Mine']);

        // The id is in the list, but the query is scoped to the caller's own
        // items, so it matches nothing and their order is untouched.
        $this->actingAs($mine)
            ->post(route('app.portfolio.reorder'), ['order' => [$theirItem->id]])
            ->assertRedirect();

        $this->assertSame($before, $theirItem->fresh()->sort_order);
    }

    public function test_you_cannot_edit_or_delete_somebody_elses_item(): void
    {
        $mine = $this->creatorWithPortfolio();
        $theirs = $this->creatorWithPortfolio();

        $this->actingAs($theirs)->post(route('app.portfolio.store'), ['title' => 'Theirs']);
        $item = PortfolioItem::query()->firstOrFail();

        $this->actingAs($mine)
            ->post(route('app.portfolio.update', $item), ['title' => 'Hijacked'])
            ->assertNotFound();

        $this->actingAs($mine)
            ->delete(route('app.portfolio.destroy', $item))
            ->assertNotFound();

        $this->assertSame('Theirs', $item->fresh()->title);
    }

    public function test_an_uploaded_file_is_checked_before_it_is_stored(): void
    {
        Storage::fake('local');

        $user = $this->creatorWithPortfolio();

        $this->actingAs($user)
            ->post(route('app.portfolio.store'), [
                'title' => 'Shell',
                'file' => UploadedFile::fake()->createWithContent('x.php', '<?php'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, PortfolioItem::query()->count());
    }
}
