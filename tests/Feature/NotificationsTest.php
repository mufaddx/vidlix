<?php

namespace Tests\Feature;

use App\Contracts\PushProviderInterface;
use App\Models\BrandProfile;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use App\Services\Marketplace\MarketplaceEngine;
use App\Services\Notifications\Notifier;
use App\Services\Platform\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A notification is recorded whether or not anything could deliver it, and a
 * push that did not go out is never reported as if it did.
 */
class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $email): User
    {
        return User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
    }

    public function test_an_unconfigured_provider_still_records_the_notification(): void
    {
        $user = $this->member('a@example.test');

        $result = app(Notifier::class)->send($user, 'message_received', 'Hello', 'A body');

        $this->assertTrue($result['stored']);
        // Not "sent". The member was not reached and the result says so.
        $this->assertSame('provider_not_configured', $result['push']);
        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    public function test_a_member_who_turned_push_off_is_not_pushed_to(): void
    {
        $user = $this->member('b@example.test');
        app(Notifier::class)->savePreferences($user, ['message_received' => ['push' => false, 'email' => true]]);

        $result = app(Notifier::class)->send($user, 'message_received', 'Hello', 'A body');

        $this->assertSame('declined_by_member', $result['push']);
        // Still recorded: the bell stays complete even when the phone is quiet.
        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    public function test_absence_of_a_preference_row_means_on(): void
    {
        $user = $this->member('c@example.test');

        // Shipping preferences must not silently mute everyone who has never
        // opened the screen.
        $this->assertTrue(app(Notifier::class)->wants($user, 'message_received', 'push'));
    }

    public function test_a_configured_provider_is_actually_called(): void
    {
        $user = $this->member('d@example.test');
        DeviceToken::query()->create([
            'user_id' => $user->id,
            'token' => 'device-token-1',
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);

        $this->swap(PushProviderInterface::class, new class implements PushProviderInterface
        {
            public array $calls = [];

            public function isConfigured(): bool
            {
                return true;
            }

            public function name(): string
            {
                return 'fake';
            }

            public function send(array $deviceTokens, string $title, string $body, array $data = []): array
            {
                $this->calls[] = $deviceTokens;

                return ['status' => 'sent', 'sent' => count($deviceTokens), 'failed' => 0, 'detail' => 'ok'];
            }
        });

        $result = app(Notifier::class)->send($user, 'message_received', 'Hello', 'A body');

        $this->assertSame('sent', $result['push']);
    }

    public function test_a_rejected_token_is_marked_failed_rather_than_retried_forever(): void
    {
        $user = $this->member('e@example.test');
        DeviceToken::query()->create([
            'user_id' => $user->id,
            'token' => 'dead-token',
            'platform' => 'ios',
            'last_seen_at' => now(),
        ]);

        $this->swap(PushProviderInterface::class, new class implements PushProviderInterface
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function name(): string
            {
                return 'fake';
            }

            public function send(array $deviceTokens, string $title, string $body, array $data = []): array
            {
                return ['status' => 'failed', 'sent' => 0, 'failed' => count($deviceTokens), 'detail' => 'Unregistered'];
            }
        });

        app(Notifier::class)->send($user, 'message_received', 'Hello', 'A body');

        $token = DeviceToken::query()->where('token', 'dead-token')->first();
        $this->assertNotNull($token->failed_at);
        $this->assertSame('Unregistered', $token->failure_reason);
    }

    public function test_the_push_feature_switch_stops_delivery(): void
    {
        $user = $this->member('f@example.test');
        app(Features::class)->setFlag('push_notifications', false, 'everyone');

        $this->assertSame('feature_off', app(Notifier::class)->send($user, 'message_received', 'Hi', 'Body')['push']);
    }

    public function test_a_decided_application_notifies_the_creator_who_applied(): void
    {
        $creator = $this->member('applicant@example.test');
        $profile = app(CreatorOnboardingService::class)->provision($creator->id, $creator->name);

        $brand = BrandProfile::query()->create([
            'user_id' => $this->member('thebrand@example.test')->id,
            'company_name' => 'The Brand',
            'slug' => 'the-brand',
            'verification_status' => 'approved',
        ]);

        $campaign = Campaign::query()->create([
            'brand_profile_id' => $brand->id,
            'name' => 'Summer push',
            'slug' => 'summer-push',
            'status' => 'published',
            'budget_minor' => 500000,
        ]);

        $application = CampaignApplication::query()->create([
            'campaign_id' => $campaign->id,
            'creator_profile_id' => $profile->id,
            'status' => 'applied',
            'proposed_fee_minor' => 100000,
        ]);

        app(MarketplaceEngine::class)->transitionApplication($application, 'shortlisted');

        // An application belongs to a creator profile, not to a user directly.
        // Reading user_id off it produced null and quietly notified nobody.
        $this->assertSame(1, $creator->fresh()->notifications()->count());
    }

    public function test_a_new_message_notifies_the_other_side_only(): void
    {
        $creator = $this->member('creator@example.test');
        $brand = $this->member('brand@example.test');

        $engine = app(MarketplaceEngine::class);
        $conversation = $engine->startInternalChat($creator, $brand, 'Campaign');
        $engine->postInternalMessage($conversation, $brand, 'Are you free in June?');

        $this->assertSame(1, $creator->fresh()->notifications()->count());
        // The sender does not need telling about their own message.
        $this->assertSame(0, $brand->fresh()->notifications()->count());
    }
}
