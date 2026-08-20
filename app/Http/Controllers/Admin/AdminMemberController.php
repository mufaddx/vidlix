<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CampaignApplication;
use App\Models\Conversation;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Project;
use App\Models\SupportThread;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\LedgerService;
use App\Services\Taxonomy\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Everything about one member, on one page.
 *
 * Support work fails when the answer to "what is going on with this account"
 * is spread across six screens. This gathers identity, roles, reach, money,
 * conversations, management and history together, and puts the account
 * controls next to the evidence you would use to decide.
 *
 * Money is read from the ledger, never from a stored balance — the same rule
 * the member's own earnings page follows, so the two can never disagree.
 */
class AdminMemberController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('roles:id,slug')
            ->when($request->query('q'), fn ($query, $term) => $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%')
                    ->orWhere('mobile', 'like', '%'.$term.'%');
            }))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.members', [
            'users' => $users,
            'q' => $request->query('q'),
            'status' => $request->query('status'),
        ]);
    }

    public function show(User $user, LedgerService $ledger, CategoryService $categories): View
    {
        $user->load(['roles:id,slug,name', 'creatorProfile', 'editorProfile', 'brandProfile']);

        $projects = Project::query()
            ->where(fn ($q) => $q->where('owner_user_id', $user->id)->orWhere('counterparty_user_id', $user->id))
            ->latest()->limit(20)->get();

        return view('admin.member', [
            'member' => $user,
            'money' => [
                'available' => $ledger->balance($user->id, LedgerService::STATE_AVAILABLE),
                'reserved' => $ledger->balance($user->id, LedgerService::STATE_RESERVED),
                'withdrawn' => $ledger->balance($user->id, LedgerService::STATE_WITHDRAWN),
            ],
            'ledger' => LedgerEntry::query()
                ->whereIn('ledger_account_id', LedgerAccount::query()->where('user_id', $user->id)->select('id'))
                ->latest()->limit(20)->get(),
            'withdrawals' => Withdrawal::query()->where('user_id', $user->id)->latest()->get(),
            'payments' => Payment::query()->where('payer_user_id', $user->id)->latest()->limit(20)->get(),
            'invoices' => Invoice::query()
                ->where(fn ($q) => $q->where('seller_user_id', $user->id)->orWhere('buyer_user_id', $user->id))
                ->latest()->limit(20)->get(),
            'projects' => $projects,
            'applications' => $user->creatorProfile
                ? CampaignApplication::query()->where('creator_profile_id', $user->creatorProfile->id)
                    ->with('campaign:id,name')->latest()->limit(20)->get()
                : collect(),
            // Who they are talking to, and on which side.
            'conversations' => Conversation::query()
                ->where(fn ($q) => $q->where('owner_user_id', $user->id)
                    ->orWhereHas('participants', fn ($p) => $p->where('user_id', $user->id)))
                ->with('externalContact')
                ->withCount('messages')
                ->latest('last_message_at')
                ->limit(20)->get(),
            'supportThreads' => SupportThread::query()->where('user_id', $user->id)->with('conversation')->latest()->limit(10)->get(),
            'activity' => AuditLog::query()->where('actor_user_id', $user->id)->latest()->limit(30)->get(),
            'creatorCategories' => $user->creatorProfile ? $categories->forProfile($user->creatorProfile) : collect(),
            'editorCategories' => $user->editorProfile ? $categories->forProfile($user->editorProfile) : collect(),
        ]);
    }

    /**
     * Suspend or restore an account.
     *
     * Suspension is reversible and keeps everything: their work, threads and
     * ledger stay exactly as they were. It stops them signing in, nothing else.
     */
    public function updateStatus(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $status = $request->validate([
            'status' => ['required', 'in:active,suspended'],
            'reason' => ['nullable', 'string', 'max:400'],
        ]);

        if ($user->isSuperAdmin()) {
            return back()->withErrors(['status' => __('A super admin account cannot be suspended from here.')]);
        }

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['status' => __('You cannot suspend your own account.')]);
        }

        $user->update(['status' => $status['status']]);
        $audit->record('member.status_changed', $user, [
            'status' => $status['status'],
            'reason' => $status['reason'] ?? null,
        ]);

        return back()->with('status', __('Account is now :status.', ['status' => $status['status']]));
    }

    /** Take a creator's public page down, or put it back up. */
    public function updateVisibility(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $profile = $user->creatorProfile;
        abort_unless($profile !== null, 404);

        $visibility = $request->validate(['visibility' => ['required', 'in:public,private']])['visibility'];
        $profile->update(['visibility' => $visibility]);
        $audit->record('member.visibility_changed', $user, ['visibility' => $visibility]);

        return back()->with('status', __('Public page is now :visibility.', ['visibility' => $visibility]));
    }
}
