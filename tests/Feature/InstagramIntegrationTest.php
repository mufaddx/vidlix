<?php

namespace Tests\Feature;

use App\Contracts\InstagramProviderInterface;
use App\Jobs\SyncInstagramProfile;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InstagramIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function configureMeta(): void
    {
        config([
            'vidlix.providers.instagram' => 'meta',
            'vidlix.instagram.app_id' => '1234567890',
            'vidlix.instagram.app_secret' => 'meta-app-secret',
            'vidlix.instagram.redirect_uri' => 'https://vidlix.test/integrations/instagram/callback',
            'vidlix.instagram.graph_base' => 'https://graph.facebook.com',
            'vidlix.instagram.graph_version' => 'v21.0',
            'vidlix.webhooks.meta_secret' => 'verify-token',
            'vidlix.webhooks.meta_app_secret' => 'meta-app-secret',
        ]);
    }

    private function creatorProfile(): CreatorProfile
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'creator'], ['name' => 'Creator']));
        app(CreatorOnboardingService::class)->provision($user->id, $user->name);

        return $user->fresh()->creatorProfile;
    }

    public function test_authorization_url_is_the_official_meta_dialog(): void
    {
        $this->configureMeta();
        $url = app(InstagramProviderInterface::class)->authorizationUrl($this->creatorProfile());

        $this->assertStringStartsWith('https://www.facebook.com/v21.0/dialog/oauth?', $url);
        $this->assertStringContainsString('client_id=1234567890', $url);
        $this->assertStringContainsString('instagram_manage_insights', urldecode($url));
    }

    public function test_a_graph_error_yields_no_insights_at_all(): void
    {
        $this->configureMeta();
        $profile = $this->creatorProfile();
        InstagramAccount::query()->updateOrCreate([
            'creator_profile_id' => $profile->id,
        ], [
            'status' => 'connected',
            'ig_user_id' => '17841400000000000',
            'token_encrypted' => Crypt::encryptString('page-token'),
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Error validating access token', 'code' => 190],
            ], 400),
        ]);

        $result = app(InstagramProviderInterface::class)->syncPermittedData($profile->fresh());

        $this->assertSame('reauth_required', $result['status']);
        $this->assertSame([], $result['insights']);
        $this->assertSame('reauth_required', $profile->fresh()->instagramAccount->status);
        $this->assertNull($profile->fresh()->instagramAccount->insights);
    }

    public function test_only_fields_the_graph_api_returned_are_stored(): void
    {
        $this->configureMeta();
        $profile = $this->creatorProfile();
        InstagramAccount::query()->updateOrCreate([
            'creator_profile_id' => $profile->id,
        ], [
            'status' => 'connected',
            'ig_user_id' => '17841400000000000',
            'token_encrypted' => Crypt::encryptString('page-token'),
        ]);

        Http::fake([
            'graph.facebook.com/v21.0/17841400000000000/insights*' => Http::response(['data' => []], 200),
            'graph.facebook.com/v21.0/17841400000000000*' => Http::response([
                'username' => 'mursalim',
                'followers_count' => 18422,
                // media_count deliberately absent from the response
            ], 200),
        ]);

        $result = app(InstagramProviderInterface::class)->syncPermittedData($profile->fresh());

        $this->assertSame('synced', $result['status']);
        $this->assertSame(18422, $result['insights']['followers_count']);
        $this->assertArrayNotHasKey('media_count', $result['insights']);
        $this->assertNotNull($profile->fresh()->instagramAccount->last_synced_at);
    }

    public function test_meta_subscription_verification_requires_the_exact_verify_token(): void
    {
        $this->configureMeta();

        $this->get('/webhooks/meta?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=42')
            ->assertStatus(403);

        $this->get('/webhooks/meta?hub.mode=subscribe&hub.verify_token=verify-token&hub.challenge=42')
            ->assertOk()
            ->assertSee('42');
    }

    public function test_meta_events_require_the_hub_signature(): void
    {
        $this->configureMeta();
        $payload = ['object' => 'instagram', 'entry' => [['id' => '17841400000000000']]];
        $body = json_encode($payload);

        $this->call('POST', '/webhooks/meta', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=deadbeef',
        ], $body)->assertStatus(401);

        $this->call('POST', '/webhooks/meta', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'meta-app-secret'),
        ], $body)->assertOk();
    }

    public function test_a_meta_event_queues_a_graph_sync_rather_than_trusting_its_body(): void
    {
        $this->configureMeta();
        $profile = $this->creatorProfile();
        InstagramAccount::query()->updateOrCreate([
            'creator_profile_id' => $profile->id,
        ], [
            'status' => 'connected',
            'ig_user_id' => '17841400000000000',
            'token_encrypted' => Crypt::encryptString('page-token'),
        ]);

        Queue::fake();

        $payload = [
            'object' => 'instagram',
            'entry' => [['id' => '17841400000000000', 'changes' => [['field' => 'comments']]]],
        ];
        $body = json_encode($payload);

        $this->call('POST', '/webhooks/meta', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'meta-app-secret'),
        ], $body)->assertOk()->assertJsonPath('outcome', 'sync_queued');

        Queue::assertPushed(SyncInstagramProfile::class);
    }

    public function test_without_meta_credentials_nothing_is_connected_or_reported(): void
    {
        config(['vidlix.providers.instagram' => 'unconfigured']);
        $profile = $this->creatorProfile();

        $provider = app(InstagramProviderInterface::class);
        $result = $provider->syncPermittedData($profile);

        $this->assertNull($provider->authorizationUrl($profile));
        $this->assertSame('provider_not_configured', $result['status']);
        $this->assertSame([], $result['insights']);
    }
}
