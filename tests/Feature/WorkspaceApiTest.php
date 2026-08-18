<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use App\Services\Ledger\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    private function creator(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'creator'], ['name' => 'Creator']));
        app(CreatorOnboardingService::class)->provision($user->id, $user->name);

        return $user->fresh();
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('api')->plainTextToken;
    }

    public function test_the_workspace_endpoints_require_a_token(): void
    {
        foreach (['/api/v1/projects', '/api/v1/earnings', '/api/v1/invoices', '/api/v1/managers', '/api/v1/instagram'] as $uri) {
            $this->getJson($uri)->assertStatus(401);
        }
    }

    public function test_earnings_are_derived_from_ledger_entries(): void
    {
        $user = $this->creator();
        $ledger = app(LedgerService::class);
        $ledger->append($user->id, 'earnings', LedgerService::STATE_AVAILABLE, 250000, 'INR');
        $ledger->append($user->id, 'earnings', LedgerService::STATE_RESERVED, 90000, 'INR');

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/earnings')
            ->assertOk()
            ->assertJsonPath('data.available_minor', 250000)
            ->assertJsonPath('data.reserved_minor', 90000)
            ->assertJsonPath('data.payout_provider_configured', false);
    }

    public function test_a_user_only_sees_their_own_projects(): void
    {
        $mine = $this->creator();
        $other = $this->creator();

        Project::query()->create([
            'name' => 'Mine', 'status' => 'draft', 'total_amount_minor' => 1000,
            'advance_amount_minor' => 0, 'revision_limit' => 2,
            'owner_user_id' => $mine->id, 'counterparty_user_id' => $other->id,
        ]);
        $theirs = Project::query()->create([
            'name' => 'Not mine', 'status' => 'draft', 'total_amount_minor' => 1000,
            'advance_amount_minor' => 0, 'revision_limit' => 2,
            'owner_user_id' => $other->id, 'counterparty_user_id' => $other->id,
        ]);

        $this->withToken($this->tokenFor($mine))
            ->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');

        $stranger = $this->creator();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($stranger))
            ->getJson('/api/v1/projects/'.$theirs->id)
            ->assertStatus(404);
    }

    public function test_instagram_status_reports_unconfigured_without_inventing_numbers(): void
    {
        $user = $this->creator();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/instagram')
            ->assertOk()
            ->assertJsonPath('data.provider_configured', false)
            ->assertJsonPath('data.insights', [])
            ->assertJsonPath('data.connect_url', null);
    }

    public function test_a_device_can_register_for_push_and_is_told_push_is_off(): void
    {
        $user = $this->creator();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/devices', ['token' => 'fcm-token-1', 'platform' => 'android'])
            ->assertStatus(201)
            ->assertJsonPath('data.push_provider_configured', false);

        $this->assertDatabaseHas('device_tokens', ['user_id' => $user->id, 'platform' => 'android']);
    }

    public function test_a_payment_status_read_never_reports_success_from_a_browser_return(): void
    {
        $user = $this->creator();
        $other = $this->creator();
        $project = Project::query()->create([
            'name' => 'Reels', 'status' => 'awaiting_advance', 'total_amount_minor' => 50000,
            'advance_amount_minor' => 50000, 'revision_limit' => 2,
            'owner_user_id' => $user->id, 'counterparty_user_id' => $other->id,
        ]);
        $payment = Payment::query()->create([
            'payment_uuid' => 'uuid-status-1',
            'status' => 'pending',
            'amount_minor' => 50000,
            'currency' => 'INR',
            'payer_user_id' => $user->id,
            'payable_type' => Project::class,
            'payable_id' => $project->id,
        ]);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/payments/'.$payment->payment_uuid)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        // The guard caches its resolved user within a test, so clear it before
        // asserting that a different account cannot read the same payment.
        $this->app['auth']->forgetGuards();

        $this->withToken($this->tokenFor($other))
            ->getJson('/api/v1/payments/'.$payment->payment_uuid)
            ->assertStatus(404);
    }
}
