<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Role;
use App\Models\User;
use App\Services\Marketplace\MarketplaceEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The inbox belongs to the person, not to a creator profile, and its filters
 * are exactly All / Creator / Editor / Brand.
 */
class UnifiedInboxTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $email, string $roleSlug): User
    {
        $user = User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
        $role = Role::query()->firstOrCreate(['slug' => $roleSlug], ['name' => ucfirst($roleSlug)]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->fresh();
    }

    public function test_the_inbox_offers_exactly_all_creator_editor_and_brand(): void
    {
        $user = $this->member('creator@example.test', 'creator');

        $response = $this->actingAs($user)->get(route('inbox'));

        $response->assertOk();
        foreach (['All', 'Creator', 'Editor', 'Brand'] as $label) {
            $response->assertSee($label);
        }
    }

    public function test_a_filter_shows_only_threads_with_that_kind_of_counterpart(): void
    {
        $creator = $this->member('c@example.test', 'creator');
        $brand = $this->member('b@example.test', 'brand');
        $editor = $this->member('e@example.test', 'editor');

        $engine = app(MarketplaceEngine::class);
        $engine->startInternalChat($creator, $brand, 'Campaign brief');
        $engine->startInternalChat($creator, $editor, 'Rough cut review');

        $this->actingAs($creator)->get(route('inbox', ['filter' => 'brand']))
            ->assertSee('Campaign brief')
            ->assertDontSee('Rough cut review');

        $this->actingAs($creator)->get(route('inbox', ['filter' => 'editor']))
            ->assertSee('Rough cut review')
            ->assertDontSee('Campaign brief');

        $this->actingAs($creator)->get(route('inbox'))
            ->assertSee('Campaign brief')
            ->assertSee('Rough cut review');
    }

    public function test_the_same_thread_is_filed_under_creator_for_the_brand(): void
    {
        $creator = $this->member('c2@example.test', 'creator');
        $brand = $this->member('b2@example.test', 'brand');

        app(MarketplaceEngine::class)->startInternalChat($creator, $brand, 'Two sided thread');

        // Whose inbox it is decides what the other side is called. A single
        // role on the conversation could only ever be right for one of them.
        $this->actingAs($brand)->get(route('inbox', ['filter' => 'creator']))
            ->assertSee('Two sided thread');

        $this->actingAs($brand)->get(route('inbox', ['filter' => 'brand']))
            ->assertDontSee('Two sided thread');
    }

    public function test_an_unknown_filter_falls_back_to_all_rather_than_hiding_mail(): void
    {
        $creator = $this->member('c3@example.test', 'creator');
        $brand = $this->member('b3@example.test', 'brand');
        app(MarketplaceEngine::class)->startInternalChat($creator, $brand, 'Still visible');

        $this->actingAs($creator)->get(route('inbox', ['filter' => 'nonsense']))
            ->assertOk()
            ->assertSee('Still visible');
    }

    public function test_a_member_cannot_open_someone_elses_conversation(): void
    {
        $creator = $this->member('c4@example.test', 'creator');
        $brand = $this->member('b4@example.test', 'brand');
        $stranger = $this->member('s4@example.test', 'creator');

        $conversation = app(MarketplaceEngine::class)->startInternalChat($creator, $brand, 'Private');

        $this->actingAs($stranger)->get(route('inbox.show', $conversation->conversation_uuid))
            ->assertNotFound();
    }

    public function test_opening_a_thread_clears_its_unread_count(): void
    {
        $creator = $this->member('c5@example.test', 'creator');
        $brand = $this->member('b5@example.test', 'brand');

        $engine = app(MarketplaceEngine::class);
        $conversation = $engine->startInternalChat($creator, $brand, 'Unread thread');
        $engine->postInternalMessage($conversation, $brand, 'Are you free next week?');

        $this->actingAs($creator)->get(route('inbox'))->assertSee('1 new');

        $this->actingAs($creator)->get(route('inbox.show', $conversation->conversation_uuid))->assertOk();

        $this->actingAs($creator)->get(route('inbox'))->assertDontSee('1 new');
    }

    public function test_the_help_desk_stays_out_of_a_members_inbox(): void
    {
        $creator = $this->member('c6@example.test', 'creator');

        $conversation = Conversation::query()->create([
            'conversation_uuid' => (string) Str::uuid(),
            'channel' => 'support',
            'subject' => 'Support ticket about payouts',
            'status' => 'open',
            'owner_user_id' => $creator->id,
            'routing_token' => 'tok-support-1',
            'last_message_at' => now(),
        ]);
        $conversation->participants()->create(['user_id' => $creator->id, 'role' => 'requester']);

        $this->actingAs($creator)->get(route('inbox'))
            ->assertDontSee('Support ticket about payouts');

        $this->actingAs($creator)->get(route('inbox.show', $conversation->conversation_uuid))
            ->assertNotFound();
    }

    public function test_the_old_creator_inbox_url_still_resolves(): void
    {
        $creator = $this->member('c7@example.test', 'creator');

        $this->actingAs($creator)->get('/creator/inbox')->assertRedirect('/inbox');
    }
}
