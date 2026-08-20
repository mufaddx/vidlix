<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the phone client needs from the API, locked down.
 */
class AppSignupContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_a_confirmed_password(): void
    {
        // The app shipped without a confirm field and every sign-up failed with
        // "the password field confirmation does not match" - a wall nobody
        // could get past.
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Someone',
            'email' => 'someone@example.test',
            'mobile' => '9990001111',
            'password' => 'Correct-Horse-1',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_registration_succeeds_when_the_confirmation_is_sent(): void
    {
        Role::query()->firstOrCreate(['slug' => 'creator'], ['name' => 'Creator']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Someone',
            'email' => 'someone2@example.test',
            'mobile' => '9990002222',
            'password' => 'Correct-Horse-1',
            'password_confirmation' => 'Correct-Horse-1',
            'role' => 'creator',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'someone2@example.test']);
    }

    public function test_the_terms_the_app_shows_come_from_the_server(): void
    {
        $response = $this->getJson('/api/v1/auth/terms')->assertOk();

        foreach (['creator', 'editor', 'brand'] as $role) {
            $response->assertJsonPath("data.roles.{$role}.label", fn ($label) => filled($label));
        }

        // Every role carries the money terms, and they quote the rate actually
        // configured rather than a number typed into the document.
        $response->assertSee('platform fee', false);
    }

    public function test_a_member_can_take_on_another_role_from_the_app(): void
    {
        Role::query()->firstOrCreate(['slug' => 'editor'], ['name' => 'Editor']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/roles/apply', ['role' => 'editor'])
            ->assertCreated()
            // Applying is not becoming: an editor profile waits for review.
            ->assertJsonPath('data.status', 'pending');

        $this->assertContains('editor', $user->fresh()->roleSlugs());
    }

    public function test_asking_twice_is_not_an_error(): void
    {
        Role::query()->firstOrCreate(['slug' => 'editor'], ['name' => 'Editor']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/roles/apply', ['role' => 'editor']);

        $this->withToken($token)
            ->postJson('/api/v1/roles/apply', ['role' => 'editor'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_nobody_can_make_themselves_a_manager(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/roles/apply', ['role' => 'manager'])
            ->assertStatus(422);
    }
}
