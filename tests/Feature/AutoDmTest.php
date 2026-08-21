<?php

namespace Tests\Feature;

use App\Contracts\InstagramProviderInterface;
use App\Models\AutodmAutomation;
use App\Models\AutodmEvent;
use App\Models\AutodmRun;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Models\InstagramMedium;
use App\Models\User;
use App\Services\AutoDm\AutomationBuilder;
use App\Services\AutoDm\AutomationEngine;
use App\Services\AutoDm\Capabilities;
use App\Services\AutoDm\KeywordMatcher;
use App\Services\Creator\CreatorOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Comment-to-DM automation.
 *
 * Two things are defended hardest, because getting either wrong is worse than
 * the feature not existing.
 *
 * Idempotency: Instagram retries deliveries, and a retry must not send a second
 * reply to the same person.
 *
 * Honesty: an action the platform does not permit is recorded as skipped with a
 * reason — never as sent, and never as failed. Calling it sent is a lie in the
 * log somebody reads to find out what happened; calling it failed invites a
 * retry that can never succeed.
 */
class AutoDmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A configured provider, so what is under test is the capability rules
        // rather than the fact that no Instagram driver is set up locally.
        // Sending itself is still not wired to anything, which is precisely the
        // "skipped, not sent" case the tests below rely on.
        $this->app->bind(InstagramProviderInterface::class, fn () => new class implements InstagramProviderInterface
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function name(): string
            {
                return 'fake';
            }

            public function authorizationUrl(CreatorProfile $profile): string
            {
                return 'https://vidlix.test/oauth';
            }

            public function completeAuthorization(CreatorProfile $profile, string $code): array
            {
                return ['status' => 'ok', 'detail' => 'Connected.'];
            }

            public function syncPermittedData(CreatorProfile $profile): array
            {
                return ['status' => 'ok', 'insights' => [], 'detail' => 'Synced.'];
            }
        });
    }

    private function creatorWithInstagram(array $scopes = ['instagram_basic']): InstagramAccount
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $profile = app(CreatorOnboardingService::class)->provision($user->id, 'Mira Rao');

        return InstagramAccount::query()->updateOrCreate(
            ['creator_profile_id' => $profile->id],
            [
                'status' => 'connected',
                'ig_user_id' => 'ig-'.$profile->id,
                'username' => 'mira',
                'granted_scopes' => $scopes,
                'authorized_at' => now(),
            ],
        );
    }

    private function ownerOf(InstagramAccount $account): User
    {
        $profile = CreatorProfile::query()->findOrFail($account->creator_profile_id);

        return User::query()->findOrFail($profile->user_id);
    }

    /** @param array<string, mixed> $overrides */
    private function draft(InstagramAccount $account, array $overrides = []): AutodmAutomation
    {
        return app(AutomationBuilder::class)->create(
            $this->ownerOf($account),
            $account,
            array_merge([
                'name' => 'Link replies',
                'trigger_type' => 'keywords',
                'keywords' => "link\nprice",
                'public_reply_enabled' => true,
                'public_reply_text' => 'Sent you the link!',
            ], $overrides),
        );
    }

    /** @param array<string, mixed> $overrides */
    private function comment(InstagramAccount $account, array $overrides = []): array
    {
        return array_merge([
            'event_id' => 'evt-'.uniqid(),
            'ig_user_id' => $account->ig_user_id,
            'media_id' => 'media-1',
            'comment_id' => 'c-'.uniqid(),
            'commenter_id' => 'u-99',
            'text' => 'please send the link',
            'commented_at' => now()->toIso8601String(),
        ], $overrides);
    }

    /* ------------------------------------------------------------- matching */

    public function test_keywords_ignore_capitals_and_accents(): void
    {
        $account = $this->creatorWithInstagram();
        $automation = $this->draft($account, ['keywords' => "café\nLINK"]);
        $version = $automation->draftVersion();

        $matcher = app(KeywordMatcher::class);

        // People do not type carefully in comments.
        $this->assertTrue($matcher->matches($version, 'Where is the CAFE?'));
        $this->assertTrue($matcher->matches($version, 'send me the link please'));
        $this->assertFalse($matcher->matches($version, 'nice photo'));
    }

    public function test_whole_word_matching_can_be_asked_for(): void
    {
        $account = $this->creatorWithInstagram();

        $loose = $this->draft($account, ['keywords' => 'art'])->draftVersion();
        $strict = $this->draft($account, ['keywords' => 'art', 'whole_word' => true])->draftVersion();

        $matcher = app(KeywordMatcher::class);

        $this->assertTrue($matcher->matches($loose, 'lets start now'));
        $this->assertFalse($matcher->matches($strict, 'lets start now'));
        $this->assertTrue($matcher->matches($strict, 'love your art'));
    }

    public function test_a_keyword_automation_with_no_keywords_matches_nothing(): void
    {
        // Rather than everything, which would turn an unfinished automation
        // into a reply to every comment on the account.
        $account = $this->creatorWithInstagram();

        $this->expectException(ValidationException::class);

        $this->draft($account, ['keywords' => '']);
    }

    /* ---------------------------------------------------------- idempotency */

    public function test_the_same_delivery_twice_only_ever_runs_once(): void
    {
        $account = $this->creatorWithInstagram();
        $automation = $this->draft($account);
        app(AutomationBuilder::class)->activate($automation, $this->ownerOf($account));

        $payload = $this->comment($account);
        $engine = app(AutomationEngine::class);

        $first = $engine->handleComment($payload);
        $second = $engine->handleComment($payload);

        $this->assertSame('matched', $first['status']);
        $this->assertSame('duplicate', $second['status']);

        // Instagram retries. One comment, one reply.
        $this->assertSame(1, AutodmRun::query()->count());
        $this->assertSame(1, AutodmEvent::query()->count());
    }

    public function test_a_delivery_with_no_event_id_is_ignored_rather_than_guessed_at(): void
    {
        $account = $this->creatorWithInstagram();
        $this->draft($account);

        $result = app(AutomationEngine::class)->handleComment(['event_id' => '']);

        $this->assertSame('ignored', $result['status']);
        $this->assertSame(0, AutodmEvent::query()->count());
    }

    public function test_a_comment_on_an_unknown_account_is_recorded_but_does_nothing(): void
    {
        $result = app(AutomationEngine::class)->handleComment([
            'event_id' => 'evt-stranger',
            'ig_user_id' => 'not-ours',
            'text' => 'link',
        ]);

        $this->assertSame('ignored', $result['status']);
        $this->assertSame(0, AutodmRun::query()->count());
    }

    /* ------------------------------------------------------------- scoping */

    public function test_an_automation_bound_to_one_post_does_not_fire_on_another(): void
    {
        $account = $this->creatorWithInstagram();

        $medium = InstagramMedium::query()->create([
            'instagram_account_id' => $account->id,
            'media_id' => 'media-1',
            'media_type' => 'REEL',
            'published_at' => now(),
        ]);

        $automation = $this->draft($account, ['instagram_media_id' => $medium->id]);
        app(AutomationBuilder::class)->activate($automation, $this->ownerOf($account));

        $engine = app(AutomationEngine::class);

        $engine->handleComment($this->comment($account, ['media_id' => 'media-2']));
        $this->assertSame(0, AutodmRun::query()->count());

        $engine->handleComment($this->comment($account, ['media_id' => 'media-1']));
        $this->assertSame(1, AutodmRun::query()->count());
    }

    public function test_a_draft_automation_never_fires(): void
    {
        $account = $this->creatorWithInstagram();
        $this->draft($account);

        $result = app(AutomationEngine::class)->handleComment($this->comment($account));

        $this->assertSame('unmatched', $result['status']);
        $this->assertSame(0, AutodmRun::query()->count());
    }

    public function test_switching_an_automation_off_stops_it(): void
    {
        $account = $this->creatorWithInstagram();
        $automation = $this->draft($account);
        $builder = app(AutomationBuilder::class);
        $owner = $this->ownerOf($account);

        $builder->activate($automation, $owner);
        $builder->deactivate($automation->fresh(), $owner);

        app(AutomationEngine::class)->handleComment($this->comment($account));

        $this->assertSame(0, AutodmRun::query()->count());
    }

    /* --------------------------------------------------------- capabilities */

    public function test_a_private_reply_cannot_be_activated_without_the_permission(): void
    {
        // instagram_manage_messages is granted only after Meta reviews the app.
        $account = $this->creatorWithInstagram(['instagram_basic']);

        $automation = $this->draft($account, [
            'public_reply_enabled' => false,
            'private_reply_enabled' => true,
            'private_reply_text' => 'Here you go',
        ]);

        // Refused here, not at 2am when a comment arrives. An automation that
        // cannot run must not look armed.
        $this->expectException(ValidationException::class);

        app(AutomationBuilder::class)->activate($automation, $this->ownerOf($account));
    }

    public function test_a_private_reply_is_allowed_once_the_permission_is_granted(): void
    {
        $account = $this->creatorWithInstagram(['instagram_basic', 'instagram_manage_messages']);

        $this->assertTrue(app(Capabilities::class)->allows($account, Capabilities::PRIVATE_REPLY));
    }

    public function test_the_reason_is_specific_rather_than_a_shrug(): void
    {
        $account = $this->creatorWithInstagram(['instagram_basic']);

        $check = app(Capabilities::class)->check($account, Capabilities::PRIVATE_REPLY);

        $this->assertFalse($check['allowed']);
        $this->assertSame('app_review_pending', $check['reason_code']);
        $this->assertStringContainsString('app review', (string) $check['reason']);
    }

    public function test_an_expired_connection_permits_nothing(): void
    {
        $account = $this->creatorWithInstagram();
        $account->update(['token_expires_at' => now()->subDay()]);

        $check = app(Capabilities::class)->check($account->fresh(), Capabilities::PUBLIC_REPLY);

        $this->assertFalse($check['allowed']);
        $this->assertSame('token_expired', $check['reason_code']);
    }

    /* -------------------------------------------------- skipped, not failed */

    public function test_an_unsendable_action_is_skipped_with_a_reason_not_recorded_as_sent(): void
    {
        $account = $this->creatorWithInstagram();
        $automation = $this->draft($account);
        app(AutomationBuilder::class)->activate($automation, $this->ownerOf($account));

        app(AutomationEngine::class)->handleComment($this->comment($account));

        $run = AutodmRun::query()->firstOrFail();

        // No provider is configured in tests, so nothing could be sent. That is
        // not a failure to chase and it is certainly not a success.
        $this->assertSame(AutodmRun::SKIPPED, $run->status);
        $this->assertNotNull($run->reason_code);
        $this->assertFalse($run->succeeded());
        $this->assertNull($run->next_attempt_at);
    }

    public function test_a_private_reply_outside_the_window_is_skipped(): void
    {
        $account = $this->creatorWithInstagram(['instagram_basic', 'instagram_manage_messages']);

        $automation = $this->draft($account, [
            'public_reply_enabled' => false,
            'private_reply_enabled' => true,
            'private_reply_text' => 'Here you go',
        ]);
        app(AutomationBuilder::class)->activate($automation, $this->ownerOf($account));

        app(AutomationEngine::class)->handleComment($this->comment($account, [
            'commented_at' => now()->subDays(3)->toIso8601String(),
        ]));

        $run = AutodmRun::query()->firstOrFail();

        $this->assertSame(AutodmRun::SKIPPED, $run->status);
        $this->assertSame('outside_messaging_window', $run->reason_code);
    }

    /* -------------------------------------------------------- authorisation */

    public function test_you_cannot_build_an_automation_on_somebody_elses_post(): void
    {
        $mine = $this->creatorWithInstagram();
        $theirs = $this->creatorWithInstagram();

        $theirMedium = InstagramMedium::query()->create([
            'instagram_account_id' => $theirs->id,
            'media_id' => 'their-media',
            'published_at' => now(),
        ]);

        $this->expectException(HttpException::class);

        $this->draft($mine, ['instagram_media_id' => $theirMedium->id]);
    }

    public function test_you_cannot_open_somebody_elses_automation(): void
    {
        $account = $this->creatorWithInstagram();
        $automation = $this->draft($account);

        $stranger = User::factory()->create(['email_verified_at' => now()]);

        foreach ([
            route('autodm.edit', $automation->uuid),
            route('autodm.review', $automation->uuid),
            route('autodm.runs', $automation->uuid),
        ] as $url) {
            $this->actingAs($stranger)->get($url)->assertNotFound();
        }

        $this->actingAs($stranger)
            ->post(route('autodm.activate', $automation->uuid))
            ->assertNotFound();

        $this->assertSame(AutodmAutomation::DRAFT, $automation->fresh()->status);
    }

    /* ------------------------------------------------------------ versioning */

    public function test_activation_freezes_the_terms_into_a_version(): void
    {
        $account = $this->creatorWithInstagram();
        $automation = $this->draft($account);
        $builder = app(AutomationBuilder::class);
        $owner = $this->ownerOf($account);

        $builder->activate($automation, $owner);
        $activeId = $automation->fresh()->active_version_id;

        // Editing afterwards writes a new version and leaves the running one
        // exactly as it was, so a past run can still say what produced it.
        $builder->saveDraft($automation->fresh(), [
            'trigger_type' => 'keywords',
            'keywords' => 'completely different',
            'public_reply_enabled' => true,
            'public_reply_text' => 'Changed',
        ]);

        $this->assertSame($activeId, $automation->fresh()->active_version_id);
        $this->assertSame('Sent you the link!', $automation->fresh()->activeVersion()->public_reply_text);
    }

    public function test_a_duplicate_is_always_a_draft(): void
    {
        $account = $this->creatorWithInstagram();
        $automation = $this->draft($account);
        $builder = app(AutomationBuilder::class);
        $owner = $this->ownerOf($account);

        $builder->activate($automation, $owner);
        $copy = $builder->duplicate($automation->fresh(), $owner);

        // Copying something must not switch it on.
        $this->assertSame(AutodmAutomation::DRAFT, $copy->status);
        $this->assertNull($copy->active_version_id);
    }

    /* --------------------------------------------------------- landing page */

    public function test_the_landing_page_states_the_limits_rather_than_selling_around_them(): void
    {
        $this->get(route('autodm.landing'))
            ->assertOk()
            ->assertSee('There are no follow-ups', false)
            ->assertSee('No messaging strangers', false)
            ->assertSee('Nothing is scraped', false);
    }
}
