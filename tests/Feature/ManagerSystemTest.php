<?php

namespace Tests\Feature;

use App\Models\ManagerAssignment;
use App\Models\ManagerInvitation;
use App\Models\Role;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use App\Services\Managers\ManagerDirectory;
use App\Services\Workspace\WorkspaceContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ManagerSystemTest extends TestCase
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

    private function invite(User $owner, string $email, string $scope = 'creator'): ManagerInvitation
    {
        return app(ManagerDirectory::class)->invite($owner, $scope, ['email' => $email, 'name' => 'Manager Person']);
    }

    public function test_nobody_can_sign_up_as_a_manager(): void
    {
        // A manager exists only because an account holder appointed them.
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Wannabe',
            'email' => 'wannabe@test.com',
            'mobile' => '9000000001',
            'role' => 'manager',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->assertDatabaseMissing('users', ['email' => 'wannabe@test.com']);
    }

    public function test_an_invited_stranger_sets_a_password_and_becomes_a_manager(): void
    {
        $owner = $this->creator();
        $invitation = $this->invite($owner, 'newmanager@test.com');

        $this->get('/manager/invite/'.$invitation->token)
            ->assertOk()
            ->assertSee('Choose a password', false);

        $this->post('/manager/invite/'.$invitation->token, [
            'name' => 'New Manager',
            'mobile' => '9000000002',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect(route('dashboard'));

        $manager = User::query()->where('email', 'newmanager@test.com')->first();
        $this->assertNotNull($manager);
        // Following the emailed token proves control of the address.
        $this->assertTrue($manager->hasVerifiedEmail());
        $this->assertContains('manager', $manager->roleSlugs());
        $this->assertDatabaseHas('manager_assignments', [
            'owner_user_id' => $owner->id,
            'manager_user_id' => $manager->id,
            'scope' => 'creator',
            'status' => 'active',
        ]);
    }

    public function test_an_invitation_link_cannot_take_over_an_existing_account(): void
    {
        $owner = $this->creator();
        $existing = User::factory()->create(['email' => 'already@test.com']);
        $originalPassword = $existing->password;

        $invitation = $this->invite($owner, 'already@test.com');

        $this->get('/manager/invite/'.$invitation->token)
            ->assertOk()
            ->assertSee('Sign in to accept', false);

        // Posting a new password must not reset theirs.
        $this->post('/manager/invite/'.$invitation->token, [
            'name' => 'Impostor',
            'password' => 'Attacker123',
            'password_confirmation' => 'Attacker123',
        ])->assertRedirect(route('login'));

        $this->assertSame($originalPassword, $existing->fresh()->password);
        $this->assertDatabaseCount('manager_assignments', 0);
    }

    public function test_an_expired_invitation_is_refused(): void
    {
        $owner = $this->creator();
        $invitation = $this->invite($owner, 'late@test.com');
        $invitation->update(['expires_at' => now()->subDay()]);

        $this->get('/manager/invite/'.$invitation->token)
            ->assertOk()
            ->assertSee('not valid', false);

        $this->post('/manager/invite/'.$invitation->token, [
            'name' => 'Late', 'password' => 'Password123', 'password_confirmation' => 'Password123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['email' => 'late@test.com']);
    }

    public function test_a_manager_can_only_act_for_accounts_they_are_assigned_to(): void
    {
        $owner = $this->creator();
        $stranger = $this->creator();
        $manager = User::factory()->create(['email_verified_at' => now()]);

        ManagerAssignment::query()->create([
            'owner_user_id' => $owner->id,
            'manager_user_id' => $manager->id,
            'scope' => 'creator',
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $context = app(WorkspaceContext::class);
        $this->assertTrue($context->canActFor($manager, $owner->id, 'creator'));
        // Right manager, wrong account.
        $this->assertFalse($context->canActFor($manager, $stranger->id, 'creator'));
        // Right account, wrong side of it.
        $this->assertFalse($context->canActFor($manager, $owner->id, 'brand'));
    }

    public function test_switching_to_an_unassigned_account_is_forbidden(): void
    {
        $stranger = $this->creator();
        $manager = User::factory()->create(['email_verified_at' => now()]);
        $manager->roles()->attach(Role::query()->where('slug', 'manager')->first());

        $this->actingAs($manager)
            ->post(route('workspace.manage'), ['owner_user_id' => $stranger->id, 'scope' => 'creator'])
            ->assertStatus(403);

        $this->assertNull(session(WorkspaceContext::ACTING_USER));
    }

    public function test_revoking_access_stops_the_manager_on_the_next_request(): void
    {
        $owner = $this->creator();
        $manager = User::factory()->create(['email_verified_at' => now()]);
        $manager->roles()->attach(Role::query()->where('slug', 'manager')->first());

        $assignment = ManagerAssignment::query()->create([
            'owner_user_id' => $owner->id,
            'manager_user_id' => $manager->id,
            'scope' => 'creator',
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $this->actingAs($manager)
            ->post(route('workspace.manage'), ['owner_user_id' => $owner->id, 'scope' => 'creator'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame($owner->id, session(WorkspaceContext::ACTING_USER));

        app(ManagerDirectory::class)->revoke($owner, $assignment);

        // The session still names the account; hydrate must drop it.
        app(WorkspaceContext::class)->hydrate($manager->fresh());
        $this->assertNull(session(WorkspaceContext::ACTING_USER));
    }

    public function test_an_owner_cannot_revoke_somebody_elses_manager(): void
    {
        $owner = $this->creator();
        $other = $this->creator();
        $manager = User::factory()->create();

        $assignment = ManagerAssignment::query()->create([
            'owner_user_id' => $owner->id,
            'manager_user_id' => $manager->id,
            'scope' => 'creator',
            'status' => 'active',
        ]);

        $this->expectException(HttpException::class);
        app(ManagerDirectory::class)->revoke($other, $assignment);
    }

    public function test_you_cannot_delegate_an_account_you_do_not_have(): void
    {
        $owner = $this->creator();   // creator only, no brand profile

        $this->expectException(ValidationException::class);
        app(ManagerDirectory::class)->invite($owner, 'brand', ['email' => 'someone@test.com']);
    }

    public function test_you_cannot_appoint_yourself(): void
    {
        $owner = $this->creator();

        $this->expectException(ValidationException::class);
        $this->invite($owner, $owner->email);
    }

    public function test_the_switcher_lists_the_own_account_and_every_managed_one(): void
    {
        $owner = $this->creator();
        $manager = User::factory()->create(['name' => 'Manager Person']);

        ManagerAssignment::query()->create([
            'owner_user_id' => $owner->id,
            'manager_user_id' => $manager->id,
            'scope' => 'creator',
            'status' => 'active',
            'source' => 'company',
        ]);

        $accounts = app(WorkspaceContext::class)->switchableAccounts($manager);

        $this->assertCount(2, $accounts);
        $this->assertTrue($accounts[0]['is_self']);
        $this->assertSame($owner->id, $accounts[1]['owner_user_id']);
        $this->assertTrue($accounts[1]['company_provided']);
        $this->assertStringContainsString('provided by Vidlix', $accounts[1]['sublabel']);
    }

    public function test_a_revoked_assignment_disappears_from_the_switcher(): void
    {
        $owner = $this->creator();
        $manager = User::factory()->create();

        $assignment = ManagerAssignment::query()->create([
            'owner_user_id' => $owner->id,
            'manager_user_id' => $manager->id,
            'scope' => 'creator',
            'status' => 'active',
        ]);

        $this->assertCount(2, app(WorkspaceContext::class)->switchableAccounts($manager));

        app(ManagerDirectory::class)->revoke($owner, $assignment);

        $this->assertCount(1, app(WorkspaceContext::class)->switchableAccounts($manager->fresh()));
    }
}
