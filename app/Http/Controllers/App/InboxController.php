<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Audit\AuditLogger;
use App\Services\Email\OutboundEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InboxController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->creatorProfile;
        abort_unless($profile, 403);

        $conversations = Conversation::query()
            ->where('creator_profile_id', $profile->id)
            ->with('externalContact')
            ->latest('last_message_at')
            ->paginate(20);

        return view('app.inbox-index', compact('conversations'));
    }

    public function show(Request $request, string $uuid): View
    {
        $profile = $request->user()->creatorProfile;
        abort_unless($profile, 403);

        $conversation = Conversation::query()
            ->where('conversation_uuid', $uuid)
            ->where('creator_profile_id', $profile->id)
            ->with(['messages.actor', 'externalContact'])
            ->firstOrFail();

        return view('app.inbox-show', compact('conversation'));
    }

    public function reply(Request $request, string $uuid, AuditLogger $audit, OutboundEmailService $outbound): RedirectResponse
    {
        $profile = $request->user()->creatorProfile;
        abort_unless($profile, 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:8000']]);

        $conversation = Conversation::query()
            ->where('conversation_uuid', $uuid)
            ->where('creator_profile_id', $profile->id)
            ->firstOrFail();

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'actor_user_id' => $request->user()->id,
            'acting_for_creator_id' => session('acting_for_creator_id'),
            'direction' => 'outbound',
            'body' => $data['body'],
            'delivery_status' => $outbound->initialStatus(),
        ]);

        $conversation->update(['last_message_at' => now()]);
        $audit->record('inbox.replied', $conversation);

        // The reply is stored either way; the queued job is what actually
        // attempts delivery, and only the provider may call it delivered.
        $outbound->queue($message);

        return back()->with('status', $message->delivery_status === 'queued'
            ? __('Reply stored and queued for delivery. It is marked sent only when the provider confirms it.')
            : __('Reply stored. Email delivery waits on a configured provider.'));
    }
}
