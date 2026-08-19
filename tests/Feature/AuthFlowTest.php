<?php

namespace Tests\Feature;

use App\Contracts\EmailProviderInterface;
use App\Models\OtpVerification;
use App\Models\User;
use App\Services\Auth\OtpService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\RecordingEmailProvider;
use Tests\TestCase;

/**
 * The codes here are real: emailed through the configured provider, stored
 * hashed, expiring, single-use and attempt-limited. These tests exist mostly to
 * prove the ways they are *refused*.
 */
class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        RecordingEmailProvider::reset();
    }

    private function configureEmail(): void
    {
        config([
            'vidlix.email.from_address' => 'noreply@vidlix.in',
            'vidlix.email.inbound_domain' => 'vidlix.in',
        ]);

        // Swap the provider rather than the HTTP layer: the code still travels
        // the real path through SystemMailer, it just lands somewhere readable.
        RecordingEmailProvider::reset();
        $this->app->instance(EmailProviderInterface::class, new RecordingEmailProvider);
    }

    /**
     * The code as the recipient would read it, taken from the message that was
     * actually sent. The stored hash cannot be reversed — that is the point of
     * storing a hash — so there is nowhere else to get it.
     */
    private function codeFor(string $identifier, string $purpose): string
    {
        $code = RecordingEmailProvider::codeFor($identifier);
        $this->assertNotNull($code, 'No code was emailed to '.$identifier);

        return $code;
    }

    public function test_no_account_exists_until_all_three_steps_are_done(): void
    {
        $this->configureEmail();

        $this->postJson(route('register.start'), [
            'name' => 'New Person',
            'mobile' => '9000000201',
            'email' => 'new@test.com',
            'role' => 'creator',
            'accepted_terms' => 1,
        ])->assertOk()->assertJsonPath('step', 2);

        // A code was sent, but there is deliberately no user row yet.
        $this->assertDatabaseMissing('users', ['email' => 'new@test.com']);
        $this->assertDatabaseCount('otp_verifications', 1);

        $this->postJson(route('register.verify'), ['code' => $this->codeFor('new@test.com', 'signup')])
            ->assertOk()->assertJsonPath('step', 3);

        $this->assertDatabaseMissing('users', ['email' => 'new@test.com']);

        $this->post(route('register.complete'), [
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'new@test.com')->firstOrFail();
        // The emailed code proved the inbox, so a second round trip is theatre.
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame(['creator'], $user->roleSlugs());
    }

    public function test_the_code_is_never_stored_in_readable_form(): void
    {
        $this->configureEmail();
        app(OtpService::class)->issue('hash@test.com', 'signup');

        $row = OtpVerification::query()->firstOrFail();
        $this->assertNotEmpty($row->code_hash);
        // A database read must not hand somebody a working code.
        $this->assertMatchesRegularExpression('/^\$2y\$/', $row->code_hash);
        $this->assertArrayNotHasKey('code_hash', $row->toArray());
    }

    public function test_a_wrong_code_is_refused_and_burns_an_attempt(): void
    {
        $this->configureEmail();
        app(OtpService::class)->issue('wrong@test.com', 'signup');

        $result = app(OtpService::class)->verify('wrong@test.com', 'signup', '000000');
        $correct = $this->codeFor('wrong@test.com', 'signup');

        // Only assert failure when we know we guessed wrong.
        if ($correct !== '000000') {
            $this->assertSame('incorrect', $result['status']);
            $this->assertSame(1, OtpVerification::query()->firstOrFail()->attempts);
        }
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $this->configureEmail();
        $otp = app(OtpService::class);
        $otp->issue('replay@test.com', 'signup');
        $code = $this->codeFor('replay@test.com', 'signup');

        $this->assertSame('verified', $otp->verify('replay@test.com', 'signup', $code)['status']);
        $this->assertSame('not_found', $otp->verify('replay@test.com', 'signup', $code)['status']);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $this->configureEmail();
        $otp = app(OtpService::class);
        $otp->issue('expired@test.com', 'signup');
        $code = $this->codeFor('expired@test.com', 'signup');

        OtpVerification::query()->update(['expires_at' => now()->subMinute()]);

        $this->assertSame('expired', $otp->verify('expired@test.com', 'signup', $code)['status']);
    }

    public function test_resending_invalidates_the_previous_code(): void
    {
        $this->configureEmail();
        $otp = app(OtpService::class);
        $otp->issue('resend@test.com', 'signup');
        $first = $this->codeFor('resend@test.com', 'signup');

        $otp->issue('resend@test.com', 'signup');

        $this->assertNotSame('verified', $otp->verify('resend@test.com', 'signup', $first)['status']);
    }

    public function test_the_password_step_cannot_be_reached_by_skipping_verification(): void
    {
        $this->configureEmail();

        $this->postJson(route('register.start'), [
            'name' => 'Skipper',
            'mobile' => '9000000202',
            'email' => 'skipper@test.com',
            'role' => 'creator',
            'accepted_terms' => 1,
        ])->assertOk();

        // Straight to step 3 without ever entering the code.
        $this->post(route('register.complete'), [
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect(route('register'));

        $this->assertDatabaseMissing('users', ['email' => 'skipper@test.com']);
    }

    public function test_signup_is_refused_without_accepting_the_terms(): void
    {
        $this->configureEmail();

        $this->postJson(route('register.start'), [
            'name' => 'No Terms',
            'mobile' => '9000000203',
            'email' => 'noterms@test.com',
            'role' => 'brand',
            'accepted_terms' => 0,
        ])->assertStatus(422);

        $this->assertDatabaseCount('otp_verifications', 0);
    }

    public function test_with_no_email_provider_signup_says_so_instead_of_showing_a_code_screen(): void
    {
        config(['vidlix.providers.email' => 'unconfigured']);

        $this->postJson(route('register.start'), [
            'name' => 'No Provider',
            'mobile' => '9000000204',
            'email' => 'noprovider@test.com',
            'role' => 'creator',
            'accepted_terms' => 1,
        ])->assertStatus(503);

        // No live code is left behind implying something was sent.
        $this->assertSame(0, OtpVerification::query()->whereNull('consumed_at')->count());
        $this->assertDatabaseMissing('users', ['email' => 'noprovider@test.com']);
    }

    public function test_password_reset_answers_the_same_whether_the_account_exists(): void
    {
        $this->configureEmail();
        User::factory()->create(['email' => 'real@test.com']);

        $known = $this->postJson(route('password.start'), ['login' => 'real@test.com'])->assertOk();
        $unknown = $this->postJson(route('password.start'), ['login' => 'nobody@test.com'])->assertOk();

        // Identical wording, so this form cannot be used to discover accounts.
        $this->assertSame($known->json('message'), $unknown->json('message'));
    }

    public function test_resetting_a_password_ends_every_existing_api_token(): void
    {
        $this->configureEmail();
        $user = User::factory()->create(['email' => 'reset@test.com']);
        $user->createToken('api');
        $this->assertSame(1, $user->tokens()->count());

        $this->postJson(route('password.start'), ['login' => 'reset@test.com'])->assertOk();
        $this->postJson(route('password.verify'), ['code' => $this->codeFor('reset@test.com', 'password_reset')])
            ->assertOk()->assertJsonPath('step', 3);

        $this->post(route('password.complete'), [
            'password' => 'BrandNew123',
            'password_confirmation' => 'BrandNew123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('BrandNew123', $user->fresh()->password));
        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_the_signup_screen_shows_terms_for_every_role(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('data-terms-for="creator"', false)
            ->assertSee('data-terms-for="editor"', false)
            ->assertSee('data-terms-for="brand"', false)
            // Role-specific wording, not one shared wall of text.
            ->assertSee('Copyright handover', false)
            ->assertSee('Usage licences', false)
            ->assertSee('Sponsored posts', false);
    }
}
