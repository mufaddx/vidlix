<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The scheduler trigger exists only because the host has no cron. It is a way
 * to make the server do work over HTTP, so it must be impossible to reach
 * without the shared secret — and invisible when no secret is configured.
 */
class SchedulerTriggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_route_does_not_exist_until_a_token_is_configured(): void
    {
        config(['vidlix.cron.token' => null]);

        $this->postJson('/api/internal/scheduler/run', [], ['X-Cron-Token' => 'anything'])
            ->assertStatus(404);
    }

    public function test_a_missing_token_is_refused(): void
    {
        config(['vidlix.cron.token' => 'the-real-token']);

        $this->postJson('/api/internal/scheduler/run')->assertStatus(404);
    }

    public function test_a_wrong_token_is_refused(): void
    {
        config(['vidlix.cron.token' => 'the-real-token']);

        $this->postJson('/api/internal/scheduler/run', [], ['X-Cron-Token' => 'guess'])
            ->assertStatus(404);
    }

    public function test_the_correct_token_runs_the_scheduler(): void
    {
        config(['vidlix.cron.token' => 'the-real-token']);
        Artisan::spy();

        $this->postJson('/api/internal/scheduler/run', [], ['X-Cron-Token' => 'the-real-token'])
            ->assertOk()
            ->assertJsonPath('code', 'SCHEDULER_RAN');

        Artisan::shouldHaveReceived('call')->with('schedule:run');
    }

    public function test_the_token_is_not_accepted_from_the_query_string(): void
    {
        // A secret in a URL ends up in access logs, referrers and browser
        // history, so only the header is honoured.
        config(['vidlix.cron.token' => 'the-real-token']);

        $this->postJson('/api/internal/scheduler/run?token=the-real-token')
            ->assertStatus(404);
    }

    public function test_a_get_request_is_not_routed(): void
    {
        config(['vidlix.cron.token' => 'the-real-token']);

        $this->getJson('/api/internal/scheduler/run')->assertStatus(405);
    }
}
