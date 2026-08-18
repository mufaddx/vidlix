<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders the pages the integration work touched, so a Blade or view-data
 * regression fails here rather than in front of a user.
 */
class WorkspacePagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function creator(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach(Role::query()->where('slug', 'creator')->first());
        app(CreatorOnboardingService::class)->provision($user->id, $user->name);

        return $user->fresh();
    }

    public function test_the_instagram_page_renders_without_calling_meta(): void
    {
        $this->actingAs($this->creator())
            ->get('/instagram')
            ->assertOk()
            ->assertSee('the Meta app credentials are not configured', false)
            ->assertSee('nothing is estimated or invented', false);
    }

    public function test_the_earnings_page_renders_ledger_derived_totals(): void
    {
        $this->actingAs($this->creator())
            ->get('/earnings')
            ->assertOk()
            ->assertSee('Ledger', false);
    }

    public function test_the_admin_finance_page_offers_no_way_to_mark_a_payout_paid(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->roles()->attach(Role::query()->where('slug', 'super_admin')->first());

        $this->actingAs($admin)
            ->get('/admin/finance')
            ->assertOk()
            ->assertDontSee('value="paid"', false);
    }
}
