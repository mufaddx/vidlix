<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeAbility;
use App\Models\Role;
use App\Models\SupportThread;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Creator\CreatorOnboardingService;
use App\Services\Support\HelpDesk;
use App\Support\Ability;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach(Role::query()->where('slug', 'super_admin')->first());

        return $user->fresh();
    }

    private function employeeWith(array $abilities, string $status = 'active'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_code' => Employee::generateCode(),
            'status' => $status,
            'joined_at' => now(),
        ]);
        foreach ($abilities as $ability) {
            EmployeeAbility::query()->create(['employee_id' => $employee->id, 'ability' => $ability]);
        }

        return $user->fresh();
    }

    public function test_a_support_employee_cannot_approve_a_payout(): void
    {
        // This is the whole point of the ability system: before it, anybody who
        // could reach the admin panel could move real money.
        $support = $this->employeeWith([Ability::SUPPORT_VIEW, Ability::SUPPORT_REPLY]);
        $member = User::factory()->create();
        $withdrawal = Withdrawal::query()->create([
            'user_id' => $member->id,
            'amount_minor' => 500000,
            'currency' => 'INR',
            'status' => 'requested',
        ]);

        $this->actingAs($support)->get('/admin/help-desk')->assertOk();

        $this->actingAs($support)->get('/admin/finance')->assertStatus(403);
        $this->actingAs($support)
            ->post('/admin/withdrawals/'.$withdrawal->id, ['decision' => 'approve'])
            ->assertStatus(403);

        $this->assertSame('requested', $withdrawal->fresh()->status);
    }

    public function test_a_content_employee_cannot_reach_verification_or_finance(): void
    {
        $content = $this->employeeWith([Ability::CMS_MANAGE]);

        $this->actingAs($content)->get('/admin/cms')->assertOk();
        $this->actingAs($content)->get('/admin/verification')->assertStatus(403);
        $this->actingAs($content)->get('/admin/finance')->assertStatus(403);
        $this->actingAs($content)->get('/admin/employees')->assertStatus(403);
    }

    public function test_a_super_admin_holds_every_ability(): void
    {
        $admin = $this->superAdmin();

        foreach (['/admin/help-desk', '/admin/verification', '/admin/finance', '/admin/employees', '/admin/managers'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_a_non_staff_member_cannot_reach_the_panel_at_all(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($member)->get('/admin/help-desk')->assertStatus(403);
        $this->actingAs($member)->get('/admin')->assertStatus(403);
    }

    public function test_a_suspended_employee_keeps_their_grants_but_can_use_none_of_them(): void
    {
        $employee = $this->employeeWith([Ability::SUPPORT_VIEW], 'suspended');

        $this->actingAs($employee)->get('/admin/help-desk')->assertStatus(403);
        // The grant is still recorded, so reactivating restores it.
        $this->assertDatabaseHas('employee_abilities', ['ability' => Ability::SUPPORT_VIEW]);
    }

    public function test_employees_manage_cannot_be_granted_to_an_ordinary_employee(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/admin/employees', [
            'name' => 'Sneaky',
            'email' => 'sneaky@vidlix.test',
            'password' => 'Sup3rSecret!Pass',
            'password_confirmation' => 'Sup3rSecret!Pass',
            'abilities' => [Ability::EMPLOYEES_MANAGE],
        ])->assertSessionHasErrors('abilities.0');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@vidlix.test']);
    }

    public function test_creating_an_employee_generates_a_code_and_grants_only_what_was_ticked(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/admin/employees', [
            'name' => 'Support Person',
            'email' => 'support1@vidlix.test',
            'title' => 'Support Executive',
            'password' => 'Sup3rSecret!Pass',
            'password_confirmation' => 'Sup3rSecret!Pass',
            'abilities' => [Ability::SUPPORT_VIEW, Ability::SUPPORT_REPLY],
        ])->assertRedirect();

        $employee = Employee::query()->whereHas('user', fn ($q) => $q->where('email', 'support1@vidlix.test'))->firstOrFail();
        $this->assertMatchesRegularExpression('/^VX-\d{2}-[A-Z0-9]{5}$/', $employee->employee_code);
        $this->assertEqualsCanonicalizing(
            [Ability::SUPPORT_VIEW, Ability::SUPPORT_REPLY],
            $employee->abilityList(),
        );
        $this->assertFalse($employee->can(Ability::FINANCE_APPROVE_PAYOUTS));
    }

    public function test_changing_abilities_replaces_rather_than_appends(): void
    {
        $admin = $this->superAdmin();
        $staff = $this->employeeWith([Ability::SUPPORT_VIEW, Ability::FINANCE_VIEW]);
        $employee = $staff->employee;

        $this->actingAs($admin)
            ->post('/admin/employees/'.$employee->id.'/abilities', ['abilities' => [Ability::CMS_MANAGE]])
            ->assertRedirect();

        $this->assertSame([Ability::CMS_MANAGE], $employee->fresh()->abilityList());
        // Revoked immediately, not at their next login.
        $this->actingAs($staff->fresh())->get('/admin/help-desk')->assertStatus(403);
    }

    public function test_a_help_desk_thread_can_be_answered_and_the_reply_is_never_claimed_as_sent(): void
    {
        $admin = $this->superAdmin();
        $member = User::factory()->create(['email_verified_at' => now()]);

        $thread = app(HelpDesk::class)->openFromMember($member, 'Cannot connect Instagram', 'It keeps failing.');

        $this->actingAs($admin)->get('/admin/help-desk/'.$thread->id)->assertOk()->assertSee('It keeps failing.', false);

        $this->actingAs($admin)
            ->post('/admin/help-desk/'.$thread->id.'/reply', ['body' => 'Try reconnecting from Settings.'])
            ->assertRedirect();

        // No provider configured in tests, so it must say exactly that.
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $thread->conversation_id,
            'direction' => 'outbound',
            'delivery_status' => 'provider_not_configured',
        ]);
        $this->assertSame('pending', $thread->fresh()->status);
    }

    public function test_read_only_support_staff_cannot_reply(): void
    {
        $reader = $this->employeeWith([Ability::SUPPORT_VIEW]);
        $member = User::factory()->create();
        $thread = app(HelpDesk::class)->openFromMember($member, 'Question', 'Hello?');

        $this->actingAs($reader)->get('/admin/help-desk/'.$thread->id)->assertOk();
        $this->actingAs($reader)
            ->post('/admin/help-desk/'.$thread->id.'/reply', ['body' => 'Hi'])
            ->assertStatus(403);
    }

    public function test_mail_to_the_help_desk_opens_a_thread_instead_of_going_unmatched(): void
    {
        config([
            'vidlix.email.inbound_domain' => 'vidlix.in',
            'vidlix.webhooks.email_secret' => 'mail-secret',
            'vidlix.webhooks.schemes.email' => 'hmac_hex',
        ]);

        $payload = [
            'id' => 'help-1',
            'from' => 'Stranger <stranger@somewhere.test>',
            'to' => 'help@vidlix.in',
            'subject' => 'I cannot log in',
            'text' => 'My password reset never arrives.',
        ];
        $body = json_encode($payload);

        $this->call('POST', '/webhooks/email/inbound', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $body, 'mail-secret'),
        ], $body)->assertOk()->assertJsonPath('outcome', 'help_desk');

        $thread = SupportThread::query()->firstOrFail();
        $this->assertSame('support', $thread->conversation->channel);
        $this->assertDatabaseHas('inbound_email_events', ['match_status' => 'help_desk']);
        // A stranger, not a member — so no account is invented for them.
        $this->assertNull($thread->user_id);
    }

    public function test_a_reply_to_the_help_desk_joins_the_senders_existing_thread(): void
    {
        config([
            'vidlix.email.inbound_domain' => 'vidlix.in',
            'vidlix.email.api_key' => 're_test',
            'vidlix.webhooks.email_secret' => 'whsec_'.base64_encode(str_repeat('k', 24)),
            'vidlix.webhooks.schemes.email' => 'svix',
        ]);

        $member = User::factory()->create(['email' => 'writer@outside.test']);
        $thread = app(HelpDesk::class)->openFromMember($member, 'First question', 'Original message.');

        // Resend sends metadata only; the body comes from the receiving API.
        Http::fake([
            'api.resend.com/emails/receiving/*' => Http::response([
                'id' => 'inbound-1',
                'from' => 'Writer <writer@outside.test>',
                'to' => ['help@vidlix.in'],
                'received_for' => ['help@vidlix.in'],
                'subject' => 'Re: First question',
                'text' => 'Thanks, that fixed it.',
                'message_id' => '<abc@mail.gmail.com>',
                'headers' => ['in-reply-to' => '<xyz@vidlix.in>'],
            ], 200),
        ]);

        $this->postSvix(['type' => 'email.received', 'data' => ['email_id' => 'inbound-1']])
            ->assertOk()
            ->assertJsonPath('outcome', 'help_desk_reply');

        // Appended to the same thread, not a duplicate one.
        $this->assertSame(1, SupportThread::query()->count());
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $thread->conversation_id,
            'direction' => 'inbound',
            'body' => 'Thanks, that fixed it.',
        ]);
        // Their reply reopens it for staff.
        $this->assertSame('open', $thread->fresh()->status);
    }

    public function test_a_received_email_whose_body_cannot_be_fetched_is_not_stored_empty(): void
    {
        config([
            'vidlix.email.inbound_domain' => 'vidlix.in',
            'vidlix.email.api_key' => 're_test',
            'vidlix.webhooks.email_secret' => 'whsec_'.base64_encode(str_repeat('k', 24)),
            'vidlix.webhooks.schemes.email' => 'svix',
        ]);

        Http::fake([
            'api.resend.com/emails/receiving/*' => Http::response(['message' => 'not found'], 404),
        ]);

        $this->postSvix(['type' => 'email.received', 'data' => ['email_id' => 'missing-1']])
            ->assertOk()
            ->assertJsonPath('outcome', 'fetch_failed');

        // The provider keeps the message and retries, so storing a blank one
        // would be worse than storing nothing.
        $this->assertDatabaseCount('support_threads', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    /** Signs a payload the way Svix does, so the webhook accepts it. */
    private function postSvix(array $payload)
    {
        $secret = (string) config('vidlix.webhooks.email_secret');
        $body = json_encode($payload);
        $id = 'msg_'.bin2hex(random_bytes(6));
        $ts = time();
        $signature = base64_encode(hash_hmac('sha256', $id.'.'.$ts.'.'.$body, base64_decode(substr($secret, 6)), true));

        return $this->call('POST', '/webhooks/email/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => $id,
            'HTTP_SVIX_TIMESTAMP' => (string) $ts,
            'HTTP_SVIX_SIGNATURE' => 'v1,'.$signature,
        ], $body);
    }

    public function test_a_company_provided_manager_is_labelled_as_such(): void
    {
        $admin = $this->superAdmin();
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $owner->roles()->attach(Role::query()->where('slug', 'creator')->first());
        app(CreatorOnboardingService::class)->provision($owner->id, $owner->name);

        $this->actingAs($admin)->post('/admin/managers/assign', [
            'owner_email' => $owner->email,
            'manager_email' => 'companymanager@vidlix.test',
            'scope' => 'creator',
            'manager_name' => 'Company Manager',
        ])->assertRedirect();

        $this->assertDatabaseHas('manager_invitations', [
            'email' => 'companymanager@vidlix.test',
            'source' => 'company',
            'scope' => 'creator',
        ]);
    }
}
