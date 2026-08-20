<?php

namespace Tests\Feature;

use App\Models\BrandProfile;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\CreatorProfile;
use App\Models\EditorProfile;
use App\Models\InstagramAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use App\Services\Email\OutboundEmailService;
use App\Services\Marketplace\CreatorDiscovery;
use App\Services\Taxonomy\CategoryService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveryAndPublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function creatorWith(?int $followers, array $categorySlugs = []): CreatorProfile
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach(Role::query()->where('slug', 'creator')->first());
        app(CreatorOnboardingService::class)->provision($user->id, $user->name);

        $profile = $user->fresh()->creatorProfile;
        $profile->update(['visibility' => 'public', 'follower_count' => $followers]);

        if ($categorySlugs !== []) {
            $ids = Category::query()->ofType('creator')->whereIn('slug', $categorySlugs)->pluck('id')->all();
            app(CategoryService::class)->sync($profile, 'creator', $ids, [], $user, 3);
        }

        return $profile->fresh();
    }

    private function brandUser(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach(Role::query()->where('slug', 'brand')->first());
        BrandProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ACME Ltd',
            'slug' => 'acme-ltd-'.$user->id,
            'verification_status' => 'verified',
        ]);

        return $user->fresh();
    }

    public function test_search_filters_by_category(): void
    {
        $food = $this->creatorWith(10000, ['food-cooking']);
        $this->creatorWith(50000, ['gaming']);

        $foodId = Category::query()->ofType('creator')->where('slug', 'food-cooking')->value('id');
        $results = collect(app(CreatorDiscovery::class)->search(['categories' => [$foodId]])->items())->pluck('id');

        $this->assertCount(1, $results);
        $this->assertSame($food->id, $results->first());
    }

    public function test_search_filters_by_follower_range(): void
    {
        $this->creatorWith(5000);
        $mid = $this->creatorWith(50000);
        $this->creatorWith(500000);

        $results = collect(app(CreatorDiscovery::class)->search(['min_followers' => 10000, 'max_followers' => 100000])->items())->pluck('id');

        $this->assertCount(1, $results);
        $this->assertSame($mid->id, $results->first());
    }

    public function test_a_creator_without_a_synced_follower_count_is_never_shown_as_zero(): void
    {
        $unsynced = $this->creatorWith(null);
        $ids = fn ($result) => collect($result->items())->pluck('id');

        // Present in an unfiltered search...
        $this->assertTrue($ids(app(CreatorDiscovery::class)->search([]))->contains($unsynced->id));

        // ...but excluded from a follower filter, because we do not know their
        // reach and must not imply it is zero.
        $this->assertFalse($ids(app(CreatorDiscovery::class)->search(['min_followers' => 0]))->contains($unsynced->id));
        $this->assertNull($unsynced->follower_count);
    }

    public function test_private_creators_are_not_discoverable(): void
    {
        $profile = $this->creatorWith(10000);
        $this->assertTrue(collect(app(CreatorDiscovery::class)->search([])->items())->pluck('id')->contains($profile->id));

        $profile->update(['visibility' => 'private']);

        $this->assertFalse(collect(app(CreatorDiscovery::class)->search([])->items())->pluck('id')->contains($profile->id));
    }

    public function test_only_a_brand_can_open_discovery(): void
    {
        $creatorUser = $this->creatorWith(1000)->user;

        $this->actingAs($creatorUser)->get(route('app.discover'))->assertStatus(403);
        $this->actingAs($this->brandUser())->get(route('app.discover'))->assertOk();
    }

    public function test_connecting_starts_an_internal_thread_not_an_email(): void
    {
        $brand = $this->brandUser();
        $creator = $this->creatorWith(20000);

        $this->actingAs($brand)
            ->post(route('app.discover.connect', $creator), [
                'subject' => 'Summer campaign',
                'message' => 'We would like to work with you.',
            ])->assertRedirect();

        $conversation = Conversation::query()->where('subject', 'Summer campaign')->firstOrFail();
        $this->assertSame('internal', $conversation->channel);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'internal',
        ]);
        // Nothing left the platform, so no external contact was created.
        $this->assertDatabaseCount('external_contacts', 0);
    }

    public function test_an_editor_public_page_accepts_an_enquiry_without_an_account(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $editor = EditorProfile::query()->create([
            'user_id' => $user->id,
            'username' => 'sharpcut',
            'display_name' => 'Sharp Cut',
            'application_status' => 'approved',
            'visibility' => 'public',
            'accepts_inquiries' => true,
        ]);

        $this->get('/sharpcut')->assertOk()->assertSee('What do you need edited?', false);

        $this->post('/sharpcut/contact', [
            'name' => 'Rahul',
            'email' => 'rahul@brand.test',
            'subject' => 'Documentary cut',
            'message' => 'Need a 20 minute documentary edited.',
        ])->assertRedirect();

        $conversation = Conversation::query()->where('subject', 'Documentary cut')->firstOrFail();
        $this->assertSame($user->id, $conversation->owner_user_id);
        $this->assertSame('editor', $conversation->owner_scope);
        $this->assertDatabaseHas('email_events', ['status' => 'provider_not_configured']);
        $_ = $editor;
    }

    public function test_the_editor_honeypot_refuses_a_bot(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        EditorProfile::query()->create([
            'user_id' => $user->id, 'username' => 'botbait', 'display_name' => 'Bot Bait',
            'application_status' => 'approved', 'visibility' => 'public', 'accepts_inquiries' => true,
        ]);

        $this->post('/botbait/contact', [
            'name' => 'Bot', 'email' => 'bot@spam.test', 'subject' => 'x', 'message' => 'y',
            config('vidlix.public_form_honeypot') => 'filled-in',
        ])->assertSessionHasErrors('form');

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_creator_threads_leave_from_creator_and_editor_threads_from_editor(): void
    {
        config(['vidlix.email.inbound_domain' => 'vidlix.in', 'vidlix.email.from_name' => 'Vidlix']);
        $outbound = app(OutboundEmailService::class);

        $creatorProfile = $this->creatorWith(1000);
        InstagramAccount::query()->updateOrCreate(
            ['creator_profile_id' => $creatorProfile->id],
            ['status' => 'connected', 'username' => 'mursalim'],
        );

        $creatorThread = Conversation::query()->create([
            'conversation_uuid' => 'c1', 'channel' => 'external_email', 'subject' => 'Hi',
            'status' => 'open', 'creator_profile_id' => $creatorProfile->id,
            'owner_user_id' => $creatorProfile->user_id, 'owner_scope' => 'creator',
            'routing_token' => 'tok-creator', 'last_message_at' => now(),
        ]);

        $editorUser = User::factory()->create();
        EditorProfile::query()->create([
            'user_id' => $editorUser->id, 'username' => 'cutter', 'display_name' => 'Cutter',
            'application_status' => 'approved',
        ]);
        $editorThread = Conversation::query()->create([
            'conversation_uuid' => 'e1', 'channel' => 'external_email', 'subject' => 'Hi',
            'status' => 'open', 'owner_user_id' => $editorUser->id, 'owner_scope' => 'editor',
            'routing_token' => 'tok-editor', 'last_message_at' => now(),
        ]);

        $creatorIdentity = $outbound->identityFor($creatorThread);
        $editorIdentity = $outbound->identityFor($editorThread);

        $this->assertSame('creator@vidlix.in', $creatorIdentity->fromAddress);
        $this->assertSame('creator+tok-creator@vidlix.in', $creatorIdentity->replyTo);
        // The brand sees who they are talking to, handle included.
        $this->assertStringContainsString('(@mursalim)', $creatorIdentity->fromName);

        $this->assertSame('editor@vidlix.in', $editorIdentity->fromAddress);
        $this->assertSame('editor+tok-editor@vidlix.in', $editorIdentity->replyTo);
        $this->assertStringContainsString('Cutter', $editorIdentity->fromName);
    }

    public function test_inbound_mail_routes_back_from_a_creator_or_editor_address(): void
    {
        config([
            'vidlix.email.inbound_domain' => 'vidlix.in',
            'vidlix.webhooks.email_secret' => 'mail-secret',
            'vidlix.webhooks.schemes.email' => 'hmac_hex',
        ]);

        $editorUser = User::factory()->create();
        EditorProfile::query()->create([
            'user_id' => $editorUser->id, 'username' => 'router', 'display_name' => 'Router',
            'application_status' => 'approved',
        ]);
        $thread = Conversation::query()->create([
            'conversation_uuid' => 'e2', 'channel' => 'external_email', 'subject' => 'Re: cut',
            'status' => 'open', 'owner_user_id' => $editorUser->id, 'owner_scope' => 'editor',
            'routing_token' => 'edtok9', 'last_message_at' => now(),
        ]);

        $payload = [
            'id' => 'inb-editor-1',
            'from' => 'brand@abc.test',
            'to' => 'editor+edtok9@vidlix.in',
            'subject' => 'Re: cut',
            'text' => 'Sounds good.',
        ];
        $body = json_encode($payload);

        $this->call('POST', '/webhooks/email/inbound', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $body, 'mail-secret'),
        ], $body)->assertOk()->assertJsonPath('outcome', 'matched');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $thread->id,
            'direction' => 'inbound',
        ]);
    }
}
