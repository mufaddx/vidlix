<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationReport;
use App\Models\Role;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\Creator\CreatorOnboardingService;
use App\Services\Marketplace\MarketplaceEngine;
use App\Services\Messaging\InboxQuery;
use App\Services\Notifications\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Archive, mute, report and block.
 *
 * The point of most of these is that they are per person. One side filing a
 * thread away or silencing it must not do the same to the other side, which is
 * exactly what putting the state on the conversation would have done.
 */
class InboxControlsTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $roleSlug = 'creator'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $role = Role::query()->firstOrCreate(['slug' => $roleSlug], ['name' => ucfirst($roleSlug)]);
        $user->roles()->attach($role);

        return $user->fresh();
    }

    private function thread(User $a, User $b, string $subject = 'A thread'): Conversation
    {
        return app(MarketplaceEngine::class)->startInternalChat($a, $b, $subject);
    }

    /* ------------------------------------------------------------- archive */

    public function test_archiving_hides_a_thread_from_the_inbox_but_keeps_it(): void
    {
        $me = $this->member();
        $them = $this->member('editor');
        $thread = $this->thread($me, $them, 'Tucked aside');

        $this->actingAs($me)
            ->post(route('inbox.archive', $thread->conversation_uuid), ['archived' => 1])
            ->assertRedirect();

        $this->actingAs($me)->get(route('inbox'))->assertDontSee('Tucked aside', false);
        $this->actingAs($me)->get(route('inbox', ['archived' => 1]))->assertSee('Tucked aside', false);

        $this->assertDatabaseHas('conversations', ['id' => $thread->id]);
    }

    public function test_archiving_is_mine_alone(): void
    {
        $me = $this->member();
        $them = $this->member('editor');
        $thread = $this->thread($me, $them, 'Shared thread');

        $this->actingAs($me)->post(route('inbox.archive', $thread->conversation_uuid), ['archived' => 1]);

        // The other side never asked for their inbox to be tidied.
        $this->actingAs($them)->get(route('inbox'))->assertSee('Shared thread', false);
    }

    public function test_a_thread_can_come_back_out_of_the_archive(): void
    {
        $me = $this->member();
        $thread = $this->thread($me, $this->member('editor'), 'Returning');

        $this->actingAs($me)->post(route('inbox.archive', $thread->conversation_uuid), ['archived' => 1]);
        $this->actingAs($me)->post(route('inbox.archive', $thread->conversation_uuid), ['archived' => 0]);

        $this->actingAs($me)->get(route('inbox'))->assertSee('Returning', false);
    }

    /* ---------------------------------------------------------------- mute */

    public function test_muting_stops_the_notification_but_not_the_message(): void
    {
        $me = $this->member();
        $thread = $this->thread($me, $this->member('editor'), 'Noisy');

        $this->actingAs($me)->post(route('inbox.mute', $thread->conversation_uuid), ['muted' => 1]);

        $result = app(Notifier::class)->sendAbout($me->fresh(), $thread, 'message', 'New message', 'Hello');

        $this->assertSame('muted_by_member', $result['push']);
        $this->assertFalse($result['stored']);

        // The thread itself is untouched — muting is about interruption, not
        // about refusing delivery.
        $this->actingAs($me)->get(route('inbox'))->assertSee('Noisy', false);
    }

    public function test_muting_is_mine_alone(): void
    {
        $me = $this->member();
        $them = $this->member('editor');
        $thread = $this->thread($me, $them, 'Two sides');

        $this->actingAs($me)->post(route('inbox.mute', $thread->conversation_uuid), ['muted' => 1]);

        $theirs = app(Notifier::class)->sendAbout($them->fresh(), $thread, 'message', 'New message', 'Hello');

        $this->assertNotSame('muted_by_member', $theirs['push']);
    }

    /* -------------------------------------------------------------- report */

    public function test_reporting_records_it_once_however_many_times_it_is_sent(): void
    {
        $me = $this->member();
        $thread = $this->thread($me, $this->member('editor'), 'Suspicious');

        foreach (['spam', 'scam'] as $reason) {
            $this->actingAs($me)->post(route('inbox.report', $thread->conversation_uuid), [
                'reason' => $reason,
                'detail' => 'Asking for money up front.',
            ])->assertRedirect();
        }

        // The second report is the same complaint again. Two rows would bury
        // the queue rather than add anything to it.
        $this->assertSame(1, ConversationReport::query()->count());
        $this->assertSame('spam', ConversationReport::query()->first()->reason);
    }

    public function test_a_reason_outside_the_list_is_refused(): void
    {
        $me = $this->member();
        $thread = $this->thread($me, $this->member('editor'));

        $this->actingAs($me)
            ->post(route('inbox.report', $thread->conversation_uuid), ['reason' => 'whatever'])
            ->assertSessionHasErrors('reason');
    }

    /* --------------------------------------------------------------- block */

    public function test_blocking_stops_a_new_thread_from_being_started(): void
    {
        $me = $this->member();
        $them = $this->member('editor');
        $thread = $this->thread($me, $them, 'The last one');

        $this->actingAs($me)
            ->post(route('inbox.block', $thread->conversation_uuid))
            ->assertRedirect();

        $this->assertDatabaseHas('user_blocks', [
            'user_id' => $me->id,
            'blocked_user_id' => $them->id,
        ]);

        // Both directions: the block belongs to the pair, not to whoever asks.
        $this->assertTrue(app(MarketplaceEngine::class)->blockedBetween($me->fresh(), $them->fresh()));
        $this->assertTrue(app(MarketplaceEngine::class)->blockedBetween($them->fresh(), $me->fresh()));
    }

    public function test_a_blocked_person_cannot_open_a_new_thread(): void
    {
        $me = $this->member();
        $them = $this->member('editor');

        UserBlock::query()->create(['user_id' => $me->id, 'blocked_user_id' => $them->id]);

        // Blocked in the other direction too: the person who was blocked must
        // not be able to reopen the conversation from their side.
        $this->expectException(HttpException::class);

        app(MarketplaceEngine::class)->startInternalChat($them->fresh(), $me->fresh(), 'Trying again');
    }

    public function test_blocking_keeps_the_history_that_explains_it(): void
    {
        $me = $this->member();
        $them = $this->member('editor');
        $thread = $this->thread($me, $them, 'Evidence');

        $this->actingAs($me)->post(route('inbox.block', $thread->conversation_uuid));

        // Deleting the thread would destroy the record of why it was blocked.
        $this->assertDatabaseHas('conversations', ['id' => $thread->id]);
        $this->actingAs($me)->get(route('inbox'))->assertSee('Evidence', false);
    }

    /* ------------------------------------------------------- authorisation */

    public function test_a_stranger_cannot_act_on_somebody_elses_thread(): void
    {
        $thread = $this->thread($this->member(), $this->member('editor'), 'Private');
        $stranger = $this->member('brand');

        foreach (['inbox.archive', 'inbox.mute', 'inbox.report', 'inbox.block'] as $route) {
            $this->actingAs($stranger)
                ->post(route($route, $thread->conversation_uuid), ['reason' => 'spam'])
                // A 404 rather than a 403: a stranger should not learn that the
                // thread exists at all.
                ->assertNotFound();
        }
    }

    /* ------------------------------------------------------------ ordering */

    public function test_your_own_kind_of_work_comes_first(): void
    {
        $creator = $this->member('creator');
        app(CreatorOnboardingService::class)->provision($creator->id, 'Creator');

        $otherCreator = $this->member('creator');
        $editor = $this->member('editor');
        $brand = $this->member('brand');

        // Created oldest-first, so a plain date sort would put brand on top.
        $brandThread = $this->thread($creator, $brand, 'From a brand');
        $editorThread = $this->thread($creator, $editor, 'From an editor');
        $creatorThread = $this->thread($creator, $otherCreator, 'From a creator');

        $brandThread->update(['last_message_at' => now()->subMinute()]);
        $editorThread->update(['last_message_at' => now()->subMinutes(2)]);
        $creatorThread->update(['last_message_at' => now()->subMinutes(3)]);

        $ordered = collect(app(InboxQuery::class)->forUser($creator->fresh())->items())->pluck('subject')->all();

        $this->assertSame(
            ['From a creator', 'From an editor', 'From a brand'],
            $ordered,
            'A creator should see creator threads first, even when a brand wrote more recently.',
        );
    }

    public function test_filters_still_narrow_by_who_the_other_side_is(): void
    {
        $creator = $this->member('creator');
        $editor = $this->member('editor');
        $brand = $this->member('brand');

        $this->thread($creator, $editor, 'Editor thread');
        $this->thread($creator, $brand, 'Brand thread');

        $inbox = app(InboxQuery::class);

        $this->assertSame(['Editor thread'], collect($inbox->forUser($creator->fresh(), 'editor')->items())->pluck('subject')->all());
        $this->assertSame(['Brand thread'], collect($inbox->forUser($creator->fresh(), 'brand')->items())->pluck('subject')->all());
        $this->assertCount(2, $inbox->forUser($creator->fresh(), 'all')->items());
    }

    public function test_archived_threads_are_left_out_of_the_filter_counts(): void
    {
        $me = $this->member('creator');
        $editor = $this->member('editor');

        $thread = $this->thread($me, $editor, 'Counted once');

        $inbox = app(InboxQuery::class);
        $this->assertSame(1, $inbox->counts($me->fresh())['editor']);

        $inbox->archive($me->fresh(), $thread, true);

        $this->assertSame(0, $inbox->counts($me->fresh())['editor']);
        $this->assertSame(1, $inbox->counts($me->fresh(), true)['editor']);
    }
}
