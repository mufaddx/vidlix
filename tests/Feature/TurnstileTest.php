<?php

namespace Tests\Feature;

use App\Services\Security\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The widget on the page proves nothing; the token is checked server side.
 */
class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    public function test_with_no_keys_the_check_is_skipped_rather_than_failed(): void
    {
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);

        // Failing closed here would take every public form down the moment
        // this shipped, on a site whose keys are set by hand.
        $result = app(Turnstile::class)->verify(null);

        $this->assertTrue($result['passed']);
        $this->assertSame('not_configured', $result['reason']);
    }

    public function test_a_configured_site_rejects_a_missing_token_without_asking_cloudflare(): void
    {
        config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret_key' => 'secret']);
        Http::fake();

        $result = app(Turnstile::class)->verify(null);

        $this->assertFalse($result['passed']);
        $this->assertSame('missing_token', $result['reason']);
        Http::assertNothingSent();
    }

    public function test_a_token_cloudflare_rejects_does_not_pass(): void
    {
        config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret_key' => 'secret']);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]),
        ]);

        $result = app(Turnstile::class)->verify('a-token');

        $this->assertFalse($result['passed']);
        $this->assertSame('invalid-input-response', $result['reason']);
    }

    public function test_a_token_cloudflare_accepts_passes(): void
    {
        config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->assertTrue(app(Turnstile::class)->verify('a-token')['passed']);
    }

    public function test_cloudflare_being_unreachable_does_not_close_the_front_door(): void
    {
        config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret_key' => 'secret']);
        Http::fake(fn () => throw new \RuntimeException('network down'));

        $result = app(Turnstile::class)->verify('a-token');

        $this->assertTrue($result['passed']);
        $this->assertSame('verifier_unreachable', $result['reason']);
    }
}
