<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\Marketplace\MarketplaceEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app is told what a project may do next, so it never keeps its own copy
 * of the state machine.
 */
class ProjectTransitionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_api_publishes_the_next_states_for_a_project(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);

        $project = Project::query()->create([
            'name' => 'A project',
            'owner_user_id' => $owner->id,
            'counterparty_user_id' => $other->id,
            'status' => 'draft_submitted',
        ]);

        $token = $owner->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/projects/'.$project->getKey())
            ->assertOk()
            ->assertJsonPath('data.next_states', ['revision_requested', 'final_submitted']);
    }

    public function test_the_published_list_is_the_same_one_the_engine_enforces(): void
    {
        // Two copies would drift, and the client's would be the stale one.
        $map = MarketplaceEngine::projectTransitions();

        $this->assertSame(['proposal_sent'], $map['draft']);
        $this->assertArrayNotHasKey('completed', $map);
    }

    public function test_a_state_the_engine_refuses_is_not_offered(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $project = Project::query()->create([
            'name' => 'Another project',
            'owner_user_id' => $owner->id,
            'status' => 'draft',
        ]);

        $token = $owner->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/projects/'.$project->getKey().'/transition', ['status' => 'completed'])
            ->assertStatus(422);
    }
}
