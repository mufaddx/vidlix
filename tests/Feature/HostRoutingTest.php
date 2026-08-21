<?php

namespace Tests\Feature;

use App\Support\Host;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each host keeps to its own part of the product.
 *
 * Four domains that all serve everything are four names for one website. These
 * tests configure four distinct hosts — as production has — and then check that
 * a request on one cannot reach another's pages.
 *
 * The rest of the suite runs with all four pointing at one host, which is what
 * makes the whole application reachable there; that arrangement is asserted at
 * the end, because it is load-bearing and easy to break by accident.
 */
class HostRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function fourHosts(): void
    {
        config([
            'vidlix.domains.site' => 'https://vidlix.test',
            'vidlix.domains.app' => 'https://app.vidlix.test',
            'vidlix.domains.autodm' => 'https://autodm.vidlix.test',
            'vidlix.domains.admin' => 'https://admin.vidlix.test',
        ]);
    }

    /* ------------------------------------------------------------ the site */

    public function test_the_landing_site_hands_sign_in_to_the_workspace(): void
    {
        $this->fourHosts();

        // Signing in is not something the marketing site does; the account
        // lives in the workspace and that is where the person is sent.
        $this->get('https://vidlix.test/login')
            ->assertRedirect('https://app.vidlix.test/login');

        $this->get('https://vidlix.test/dashboard')
            ->assertRedirect('https://app.vidlix.test/dashboard');
    }

    public function test_the_landing_site_still_serves_what_it_is_for(): void
    {
        $this->fourHosts();

        foreach (['/', '/creators', '/editors', '/pricing', '/autodm'] as $path) {
            $this->get('https://vidlix.test'.$path)
                ->assertOk();
        }
    }

    public function test_the_autodm_dashboard_is_not_served_from_the_landing_site(): void
    {
        $this->fourHosts();

        $this->get('https://vidlix.test/autodm/dashboard')
            ->assertRedirect('https://autodm.vidlix.test/autodm/dashboard');
    }

    /* --------------------------------------------------------- the autodm host */

    public function test_the_autodm_host_shows_the_autodm_page_at_its_root(): void
    {
        $this->fourHosts();

        // Not the marketplace landing page. Somebody who typed the AutoDM
        // address came for AutoDM.
        $this->get('https://autodm.vidlix.test/')
            ->assertOk()
            ->assertSee('What Instagram allows', false);
    }

    public function test_the_autodm_host_does_not_serve_the_marketplace(): void
    {
        $this->fourHosts();

        // A creator's inbox has no business on the Instagram automation domain.
        $this->get('https://autodm.vidlix.test/inbox')
            ->assertRedirect('https://app.vidlix.test/inbox');

        $this->get('https://autodm.vidlix.test/chat')
            ->assertRedirect('https://app.vidlix.test/chat');

        $this->get('https://autodm.vidlix.test/projects')
            ->assertRedirect('https://app.vidlix.test/projects');
    }

    public function test_the_autodm_host_sends_marketing_pages_back_to_the_site(): void
    {
        $this->fourHosts();

        $this->get('https://autodm.vidlix.test/pricing')
            ->assertRedirect('https://vidlix.test/pricing');
    }

    public function test_signing_in_works_on_the_autodm_host(): void
    {
        $this->fourHosts();

        // Otherwise the product cannot be entered from its own address.
        $this->get('https://autodm.vidlix.test/login')->assertOk();
        $this->get('https://autodm.vidlix.test/register')->assertOk();
    }

    /* ----------------------------------------------------------- the workspace */

    public function test_the_workspace_root_goes_to_sign_in_not_marketing(): void
    {
        $this->fourHosts();

        $this->get('https://app.vidlix.test/')
            ->assertRedirect('/login');
    }

    public function test_the_workspace_sends_marketing_pages_to_the_site(): void
    {
        $this->fourHosts();

        // So a link somebody copies out of the workspace reads as vidlix.in.
        $this->get('https://app.vidlix.test/pricing')
            ->assertRedirect('https://vidlix.test/pricing');
    }

    public function test_the_workspace_sends_autodm_to_its_own_host(): void
    {
        $this->fourHosts();

        $this->get('https://app.vidlix.test/autodm/dashboard')
            ->assertRedirect('https://autodm.vidlix.test/autodm/dashboard');
    }

    /* --------------------------------------------------------------- admin */

    public function test_the_admin_panel_is_invisible_from_every_other_host(): void
    {
        $this->fourHosts();

        // Refused rather than redirected: pointing somebody at the staff
        // sign-in because they guessed a URL tells them it is there.
        foreach (['https://vidlix.test', 'https://app.vidlix.test', 'https://autodm.vidlix.test'] as $host) {
            $this->get($host.'/admin')->assertNotFound();
            $this->get($host.'/admin/login')->assertNotFound();
        }
    }

    public function test_the_admin_host_serves_only_the_panel(): void
    {
        $this->fourHosts();

        $this->get('https://admin.vidlix.test/admin/login')->assertOk();

        // A staff member who wanders off the panel is sent back to it rather
        // than shown the marketplace.
        $this->get('https://admin.vidlix.test/pricing')
            ->assertRedirect('https://admin.vidlix.test/admin');
    }

    /* -------------------------------------------------------------- shared */

    public function test_health_and_webhooks_answer_on_every_host(): void
    {
        $this->fourHosts();

        // These are addressed by external systems that were configured with one
        // hostname, and moving them would silently break a provider.
        foreach ([
            'https://vidlix.test',
            'https://app.vidlix.test',
            'https://autodm.vidlix.test',
            'https://admin.vidlix.test',
        ] as $host) {
            $this->get($host.'/up')->assertOk();
        }
    }

    public function test_an_unrecognised_host_is_left_alone(): void
    {
        $this->fourHosts();

        // Development, a health check by IP, a staging box. Guessing which face
        // was meant would take most of the application away from them.
        $this->assertNull(Host::resolve('192.0.2.10'));
    }

    /* ------------------------------------------------ the single-host case */

    public function test_one_host_serving_everything_is_left_alone(): void
    {
        // This is the arrangement the rest of the suite runs under, and the
        // reason the whole application is reachable in tests at all.
        config([
            'vidlix.domains.site' => 'http://localhost',
            'vidlix.domains.app' => 'http://localhost',
            'vidlix.domains.autodm' => 'http://localhost',
            'vidlix.domains.admin' => 'http://localhost',
        ]);

        $this->assertTrue(Host::isSingleHostEnvironment());

        $this->get('http://localhost/pricing')->assertOk();
        $this->get('http://localhost/login')->assertOk();
    }
}
