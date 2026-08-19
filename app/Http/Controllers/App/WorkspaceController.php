<?php

namespace App\Http\Controllers\App;

use App\Contracts\InstagramProviderInterface;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\BlogPost;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Conversation;
use App\Models\Dispute;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\ManagementPlan;
use App\Models\ManagerAssignment;
use App\Models\ManagerInvitation;
use App\Models\Payment;
use App\Models\PortfolioItem;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Identity\AccountProvisioner;
use App\Services\Ledger\LedgerService;
use App\Services\Managers\ManagerDirectory;
use App\Services\Marketplace\MarketplaceEngine;
use App\Services\Support\HelpDesk;
use App\Services\Workspace\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function __construct(private MarketplaceEngine $engine) {}

    public function switch(Request $request, WorkspaceContext $workspace): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', 'string']]);
        $workspace->switchRole($request->user(), $data['role']);

        return back();
    }

    /** Account switcher. The owner id is checked against live assignments. */
    public function manage(Request $request, WorkspaceContext $workspace): RedirectResponse
    {
        $data = $request->validate([
            'owner_user_id' => ['required', 'integer'],
            'scope' => ['nullable', 'in:creator,brand,editor'],
        ]);

        if ((int) $data['owner_user_id'] === $request->user()->id) {
            $workspace->actAsSelf();

            return redirect()->route('dashboard');
        }

        $workspace->actAs($request->user(), (int) $data['owner_user_id'], (string) ($data['scope'] ?? ''));

        return redirect()->route('dashboard');
    }

    public function editors(): View
    {
        $this->ensureRole(request()->user(), 'editor');

        return view('app.editor-home', ['profile' => request()->user()->fresh()->editorProfile]);
    }

    public function brandProfile(): View
    {
        $this->ensureRole(request()->user(), 'brand');

        return view('app.partials.brand-form', ['profile' => request()->user()->fresh()->brandProfile]);
    }

    private function ensureRole(User $user, string $role): void
    {
        app(AccountProvisioner::class)->provisionRole($user, $role);
        $user->roles()->syncWithoutDetaching(Role::query()->where('slug', $role)->pluck('id'));
    }

    public function applyEditor(Request $request): RedirectResponse
    {
        $this->ensureRole($request->user(), 'editor');
        $profile = $request->user()->fresh()->editorProfile;
        abort_unless($profile, 403);
        $data = $request->validate([
            'bio' => ['required', 'string', 'max:3000'],
            'software' => ['nullable', 'string'],
            'specializations' => ['nullable', 'string'],
            'starting_price_minor' => ['nullable', 'integer', 'min:0'],
        ]);
        $profile->update([
            'bio' => $data['bio'],
            'software' => array_filter(array_map('trim', explode(',', $data['software'] ?? ''))),
            'specializations' => array_filter(array_map('trim', explode(',', $data['specializations'] ?? ''))),
            'starting_price_minor' => $data['starting_price_minor'] ?? null,
            'application_status' => 'pending_review',
        ]);

        return back()->with('status', __('Editor application submitted for admin review.'));
    }

    public function saveBrand(Request $request): RedirectResponse
    {
        $this->ensureRole($request->user(), 'brand');
        $profile = $request->user()->fresh()->brandProfile;
        abort_unless($profile, 403);
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:160'],
            'website' => ['nullable', 'url'],
            'industry' => ['nullable', 'string', 'max:120'],
        ]);
        $profile->update($data + ['verification_status' => $profile->verification_status === 'verified' ? 'verified' : 'pending_review']);

        return back()->with('status', __('Brand profile saved for verification.'));
    }

    public function campaigns(): View
    {
        $user = request()->user();
        $mine = $user->brandProfile?->campaigns()->latest()->get() ?? collect();
        $open = Campaign::query()->where('status', 'published')->latest()->limit(50)->get();

        return view('app.campaigns', compact('mine', 'open'));
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $brand = $request->user()->brandProfile;
        abort_unless($brand?->isVerified(), 403, __('Brand must be verified to publish campaigns. Save profile and wait for admin.'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'objective' => ['nullable', 'string', 'max:160'],
            'brief' => ['nullable', 'string'],
            'budget_minor' => ['nullable', 'integer', 'min:0'],
            'platform' => ['nullable', 'string', 'max:80'],
        ]);
        Campaign::query()->create($data + [
            'brand_profile_id' => $brand->id,
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'status' => 'draft',
        ]);

        return back()->with('status', __('Campaign drafted. Submit for review next.'));
    }

    public function submitCampaign(Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->brand_profile_id === request()->user()->brandProfile?->id, 403);
        $campaign->update(['status' => 'pending_review']);

        return back()->with('status', __('Campaign sent for review.'));
    }

    public function applyCampaign(Request $request, Campaign $campaign): RedirectResponse
    {
        $data = $request->validate([
            'proposed_fee_minor' => ['required', 'integer', 'min:1'],
            'message' => ['required', 'string', 'max:4000'],
            'availability' => ['nullable', 'string', 'max:120'],
        ]);
        $this->engine->applyToCampaign($request->user(), $campaign, $data);

        return back()->with('status', __('Application stored.'));
    }

    public function applications(): View
    {
        $user = request()->user();
        $asCreator = $user->creatorProfile
            ? CampaignApplication::query()->where('creator_profile_id', $user->creatorProfile->id)->with('campaign')->latest()->get()
            : collect();
        $asBrand = $user->brandProfile
            ? CampaignApplication::query()->whereIn('campaign_id', $user->brandProfile->campaigns()->pluck('id'))->with('creator')->latest()->get()
            : collect();

        return view('app.applications', compact('asCreator', 'asBrand'));
    }

    public function applicationStatus(Request $request, CampaignApplication $application): RedirectResponse
    {
        abort_unless($application->campaign->brand_profile_id === $request->user()->brandProfile?->id, 403);
        $data = $request->validate(['status' => ['required', 'string']]);
        $this->engine->transitionApplication($application, $data['status']);

        return back()->with('status', __('Application updated.'));
    }

    public function projects(): View
    {
        $id = request()->user()->id;
        $projects = Project::query()->where('owner_user_id', $id)->orWhere('counterparty_user_id', $id)->latest()->get();

        return view('app.projects', compact('projects'));
    }

    public function storeProject(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'counterparty_user_id' => ['required', 'exists:users,id'],
            'total_amount_minor' => ['required', 'integer', 'min:1'],
            'advance_amount_minor' => ['nullable', 'integer', 'min:0'],
            'deadline' => ['nullable', 'date'],
        ]);
        $other = User::query()->findOrFail($data['counterparty_user_id']);
        $this->engine->createProject($request->user(), $other, $data);

        return back()->with('status', __('Project created as draft.'));
    }

    public function showProject(Project $project): View
    {
        abort_unless($project->involves(request()->user()), 403);
        $project->load(['files', 'revisions']);
        $payments = Payment::query()->where('payable_type', Project::class)->where('payable_id', $project->id)->latest()->get();
        $invoices = Invoice::query()->where('invoiceable_type', Project::class)->where('invoiceable_id', $project->id)->latest()->get();

        return view('app.project-show', compact('project', 'payments', 'invoices'));
    }

    public function projectTransition(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->involves($request->user()), 403);
        $this->engine->transitionProject($project, $request->validate(['status' => ['required']])['status']);

        return back();
    }

    public function projectFile(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->involves($request->user()), 403);
        $request->validate(['file' => ['required', 'file'], 'kind' => ['required', 'string']]);
        $this->engine->storeProjectFile($project, $request->user(), $request->file('file'), $request->string('kind')->toString(), $request->boolean('watermarked'));

        return back()->with('status', __('File stored in object storage metadata (local private disk).'));
    }

    public function projectRevision(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->involves($request->user()), 403);
        $this->engine->requestRevision($project, $request->user(), $request->validate(['feedback' => ['required', 'string']])['feedback']);

        return back()->with('status', __('Revision requested.'));
    }

    public function projectPay(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->involves($request->user()), 403);
        $amount = (int) $request->validate(['amount_minor' => ['required', 'integer', 'min:1']])['amount_minor'];
        $payment = $this->engine->requestPayment($request->user(), $project, $amount, 'project');

        return back()->with('status', __('Payment record '.$payment->status.'. Provider confirmation is required before funds move.'));
    }

    public function chat(): View
    {
        $ids = DB::table('conversation_participants')->where('user_id', request()->user()->id)->pluck('conversation_id');
        $conversations = Conversation::query()->whereIn('id', $ids)->where('channel', 'internal')->latest('last_message_at')->get();

        return view('app.chat-index', compact('conversations'));
    }

    public function startChat(Request $request): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'subject' => ['required', 'string', 'max:160']]);
        $conversation = $this->engine->startInternalChat($request->user(), User::query()->findOrFail($data['user_id']), $data['subject']);

        return redirect()->route('app.chat.show', $conversation->conversation_uuid);
    }

    public function showChat(string $uuid): View
    {
        $conversation = Conversation::query()->where('conversation_uuid', $uuid)->where('channel', 'internal')->with('messages')->firstOrFail();
        abort_unless($conversation->participants()->where('user_id', request()->user()->id)->exists(), 403);

        return view('app.chat-show', compact('conversation'));
    }

    public function chatReply(Request $request, string $uuid): RedirectResponse
    {
        $conversation = Conversation::query()->where('conversation_uuid', $uuid)->firstOrFail();
        $this->engine->postInternalMessage($conversation, $request->user(), $request->validate(['body' => ['required', 'string']])['body']);

        return back();
    }

    public function earnings(LedgerService $ledgerService): View
    {
        $user = request()->user();
        $accountIds = $user->ledgerAccounts()->pluck('id');
        $ledger = LedgerEntry::query()->whereIn('ledger_account_id', $accountIds)->latest()->get();
        $withdrawals = Withdrawal::query()->where('user_id', $user->id)->latest()->get();

        return view('app.earnings', [
            // Every figure below is a SUM over ledger entries, never a stored balance.
            'available' => $ledgerService->balance($user->id, LedgerService::STATE_AVAILABLE),
            'reserved' => $ledgerService->balance($user->id, LedgerService::STATE_RESERVED),
            'ledger' => $ledger,
            'withdrawals' => $withdrawals,
            'count' => $ledger->count(),
        ]);
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $this->engine->requestWithdrawal($request->user(), (int) $request->validate(['amount_minor' => ['required', 'integer', 'min:1']])['amount_minor']);

        return back()->with('status', __('Withdrawal requested. Payout waits on provider + admin verification.'));
    }

    public function managers(ManagerDirectory $directory): View
    {
        $user = request()->user();

        return view('app.managers', [
            // Managers this person has appointed over their own accounts.
            'invites' => ManagerInvitation::query()->where('owner_user_id', $user->id)->latest()->get(),
            'appointed' => ManagerAssignment::query()->where('owner_user_id', $user->id)->with('manager:id,name,email')->get(),
            // Accounts this person manages for somebody else.
            'representing' => ManagerAssignment::query()->active()->where('manager_user_id', $user->id)->with('owner:id,name,email')->get(),
            'scopes' => $directory->ownedScopes($user),
            'plans' => ManagementPlan::query()->where('is_active', true)->get(),
        ]);
    }

    public function inviteManager(Request $request, ManagerDirectory $directory): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', 'in:creator,brand,editor'],
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:120'],
            'mobile' => ['nullable', 'string', 'max:20'],
        ]);
        $directory->invite($request->user(), $data['scope'], $data);

        return back()->with('status', __('Invitation stored. Manager must accept with the same email.'));
    }

    public function acceptInvite(Request $request, string $token, ManagerDirectory $directory): RedirectResponse
    {
        $invitation = $directory->findOpenInvitation($token);
        abort_unless($invitation !== null, 404);
        $directory->acceptAsExistingUser($invitation, $request->user());

        return back()->with('status', __('Manager access active. Use the account switcher to act for that account.'));
    }

    public function subscribe(Request $request): RedirectResponse
    {
        abort_unless($request->user()->creatorProfile, 403);
        $this->engine->subscribe($request->user(), (int) $request->validate(['plan_id' => ['required', 'exists:management_plans,id']])['plan_id']);

        return back()->with('status', __('Subscription recorded. Charging requires a payment provider.'));
    }

    public function revokeManager(ManagerAssignment $assignment, ManagerDirectory $directory): RedirectResponse
    {
        $directory->revoke(request()->user(), $assignment);

        return back()->with('status', __('Manager access revoked. It stops on their very next request.'));
    }

    public function automations(InstagramProviderInterface $instagram): View
    {
        $profile = request()->user()->creatorProfile;
        abort_unless($profile, 403);
        $items = Automation::query()->where('creator_profile_id', $profile->id)->latest()->get();

        return view('app.automations', ['items' => $items, 'configured' => $instagram->isConfigured()]);
    }

    public function storeAutomation(Request $request): RedirectResponse
    {
        $profile = $request->user()->creatorProfile;
        abort_unless($profile, 403);
        $this->engine->saveAutomation($profile->id, $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'keywords' => ['nullable', 'string'],
        ]));

        return back()->with('status', __('Automation saved. Live DM sending stays disabled without Meta permissions.'));
    }

    /**
     * Shows what the last authorised Graph API read returned. Rendering a page
     * never calls Meta, and an empty insights set stays empty.
     */
    public function instagram(InstagramProviderInterface $instagram): View
    {
        $profile = request()->user()->creatorProfile;
        abort_unless($profile, 403);
        $account = $profile->instagramAccount;

        return view('app.instagram', [
            'profile' => $profile,
            'account' => $account,
            'insights' => $account->insights ?? [],
            'configured' => $instagram->isConfigured(),
        ]);
    }

    public function disputes(): View
    {
        $items = Dispute::query()->where('opened_by', request()->user()->id)->latest()->get();

        return view('app.disputes', compact('items'));
    }

    public function storeDispute(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'reason' => ['required', 'string', 'max:64'],
            'statement' => ['required', 'string'],
        ]);
        $project = Project::query()->findOrFail($data['project_id']);
        abort_unless($project->involves($request->user()), 403);
        $this->engine->openDispute($request->user(), $project, $data['reason'], $data['statement']);

        return back()->with('status', __('Dispute opened with evidence chain placeholders.'));
    }

    public function tickets(): View
    {
        $items = SupportTicket::query()->where('user_id', request()->user()->id)->latest()->get();

        return view('app.tickets', compact('items'));
    }

    public function storeTicket(Request $request, HelpDesk $desk): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:64'],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string'],
        ]);

        // Kept for the member's own ticket list, and mirrored into the help desk
        // so staff answer everything from one place regardless of how it arrived.
        SupportTicket::query()->create($data + [
            'user_id' => $request->user()->id,
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $thread = $desk->openFromMember(
            $request->user(),
            '['.$data['category'].'] '.$data['subject'],
            $data['body'],
        );

        return back()->with('status', __('Ticket :ref opened. We reply to this thread and by email.', [
            'ref' => $thread->reference,
        ]));
    }

    public function notifications(): View
    {
        return view('app.notifications', ['items' => request()->user()->notifications()->latest()->limit(50)->get()]);
    }

    public function settings(): View
    {
        $sessions = DB::table('sessions')->where('user_id', request()->user()->id)->get();

        return view('app.settings', compact('sessions'));
    }

    public function revokeSession(Request $request, string $id): RedirectResponse
    {
        DB::table('sessions')->where('id', $id)->where('user_id', $request->user()->id)->delete();

        return back()->with('status', __('Session revoked.'));
    }

    public function portfolio(): View
    {
        $user = request()->user();
        $owner = $user->creatorProfile ?? $user->editorProfile;
        abort_unless($owner, 403);
        $items = PortfolioItem::query()->where('owner_type', $owner::class)->where('owner_id', $owner->id)->get();

        return view('app.portfolio', compact('items'));
    }

    public function storePortfolio(Request $request): RedirectResponse
    {
        $user = $request->user();
        $owner = $user->creatorProfile ?? $user->editorProfile;
        abort_unless($owner, 403);
        $data = $request->validate(['title' => ['required', 'string'], 'url' => ['nullable', 'url'], 'description' => ['nullable', 'string']]);
        PortfolioItem::query()->create($data + ['owner_type' => $owner::class, 'owner_id' => $owner->id]);

        return back()->with('status', __('Portfolio item saved. Media files use storage keys, not MySQL blobs.'));
    }

    public function proposals(): View
    {
        $id = request()->user()->id;
        $items = Proposal::query()->where('from_user_id', $id)->orWhere('to_user_id', $id)->with('versions')->latest()->get();

        return view('app.proposals', compact('items'));
    }

    public function storeProposal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'to_user_id' => ['required', 'exists:users,id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);
        $this->engine->createProposal($request->user(), User::query()->findOrFail($data['to_user_id']), $request->user(), $data);

        return back()->with('status', __('Proposal version 1 stored immutably.'));
    }

    public function invoices(): View
    {
        $id = request()->user()->id;
        $items = Invoice::query()->where('seller_user_id', $id)->orWhere('buyer_user_id', $id)->latest()->get();

        return view('app.invoices', compact('items'));
    }

    public function blogIndex(): View
    {
        $posts = BlogPost::query()->where('status', 'published')->latest('published_at')->paginate(10);

        return view('public.blog', compact('posts'));
    }
}
