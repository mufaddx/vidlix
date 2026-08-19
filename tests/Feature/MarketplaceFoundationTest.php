<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Conversation;
use App\Models\Role;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_home_page_renders_cms_hero(): void
    {
        $this->get('/')->assertOk()->assertSee('production desk', false);
    }

    public function test_public_inquiry_creates_conversation_without_fake_email_send(): void
    {
        $this->post('/u/mursalim/inquire', [
            'name' => 'Rahul',
            'email' => 'rahul@abcbrand.com',
            'subject' => 'Summer Campaign',
            'message' => 'Need 8 reels in June.',
            'company' => 'ABC',
        ])->assertRedirect();

        $this->assertDatabaseHas('conversations', [
            'channel' => 'external_email',
            'subject' => 'Summer Campaign',
        ]);
        $this->assertDatabaseHas('email_events', [
            'status' => 'provider_not_configured',
        ]);
    }

    public function test_creator_cannot_open_another_creator_conversation(): void
    {
        $this->post('/u/mursalim/inquire', [
            'name' => 'Rahul',
            'email' => 'rahul@abcbrand.com',
            'subject' => 'Private deal',
            'message' => 'Budget enclosed.',
        ]);
        $uuid = Conversation::query()->firstOrFail()->conversation_uuid;

        $intruder = User::factory()->create();
        $intruder->roles()->attach(Role::query()->where('slug', 'creator')->first());
        app(CreatorOnboardingService::class)->provision($intruder->id, $intruder->name);

        $this->actingAs($intruder)
            ->get('/inbox/'.$uuid)
            ->assertNotFound();
    }

    public function test_payment_create_does_not_fake_success(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/payments/create', [
                'amount_minor' => 800000,
                'currency' => 'INR',
            ])
            ->assertStatus(503)
            ->assertJsonPath('code', 'PROVIDER_NOT_CONFIGURED');
    }

    public function test_published_campaign_application_persists(): void
    {
        $this->actingAs(User::query()->where('email', 'creator@vidlix.test')->first());
        $campaign = Campaign::query()->where('slug', 'summer-reels')->firstOrFail();
        $this->post(route('app.campaigns.apply', $campaign), [
            'proposed_fee_minor' => 2500000,
            'message' => 'I can deliver eight reels.',
        ])->assertRedirect();
        $this->assertDatabaseHas('campaign_applications', [
            'campaign_id' => $campaign->id,
            'status' => 'applied',
        ]);
    }

    public function test_unmatched_inbound_email_is_not_guessed_into_an_inbox(): void
    {
        config(['vidlix.webhooks.email_secret' => 'mail-secret']);
        $payload = ['id' => 'em_1', 'routing_token' => 'unknown-token', 'from' => 'a@b.c', 'text' => 'hello'];
        $body = json_encode($payload);
        $sig = hash_hmac('sha256', $body, 'mail-secret');
        $this->call('POST', '/webhooks/email/inbound', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
        ], $body)->assertOk();
        $this->assertDatabaseHas('inbound_email_events', ['match_status' => 'unmatched']);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_payment_webhook_rejects_bad_signature_and_ignores_replay(): void
    {
        config(['vidlix.webhooks.payment_secret' => 'test-secret']);
        $payload = ['id' => 'evt_99', 'type' => 'payment.captured'];

        $this->postJson('/webhooks/payment', $payload, ['X-Webhook-Signature' => 'nope'])
            ->assertStatus(401);

        $body = json_encode($payload);
        $sig = hash_hmac('sha256', $body, 'test-secret');

        $this->call(
            'POST',
            '/webhooks/payment',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
            ],
            $body,
        )->assertOk();

        $this->call(
            'POST',
            '/webhooks/payment',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
            ],
            $body,
        )->assertOk()->assertJsonPath('code', 'DUPLICATE_IGNORED');
    }
}
