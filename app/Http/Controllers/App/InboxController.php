<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
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

        $conversations = $inbox->forUser($request->user(), $filter, $search);

        return view('app.inbox-index', [
            'conversations' => $conversations,
            'filter' => in_array($filter, InboxQuery::FILTERS, true) ? $filter : 'all',
            'filters' => InboxQuery::FILTERS,
            'counts' => $inbox->counts($request->user()),
            'unread' => $inbox->unreadCounts($request->user(), $conversations->items()),
            'search' => $search,
        ]);
    }

    public function show(Request $request, string $uuid, InboxQuery $inbox): View
    {
        $conversation = $this->authorised($request, $uuid);
        $inbox->markRead($request->user(), $conversation);

        return view('app.inbox-show', [
            'conversation' => $conversation->load(['messages.actor', 'externalContact', 'participants.user:id,name']),
            'isExternal' => $conversation->channel === 'external_email',
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
            'acting_for_creator_id' => session('acting_for_creator_id'),
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
