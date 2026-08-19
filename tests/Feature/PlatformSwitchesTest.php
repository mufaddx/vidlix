<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeAbility;
use App\Models\User;
use App\Services\Platform\Features;
use App\Support\Ability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Switches an operator can throw without a deploy.
 */
class PlatformSwitchesTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $abilities): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_code' => Employee::generateCode(),
            'status' => 'active',
            'joined_at' => now(),
        ]);

        foreach ($abilities as $ability) {
            EmployeeAbility::query()->create(['employee_id' => $employee->id, 'ability' => $ability]);
        }

        return $user->fresh();
    }

    public function test_a_capability_is_on_until_somebody_turns_it_off(): void
    {
        // Absence must mean on. If a missing row meant off, shipping this
        // feature would have silently closed sign-up and every public form.
        $this->assertTrue(app(Features::class)->enabled('public_signup'));

        $this->get('/register')->assertOk();
    }

    public function test_turning_a_switch_off_closes_the_route_it_names(): void
    {
        app(Features::class)->setFlag('public_signup', false, 'everyone');

        $this->get('/register')->assertStatus(503);
    }

    public function test_maintenance_closes_the_site_to_members(): void
    {
        app(Features::class)->putSetting(Features::MAINTENANCE_KEY, '1');

        $this->get('/')->assertStatus(503)->assertSee('Back shortly');
    }

    public function test_maintenance_leaves_staff_sign_in_and_webhooks_open(): void
    {
        app(Features::class)->putSetting(Features::MAINTENANCE_KEY, '1');

        // A provider confirming a payment must never be turned away: the money
        // moved whether the site is up or not.
        $this->get('/login')->assertOk();
        // 401 is the signature check refusing an unsigned call, which is the
        // point: the request reached the webhook rather than the closed sign.
        $this->post('/webhooks/payment')->assertStatus(401);
    }

    public function test_staff_can_still_work_while_the_site_is_closed(): void
    {
        app(Features::class)->putSetting(Features::MAINTENANCE_KEY, '1');
        $staff = $this->staff([Ability::PLATFORM_MANAGE]);

        $this->actingAs($staff)->get('/')->assertOk();
    }

    public function test_only_platform_managers_can_throw_switches(): void
    {
        $support = $this->staff([Ability::SUPPORT_VIEW]);

        $this->actingAs($support)->get(route('admin.platform'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.health'))->assertForbidden();

        $operator = $this->staff([Ability::PLATFORM_MANAGE]);
        $this->actingAs($operator)->get(route('admin.platform'))->assertOk();
    }

    public function test_the_health_page_reports_an_unconfigured_provider_as_unconfigured(): void
    {
        $operator = $this->staff([Ability::PLATFORM_MANAGE]);

        // Not "ok", and not "down": no credentials is its own answer.
        $this->actingAs($operator)->get(route('admin.health'))
            ->assertOk()
            ->assertSee('Not configured');
    }
}
