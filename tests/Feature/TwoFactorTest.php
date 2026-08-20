<?php

namespace Tests\Feature;

use App\Models\TwoFactorRecoveryCode;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * A second factor that actually stands between the password and a session.
 */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create([
            'email' => 'member@example.test',
            'email_verified_at' => now(),
            'password' => bcrypt('correct-horse-battery'),
            'status' => 'active',
        ]);
    }

    private function currentCode(User $user): string
    {
        $secret = Crypt::decryptString($user->fresh()->two_factor_secret);

        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    public function test_enrolment_is_not_in_force_until_a_code_is_confirmed(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post(route('app.two-factor.begin'))->assertRedirect();

        // A secret exists, but the account is not protected yet - otherwise a
        // half-finished set-up would lock somebody out of their own account.
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse(app(TwoFactorService::class)->isEnabled($user));
    }

    public function test_confirming_turns_it_on_and_issues_recovery_codes(): void
    {
        $user = $this->member();
        app(TwoFactorService::class)->beginEnrolment($user);

        $this->actingAs($user)
            ->post(route('app.two-factor.confirm'), ['code' => $this->currentCode($user)])
            ->assertRedirect()
            ->assertSessionHas('two_factor.codes');

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertSame(8, app(TwoFactorService::class)->unusedRecoveryCodeCount($user->fresh()));
    }

    public function test_a_wrong_code_does_not_turn_it_on(): void
    {
        $user = $this->member();
        app(TwoFactorService::class)->beginEnrolment($user);

        $this->from(route('app.two-factor'))
            ->actingAs($user)
            ->post(route('app.two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_signing_in_stops_at_the_challenge_and_does_not_log_you_in(): void
    {
        $user = $this->member();
        $service = app(TwoFactorService::class);
        $service->beginEnrolment($user);
        $service->confirm($user->fresh(), $this->currentCode($user));

        $this->post(route('login'), ['login' => 'member@example.test', 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('two-factor.challenge'));

        // The password was right, so this is the whole point: still a guest.
        $this->assertGuest();
    }

    public function test_the_right_code_completes_the_sign_in(): void
    {
        $user = $this->member();
        $service = app(TwoFactorService::class);
        $service->beginEnrolment($user);
        $service->confirm($user->fresh(), $this->currentCode($user));

        $this->post(route('login'), ['login' => 'member@example.test', 'password' => 'correct-horse-battery']);
        $this->post(route('two-factor.verify'), ['code' => $this->currentCode($user)])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_a_recovery_code_works_once_and_then_never_again(): void
    {
        $user = $this->member();
        $service = app(TwoFactorService::class);
        $service->beginEnrolment($user);
        $codes = $service->confirm($user->fresh(), $this->currentCode($user));
        $code = $codes[0];

        $this->assertTrue($service->verify($user->fresh(), $code));
        $this->assertFalse($service->verify($user->fresh(), $code));

        $this->assertNotNull(TwoFactorRecoveryCode::query()->whereNotNull('used_at')->first());
    }

    public function test_recovery_codes_are_not_stored_in_the_clear(): void
    {
        $user = $this->member();
        $service = app(TwoFactorService::class);
        $service->beginEnrolment($user);
        $codes = $service->confirm($user->fresh(), $this->currentCode($user));

        // A leaked database must not hand somebody a working second factor.
        $this->assertDatabaseMissing('two_factor_recovery_codes', ['code_hash' => $codes[0]]);
    }

    public function test_turning_it_off_needs_the_password_again(): void
    {
        $user = $this->member();
        $service = app(TwoFactorService::class);
        $service->beginEnrolment($user);
        $service->confirm($user->fresh(), $this->currentCode($user));

        $this->from(route('app.two-factor'))
            ->actingAs($user->fresh())
            ->delete(route('app.two-factor.disable'), ['password' => 'not-my-password'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        $this->actingAs($user->fresh())
            ->delete(route('app.two-factor.disable'), ['password' => 'correct-horse-battery'])
            ->assertRedirect();

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_the_challenge_is_not_reachable_without_passing_a_password(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    }
}
