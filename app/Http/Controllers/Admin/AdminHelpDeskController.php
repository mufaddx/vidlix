<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportThread;
use App\Services\Support\HelpDesk;
use App\Support\Ability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The help desk, answered from the admin panel.
 *
 * Everything arrives here — mail to help@, and tickets raised inside the app —
 * and a staff reply goes back out as email on the same thread.
 */
class AdminHelpDeskController extends Controller
{
    public function index(Request $request, HelpDesk $desk): View
    {
        $status = $request->query('status', 'open');

        return view('admin.help-desk', [
            'threads' => SupportThread::query()
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->with([
                    'conversation' => fn ($q) => $q->withCount('messages')->with('externalContact'),
                    'user:id,name,email',
                    'assignee:id,name',
                ])
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'status' => $status,
            'address' => $desk->address(),
            'canReply' => $request->user()->hasAbility(Ability::SUPPORT_REPLY),
        ]);
    }

    public function show(Request $request, SupportThread $thread): View
    {
        return view('admin.help-desk-thread', [
            'thread' => $thread->load(['conversation.externalContact', 'user:id,name,email']),
            'messages' => $thread->conversation->messages()->orderBy('id')->get(),
            'canReply' => $request->user()->hasAbility(Ability::SUPPORT_REPLY),
        ]);
    }

    public function reply(Request $request, SupportThread $thread, HelpDesk $desk): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:8000']]);
        $message = $desk->reply($thread, $request->user(), $data['body']);

        return back()->with('status', $message->delivery_status === 'queued'
            ? __('Reply queued. It counts as sent only when the provider confirms it.')
            : __('Reply stored. No email provider is configured, so nothing was sent.'));
    }

    public function close(Request $request, SupportThread $thread, HelpDesk $desk): RedirectResponse
    {
        $desk->close($thread, $request->user());

        return back()->with('status', __('Thread :ref closed.', ['ref' => $thread->reference]));
    }
}
