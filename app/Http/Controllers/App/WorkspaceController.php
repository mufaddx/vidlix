<?php

namespace App\Http\Controllers\App;

use App\Contracts\InstagramProviderInterface;
use App\Contracts\PushProviderInterface;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\BlogPost;
use App\Models\BrandDocument;
use App\Models\BrandProfile;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Conversation;
use App\Models\Dispute;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PortfolioItem;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\InvoicePdf;
use App\Services\Identity\AccountProvisioner;
use App\Services\Ledger\LedgerService;
use App\Services\Marketplace\MarketplaceEngine;
use App\Services\Media\MediaStorage;
use App\Services\Notifications\Notifier;
use App\Services\Support\HelpDesk;
use App\Services\Workspace\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function editors(): View
    {
        $this->ensureRole(request()->user(), 'editor');

        return view('app.editor-home', ['profile' => request()->user()->fresh()->editorProfile]);
    }

    public function brandProfile(Request $request): View
    {
        $profile = $this->brandProfileFor($request);

        return view('app.partials.brand-form', [
            'profile' => $profile,
            'documents' => $profile->documents()->latest()->get(),
            'kinds' => BrandDocument::KINDS,
            'missing' => $profile->missingForVerification(),
        ]);
    }

    /**
     * The signed-in member's brand profile, typed.
     *
     * Read back through a query rather than the relation so the type stays
     * concrete, and so the role check and the missing-profile check live in one
     * place instead of being repeated at every call site.
     */
    private function brandProfileFor(Request $request): BrandProfile
    {
        $user = $request->user();
        $this->ensureRole($user, 'brand');

        $profile = BrandProfile::query()->where('user_id', $user->getAuthIdentifier())->first();
        abort_unless($profile !== null, 403);

        return $profile;
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
        abort_unless($profile !== null, 403);
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
        $profile = $this->brandProfileFor($request);
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'website' => ['nullable', 'url'],
            'industry' => ['nullable', 'string', 'max:120'],
            // Format-checked, never treated as proof: a well-formed GSTIN is
            // still only a claim until the certificate is reviewed. Case is
            // ignored here and normalised below, so a lower-case paste is not
            // rejected for something that is not a mistake.
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]{3}$/i'],
            'pan' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/i'],
            'cin' => ['nullable', 'string', 'max:25'],
            'registered_address' => ['nullable', 'string', 'max:1000'],
            'billing_state' => ['nullable', 'string', 'max:64'],
            'billing_country' => ['nullable', 'string', 'max:64'],
            'billing_pincode' => ['nullable', 'string', 'max:12'],
            'authorized_person_name' => ['nullable', 'string', 'max:160'],
            'authorized_person_designation' => ['nullable', 'string', 'max:120'],
            'authorized_person_email' => ['nullable', 'email', 'max:190'],
            'authorized_person_phone' => ['nullable', 'string', 'max:24'],
        ]);

        foreach (['gstin', 'pan'] as $upper) {
            if (filled($data[$upper] ?? null)) {
                $data[$upper] = strtoupper($data[$upper]);
            }
        }

        // A verified brand that edits its details stays verified; re-review is
        // an operator decision, not something a form field triggers.
        $profile->update($data + ['verification_status' => $profile->verification_status === 'verified' ? 'verified' : 'pending_review']);

        $missing = $profile->refresh()->missingForVerification();

        return back()->with('status', $missing === []
            ? __('Brand profile saved. Everything verification needs is on file.')
            : __('Saved. Verification still needs: :list', ['list' => implode(', ', $missing)]));
    }

    /**
     * Upload one supporting document. The file goes to object storage; the
     * database keeps the key.
     */
    public function uploadBrandDocument(Request $request, MediaStorage $media, AuditLogger $audit): RedirectResponse
    {
        $profile = $this->brandProfileFor($request);

        $data = $request->validate([
            'kind' => ['required', 'string', Rule::in(array_keys(BrandDocument::KINDS))],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $file = $request->file('document');
        $key = $media->keyFor('brand-documents/'.$profile->id, $file);

        abort_unless($media->put($key, $file), 500);

        BrandDocument::query()->create([
            'brand_profile_id' => $profile->id,
            'kind' => $data['kind'],
            'original_name' => $file->getClientOriginalName(),
            'disk' => $media->disk(),
            'storage_key' => $key,
            'size_bytes' => $file->getSize() ?: 0,
            'mime' => $file->getMimeType(),
            'review_status' => 'pending',
        ]);

        $audit->record('brand.document_uploaded', $profile, ['kind' => $data['kind']]);

        return back()->with('status', __('Document uploaded. It is pending review — uploading it does not verify the brand.'));
    }

    public function deleteBrandDocument(Request $request, BrandDocument $document, MediaStorage $media, AuditLogger $audit): RedirectResponse
    {
        $profile = $this->brandProfileFor($request);
        abort_unless($document->brand_profile_id === $profile->id, 404);

        // A document an operator has already accepted stays on file; removing
        // the evidence behind a decision would leave the decision unexplained.
        abort_if($document->review_status === 'approved', 403);

        $media->delete($document->disk, $document->storage_key);
        $audit->record('brand.document_removed', $profile, ['kind' => $document->kind]);
        $document->delete();

        return back()->with('status', __('Document removed.'));
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

    public function automations(InstagramProviderInterface $instagram): View
    {
        $profile = request()->user()->creatorProfile;
        abort_unless($profile !== null, 403);
        $items = Automation::query()->where('creator_profile_id', $profile->id)->latest()->get();

        return view('app.automations', ['items' => $items, 'configured' => $instagram->isConfigured()]);
    }

    public function storeAutomation(Request $request): RedirectResponse
    {
        $profile = $request->user()->creatorProfile;
        abort_unless($profile !== null, 403);
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
        abort_unless($profile !== null, 403);
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

    public function notifications(Request $request, Notifier $notifier): View
    {
        return view('app.notifications', [
            'items' => $request->user()->notifications()->latest()->limit(50)->get(),
            'preferences' => $notifier->preferences($request->user()),
            'pushConfigured' => app(PushProviderInterface::class)->isConfigured(),
        ]);
    }

    public function saveNotificationPreferences(Request $request, Notifier $notifier): RedirectResponse
    {
        $notifier->savePreferences($request->user(), (array) $request->input('events', []));

        return back()->with('status', __('Saved.'));
    }

    public function markNotificationsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
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
        abort_unless($owner !== null, 403);
        $items = PortfolioItem::query()->where('owner_type', $owner::class)->where('owner_id', $owner->id)->get();

        return view('app.portfolio', compact('items'));
    }

    public function storePortfolio(Request $request): RedirectResponse
    {
        $user = $request->user();
        $owner = $user->creatorProfile ?? $user->editorProfile;
        abort_unless($owner !== null, 403);
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
        $items = Invoice::query()
            ->where(fn ($q) => $q->where('seller_user_id', $id)->orWhere('buyer_user_id', $id))
            ->with('items')
            ->latest()
            ->get();

        return view('app.invoices', compact('items'));
    }

    /**
     * The PDF of one invoice. Only the two parties to it may download it.
     */
    public function invoicePdf(Request $request, Invoice $invoice, InvoicePdf $pdf): Response
    {
        $id = $request->user()->id;
        abort_unless($invoice->seller_user_id === $id || $invoice->buyer_user_id === $id, 404);

        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->filename($invoice).'"',
        ]);
    }

    public function blogIndex(): View
    {
        $posts = BlogPost::query()->where('status', 'published')->latest('published_at')->paginate(10);

        return view('public.blog', compact('posts'));
    }
}
