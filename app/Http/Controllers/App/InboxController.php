<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationReport;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\Audit\AuditLogger;
use App\Services\Email\OutboundEmailService;
use App\Services\Marketplace\MarketplaceEngine;
use App\Services\Messaging\InboxQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One inbox for every conversation, whatever role you hold.
 *
 * It used to be reachable only through a creator profile, so an editor or a
 * brand had nowhere to read their own mail. Access is now decided by
 * participation or ownership of the thread, which is also what stops one
 * member opening another's.
 */
class InboxController extends Controller
{
    public function index(Request $request, InboxQuery $inbox): View
    {
        $filter = (string) $request->query('filter', 'all');
        $search = $request->query('q');
        $archived = $request->boolean('archived');

        $conversations = $inbox->forUser($request->user(), $filter, $search, 25, $archived);

        return view('app.inbox-index', [
            'conversations' => $conversations,
            'filter' => in_array($filter, InboxQuery::FILTERS, true) ? $filter : 'all',
            'filters' => InboxQuery::FILTERS,
            'counts' => $inbox->counts($request->user(), $archived),
            'unread' => $inbox->unreadCounts($request->user(), $conversations->items()),
            'search' => $search,
            'archived' => $archived,
        ]);
    }

    public function show(Request $request, string $uuid, InboxQuery $inbox): View
    {
        $conversation = $this->authorised($request, $uuid);
        $inbox->markRead($request->user(), $conversation);

        return view('app.inbox-show', [
            'conversation' => $conversation->load(['messages.actor', 'externalContact', 'participants.user:id,name']),
            'isExternal' => $conversation->channel === 'external_email',
            'isArchived' => $inbox->isArchived($request->user(), $conversation),
            'isMuted' => $inbox->isMuted($request->user(), $conversation),
            'reported' => ConversationReport::query()
                ->where('conversation_id', $conversation->id)
                ->where('reported_by_user_id', $request->user()->id)
                ->exists(),
            'reasons' => ConversationReport::REASONS,
            'blockable' => $this->counterpart($request, $conversation),
        ]);
    }

    public function reply(
        Request $request,
        string $uuid,
        AuditLogger $audit,
        OutboundEmailService $outbound,
        MarketplaceEngine $engine,
    ): RedirectResponse {
        $conversation = $this->authorised($request, $uuid);
        $data = $request->validate(['body' => ['required', 'string', 'max:8000']]);

        // An internal thread stays inside Vidlix; an external one leaves as
        // email and is only ever reported as the provider reports it.
        if ($conversation->channel !== 'external_email') {
            $engine->postInternalMessage($conversation, $request->user(), $data['body']);
            $audit->record('inbox.replied', $conversation, ['channel' => 'internal']);

            return back()->with('status', __('Message sent.'));
        }

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'actor_user_id' => $request->user()->id,
            'direction' => 'outbound',
            'body' => $data['body'],
            'delivery_status' => $outbound->initialStatus(),
        ]);

        $conversation->update(['last_message_at' => now()]);
        $audit->record('inbox.replied', $conversation, ['channel' => 'email']);
        $outbound->queue($message);

        return back()->with('status', $message->delivery_status === 'queued'
            ? __('Reply queued. It counts as sent only when the provider confirms it.')
            : __('Reply stored. Email delivery waits on a configured provider.'));
    }

    public function archive(Request $request, string $uuid, InboxQuery $inbox): RedirectResponse
    {
        $conversation = $this->authorised($request, $uuid);
        $archived = $request->boolean('archived');

        $inbox->archive($request->user(), $conversation, $archived);

        return back()->with('status', $archived
            ? __('Filed away. It is still here under Archived.')
            : __('Back in your inbox.'));
    }

    public function mute(Request $request, string $uuid, InboxQuery $inbox): RedirectResponse
    {
        $conversation = $this->authorised($request, $uuid);
        $muted = $request->boolean('muted');

        $inbox->mute($request->user(), $conversation, $muted);

        return back()->with('status', $muted
            ? __('Muted. The thread stays, the notifications stop.')
            : __('Notifications back on for this thread.'));
    }

    public function report(Request $request, string $uuid, AuditLogger $audit): RedirectResponse
    {
        $conversation = $this->authorised($request, $uuid);

        $data = $request->validate([
            'reason' => ['required', 'string', 'in:'.implode(',', ConversationReport::REASONS)],
            'detail' => ['nullable', 'string', 'max:2000'],
        ]);

        // firstOrCreate rather than create: reporting twice is the same
        // complaint again, and a duplicate would bury the queue rather than
        // add to it. The person is told it is already with us either way.
        ConversationReport::query()->firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'reported_by_user_id' => $request->user()->id,
            ],
            [
                'reason' => $data['reason'],
                'detail' => $data['detail'] ?? null,
                'status' => 'open',
            ],
        );

        $audit->record('conversation.reported', $conversation, ['reason' => $data['reason']]);

        return back()->with('status', __('Reported. Someone will look at this thread.'));
    }

    public function block(Request $request, string $uuid, AuditLogger $audit): RedirectResponse
    {
        $conversation = $this->authorised($request, $uuid);
        $other = $this->counterpart($request, $conversation);

        // An external contact has no account to block; blocking a stranger by
        // email is a mail rule, not a relationship, and pretending otherwise
        // would be a button that quietly does nothing.
        abort_unless($other !== null, 404);

        UserBlock::query()->firstOrCreate(
            ['user_id' => $request->user()->id, 'blocked_user_id' => $other->id],
            ['reason' => $request->string('reason')->limit(200)->toString() ?: null],
        );

        $audit->record('user.blocked', $conversation, ['blocked_user_id' => $other->id]);

        return back()->with('status', __(':name can no longer start a new thread with you.', [
            'name' => $other->name,
        ]));
    }

    /**
     * The other member in this thread, if there is exactly one.
     *
     * Null for an external email thread and for anything with more than two
     * people in it, because "block" has no obvious meaning in either case.
     */
    private function counterpart(Request $request, Conversation $conversation): ?User
    {
        $ids = $conversation->participants()
            ->where('user_id', '!=', $request->user()->id)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        return $ids->count() === 1 ? User::query()->find($ids->first()) : null;
    }

    /**
     * A thread is yours if you own it or take part in it. Anything else is a
     * 404 rather than a 403, so the inbox cannot be used to discover that
     * somebody else's conversation exists.
     */
    private function authorised(Request $request, string $uuid): Conversation
    {
        $user = $request->user();

        $conversation = Conversation::query()
            ->where('conversation_uuid', $uuid)
            ->where('channel', '!=', 'support')
            ->where(function ($q) use ($user) {
                $q->where('owner_user_id', $user->id)
                    ->orWhereHas('participants', fn ($p) => $p->where('user_id', $user->id));
            })
            ->first();

        abort_unless($conversation !== null, 404);

        return $conversation;
    }
}
