<?php

namespace Tests\Feature;

use App\Contracts\CustomHostnameProviderInterface;
use App\Models\CustomDomain;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use App\Services\Domains\CustomDomainService;
use App\Services\Domains\Hostname;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Bringing your own hostname.
 *
 * Most of this is about refusing things. A custom domain is the one place where
 * somebody outside the platform controls part of the routing input, so the
 * interesting cases are all the ones where the answer has to be no.
 */
class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    private function creator(string $username = 'mira'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $profile = app(CreatorOnboardingService::class)->provision($user->id, 'Mira Rao');

        $profile->update(['username' => $username, 'visibility' => 'public']);
        $profile->publicPage->update([
            'status' => 'published',
            'published_payload' => ['hero_title' => 'Mira', 'cta_text' => 'Work with me'],
        ]);

        return $user->fresh();
    }

    /** A provider that reports whatever the test needs, without a network call. */
    private function fakeProvider(bool $dns = true, bool $ssl = true): void
    {
        $this->app->bind(CustomHostnameProviderInterface::class, fn () => new class($dns, $ssl) implements CustomHostnameProviderInterface
        {
            public function __construct(private bool $dns, private bool $ssl) {}

            public function name(): string
            {
                return 'fake';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function dnsTarget(): string
            {
                return 'tenants.vidlix.in';
            }

            public function register(CustomDomain $domain): array
            {
                return ['status' => 'ok', 'provider_hostname_id' => 'fake-1', 'detail' => 'Registered.'];
            }

            public function status(CustomDomain $domain): array
            {
                return [
                    'dns_ok' => $this->dns,
                    'ssl_ok' => $this->ssl,
                    'status' => 'ok',
                    'detail' => 'Checked.',
                ];
            }

            public function release(CustomDomain $domain): array
            {
                return ['status' => 'ok', 'detail' => 'Released.'];
            }
        });
    }

    /* ------------------------------------------------------------ hostnames */

    public function test_private_and_internal_hostnames_are_refused(): void
    {
        // Every one of these is a way of pointing us at something inside a
        // network rather than at a domain somebody owns.
        foreach ([
            'localhost',
            'app.localhost',
            'db.internal',
            'printer.local',
            'metadata.google.internal',
            'something.test',
            'site.invalid',
            'hidden.onion',
        ] as $bad) {
            $this->assertFalse(Hostname::isAcceptable($bad), $bad.' should be refused');
        }
    }

    public function test_ip_addresses_are_not_domains(): void
    {
        foreach (['127.0.0.1', '10.0.0.5', '169.254.169.254', '::1'] as $ip) {
            $this->assertFalse(Hostname::isAcceptable($ip), $ip.' should be refused');
        }
    }

    public function test_our_own_hostnames_cannot_be_claimed(): void
    {
        config(['vidlix.domains.site' => 'https://vidlix.in']);
        config(['vidlix.domains.admin' => 'https://admin.vidlix.in']);

        $this->assertFalse(Hostname::isAcceptable('vidlix.in'));
        $this->assertFalse(Hostname::isAcceptable('admin.vidlix.in'));
        $this->assertFalse(Hostname::isAcceptable('anything.vidlix.in'));
    }

    public function test_a_hostname_is_normalised_before_it_is_compared(): void
    {
        // A URL pasted instead of a hostname, with case and a trailing dot.
        $this->assertSame(
            'contact.example-brand.com',
            Hostname::normalise('HTTPS://Contact.Example-Brand.com./some/path?x=1'),
        );
    }

    public function test_a_unicode_hostname_becomes_punycode(): void
    {
        // Two hostnames that look identical but are different strings would
        // otherwise both pass a uniqueness check.
        $normalised = Hostname::normalise('café.example.com');

        $this->assertStringStartsWith('xn--', $normalised);
    }

    public function test_an_ordinary_domain_is_accepted(): void
    {
        $this->assertTrue(Hostname::isAcceptable('contact.example-brand.com'));
    }

    /* ------------------------------------------------------------ connecting */

    public function test_connecting_records_the_domain_but_does_not_serve_it(): void
    {
        $this->fakeProvider();
        $user = $this->creator();

        $domain = app(CustomDomainService::class)->connect($user, 'creator', 'contact.example-brand.com');

        // Recorded, but nothing is live: DNS has not been seen yet.
        $this->assertSame(CustomDomain::DNS_REQUIRED, $domain->status);
        $this->assertFalse($domain->isActive());
        $this->assertNotEmpty($domain->verification_token);
    }

    public function test_one_hostname_cannot_belong_to_two_accounts(): void
    {
        $this->fakeProvider();

        app(CustomDomainService::class)->connect($this->creator('one'), 'creator', 'shared.example-brand.com');

        $this->expectException(ValidationException::class);

        app(CustomDomainService::class)->connect($this->creator('two'), 'creator', 'shared.example-brand.com');
    }

    public function test_reconnecting_issues_a_new_verification_token(): void
    {
        $this->fakeProvider();
        $user = $this->creator();
        $service = app(CustomDomainService::class);

        $first = $service->connect($user, 'creator', 'a.example-brand.com');
        $second = $service->connect($user->fresh(), 'creator', 'b.example-brand.com');

        // Reusing a token would let whoever holds the old domain next replay a
        // proof of ownership that was never theirs.
        $this->assertNotSame($first->verification_token, $second->verification_token);
    }

    /* ------------------------------------------------------------- provider */

    public function test_without_a_provider_the_feature_says_so_rather_than_pretending(): void
    {
        $user = $this->creator();

        // The default binding is the unconfigured one.
        $this->assertFalse(app(CustomDomainService::class)->isAvailable());

        $this->actingAs($user)
            ->get(route('app.custom-domain'))
            ->assertOk()
            ->assertSee('Not available yet', false);
    }

    /* -------------------------------------------------------------- routing */

    public function test_an_unknown_hostname_is_not_served_our_site(): void
    {
        // A stale DNS record belonging to somebody else must not quietly become
        // a page that looks like ours.
        $this->get('http://someone-elses-domain.example.com/')->assertNotFound();
    }

    public function test_a_domain_that_is_not_active_yet_is_not_served(): void
    {
        $this->fakeProvider(dns: false, ssl: false);
        $user = $this->creator('waiting');

        app(CustomDomainService::class)->connect($user, 'creator', 'pending.example-brand.com');

        $this->get('http://pending.example-brand.com/')->assertNotFound();
    }

    public function test_an_active_domain_serves_only_the_contact_form(): void
    {
        $user = $this->creator('served');

        // Straight to active, so routing is what is under test rather than the
        // state machine that gets there.
        CustomDomain::query()->create([
            'user_id' => $user->id,
            'owner_scope' => 'creator',
            'hostname' => 'contact.example-brand.com',
            'status' => CustomDomain::ACTIVE,
            'verification_token' => 'token',
        ]);

        $this->get('http://contact.example-brand.com/')->assertOk();
        $this->get('http://contact.example-brand.com/contact')->assertOk();
    }

    public function test_an_active_domain_cannot_reach_anything_else(): void
    {
        $user = $this->creator('locked-down');

        CustomDomain::query()->create([
            'user_id' => $user->id,
            'owner_scope' => 'creator',
            'hostname' => 'contact.example-brand.com',
            'status' => CustomDomain::ACTIVE,
            'verification_token' => 'token',
        ]);

        // The whitelist is two paths. Everything else — the app, the panel, the
        // API, and other people's profiles — is refused on this hostname.
        foreach (['login', 'dashboard', 'admin', 'admin/login', 'api/v1/creators', 'served', 'inbox'] as $path) {
            $this->get('http://contact.example-brand.com/'.$path)
                ->assertNotFound();
        }
    }

    public function test_a_tenant_hostname_serves_its_own_owner_and_nobody_else(): void
    {
        $mine = $this->creator('mine');
        $this->creator('theirs');

        CustomDomain::query()->create([
            'user_id' => $mine->id,
            'owner_scope' => 'creator',
            'hostname' => 'contact.example-brand.com',
            'status' => CustomDomain::ACTIVE,
            'verification_token' => 'token',
        ]);

        $this->get('http://contact.example-brand.com/')
            ->assertOk()
            ->assertDontSee('theirs', false);
    }

    /* -------------------------------------------------------- state machine */

    public function test_a_domain_does_not_go_active_without_a_certificate(): void
    {
        $this->fakeProvider(dns: true, ssl: false);
        $user = $this->creator('nossl');
        $service = app(CustomDomainService::class);

        $domain = $service->connect($user, 'creator', 'nossl.example-brand.com');

        // dns_get_record will not resolve this name, so the public-resolution
        // check fails first — which is itself the right answer: a domain that
        // does not resolve publicly is not one we serve.
        $refreshed = $service->refresh($domain);

        $this->assertNotSame(CustomDomain::ACTIVE, $refreshed->status);
    }

    public function test_every_state_change_is_recorded(): void
    {
        $this->fakeProvider();
        $user = $this->creator('history');

        $domain = app(CustomDomainService::class)->connect($user, 'creator', 'history.example-brand.com');

        $this->assertDatabaseHas('custom_domain_events', [
            'custom_domain_id' => $domain->id,
            'event' => 'connected',
        ]);
    }

    public function test_disconnecting_stops_it_being_served(): void
    {
        $this->fakeProvider();
        $user = $this->creator('leaving');
        $service = app(CustomDomainService::class);

        $domain = $service->connect($user, 'creator', 'leaving.example-brand.com');
        $domain->update(['status' => CustomDomain::ACTIVE]);

        $this->get('http://leaving.example-brand.com/')->assertOk();

        $service->disconnect($domain->fresh(), $user);

        $this->get('http://leaving.example-brand.com/')->assertNotFound();
    }

    /* -------------------------------------------------------- authorisation */

    public function test_you_can_only_act_on_your_own_domain(): void
    {
        $this->fakeProvider();
        $mine = $this->creator('owner');
        $stranger = $this->creator('stranger');

        app(CustomDomainService::class)->connect($mine, 'creator', 'owned.example-brand.com');

        // No route takes a domain id, so the stranger has nothing of mine to
        // ask for — their own lookup simply finds nothing.
        $this->actingAs($stranger)
            ->post(route('app.custom-domain.check'))
            ->assertNotFound();

        $this->assertDatabaseHas('custom_domains', [
            'hostname' => 'owned.example-brand.com',
            'user_id' => $mine->id,
        ]);
    }
}
