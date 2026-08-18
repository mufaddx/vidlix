<?php

namespace App\Services\Marketplace;

use App\Contracts\InstagramProviderInterface;
use App\Contracts\PaymentProviderInterface;
use App\Contracts\PayoutProviderInterface;
use App\Models\Agreement;
use App\Models\AgreementAcceptance;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\CommissionRule;
use App\Models\Conversation;
use App\Models\CreatorManagerRelationship;
use App\Models\Dispute;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ManagementSubscription;
use App\Models\ManagerActivityLog;
use App\Models\ManagerInvitation;
use App\Models\Message;
use App\Models\Payment;
use App\Models\PayoutAccount;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectRevision;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\Review;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\GenericNotice;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\LedgerService;
use App\Services\Media\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketplaceEngine
{
    public function __construct(
        private AuditLogger $audit,
        private PaymentProviderInterface $payments,
        private PayoutProviderInterface $payouts,
        private InstagramProviderInterface $instagram,
        private LedgerService $ledger,
        private MediaStorage $media,
    ) {}

    public function applyToCampaign(User $user, Campaign $campaign, array $data): CampaignApplication
    {
        if (! $campaign->isPublished()) {
            throw ValidationException::withMessages(['campaign' => __('This campaign is not open.')]);
        }
        $profile = $user->creatorProfile;
        if (! $profile) {
            throw ValidationException::withMessages(['campaign' => __('Creator profile required.')]);
        }

        $application = CampaignApplication::query()->create([
            'campaign_id' => $campaign->id,
            'creator_profile_id' => $profile->id,
            'status' => 'applied',
            'proposed_fee_minor' => (int) ($data['proposed_fee_minor'] ?? 0),
            'message' => $data['message'] ?? null,
            'availability' => $data['availability'] ?? null,
            'analytics_snapshot' => [
                'source' => 'not_synced',
                'note' => 'Instagram insights are omitted until official Meta sync succeeds.',
            ],
        ]);
        $this->audit->record('campaign.applied', $application);
        $campaign->brand->user->notify(new GenericNotice('campaign_application', [
            'campaign_id' => $campaign->id,
            'application_id' => $application->id,
        ]));

        return $application;
    }

    public function transitionApplication(CampaignApplication $application, string $status): void
    {
        $allowed = [
            'applied' => ['viewed', 'shortlisted', 'rejected'],
            'viewed' => ['shortlisted', 'rejected', 'negotiation'],
            'shortlisted' => ['negotiation', 'rejected'],
            'negotiation' => ['accepted', 'rejected'],
            'accepted' => ['contract'],
            'contract' => ['active'],
            'active' => ['completed'],
        ];
        $from = $application->status;
        if (! in_array($status, $allowed[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('Illegal application transition.')]);
        }
        $application->update(['status' => $status]);
        $this->audit->record('application.transitioned', $application, ['from' => $from, 'to' => $status]);
    }

    public function createProposal(User $from, User $to, $proposible, array $payload): Proposal
    {
        return DB::transaction(function () use ($from, $to, $proposible, $payload) {
            $proposal = Proposal::query()->create([
                'proposal_uuid' => (string) Str::uuid(),
                'proposible_type' => $proposible::class,
                'proposible_id' => $proposible->getKey(),
                'from_user_id' => $from->id,
                'to_user_id' => $to->id,
                'status' => 'sent',
            ]);
            ProposalVersion::query()->create([
                'proposal_id' => $proposal->id,
                'version_number' => 1,
                'amount_minor' => (int) $payload['amount_minor'],
                'currency' => $payload['currency'] ?? 'INR',
                'deliverables' => $payload['deliverables'] ?? [],
                'deadline' => $payload['deadline'] ?? null,
                'revisions' => $payload['revisions'] ?? 2,
                'usage_rights' => $payload['usage_rights'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $from->id,
            ]);
            $this->audit->record('proposal.created', $proposal);

            return $proposal;
        });
    }

    public function counterProposal(Proposal $proposal, User $actor, array $payload): ProposalVersion
    {
        $latest = $proposal->latestVersion();
        $version = ProposalVersion::query()->create([
            'proposal_id' => $proposal->id,
            'version_number' => ($latest?->version_number ?? 0) + 1,
            'amount_minor' => (int) $payload['amount_minor'],
            'currency' => $latest->currency ?? 'INR',
            'deliverables' => $payload['deliverables'] ?? $latest?->deliverables,
            'deadline' => $payload['deadline'] ?? $latest?->deadline,
            'revisions' => $payload['revisions'] ?? $latest?->revisions,
            'usage_rights' => $payload['usage_rights'] ?? $latest?->usage_rights,
            'notes' => $payload['notes'] ?? null,
            'created_by' => $actor->id,
        ]);
        $proposal->update(['status' => 'countered']);
        $this->audit->record('proposal.countered', $proposal, ['version' => $version->version_number]);

        return $version;
    }

    public function createProject(User $owner, User $counterparty, array $data): Project
    {
        $project = Project::query()->create([
            'name' => $data['name'],
            'status' => 'draft',
            'total_amount_minor' => (int) $data['total_amount_minor'],
            'advance_amount_minor' => (int) ($data['advance_amount_minor'] ?? 0),
            'deadline' => $data['deadline'] ?? null,
            'revision_limit' => (int) ($data['revision_limit'] ?? 2),
            'owner_user_id' => $owner->id,
            'counterparty_user_id' => $counterparty->id,
            'campaign_id' => $data['campaign_id'] ?? null,
        ]);
        $this->audit->record('project.created', $project);

        return $project;
    }

    public function transitionProject(Project $project, string $to): void
    {
        $map = [
            'draft' => ['proposal_sent'],
            'proposal_sent' => ['awaiting_advance'],
            'awaiting_advance' => ['advance_paid'],
            'advance_paid' => ['active'],
            'active' => ['draft_submitted', 'source_files_received'],
            'source_files_received' => ['draft_submitted'],
            'draft_submitted' => ['revision_requested', 'final_submitted'],
            'revision_requested' => ['revision_submitted'],
            'revision_submitted' => ['final_submitted', 'revision_requested'],
            'final_submitted' => ['remaining_payment'],
            'remaining_payment' => ['client_approved'],
            'client_approved' => ['settlement_pending'],
            'settlement_pending' => ['completed'],
        ];
        if (! in_array($to, $map[$project->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('Illegal project transition from '.$project->status)]);
        }
        $project->update(['status' => $to]);
        $this->audit->record('project.transitioned', $project, ['to' => $to]);

        if ($to === 'completed') {
            $this->releaseProjectEarnings($project->fresh());
        }
    }

    public function requestPayment(User $payer, Project $project, int $amountMinor, string $kind): Payment
    {
        $payment = Payment::query()->create([
            'payment_uuid' => (string) Str::uuid(),
            'status' => 'pending',
            'amount_minor' => $amountMinor,
            'currency' => config('vidlix.currency', 'INR'),
            'provider' => $this->payments->name(),
            'payer_user_id' => $payer->id,
            'payable_type' => Project::class,
            'payable_id' => $project->id,
        ]);

        $checkout = $this->payments->createCheckout($amountMinor, $payment->currency, [
            'payment_uuid' => $payment->payment_uuid,
            'kind' => $kind,
            'description' => $project->name,
            'customer_name' => $payer->name,
            'customer_email' => $payer->email,
        ]);

        // A checkout URL is an invitation to pay, never a payment. The status
        // stays pending until a signed webhook plus a provider API read agree.
        $payment->update([
            'status' => $checkout['status'] === 'checkout_created' ? 'pending' : 'awaiting_provider',
            'provider_payment_id' => $checkout['provider_payment_id'] ?: $payment->provider_payment_id,
            'checkout_url' => $checkout['checkout_url'],
            'last_provider_detail' => $checkout['detail'],
        ]);
        $this->audit->record('payment.requested', $payment, $checkout);

        return $payment->fresh();
    }

    /**
     * Release escrowed earnings once a project is finished. Both legs are
     * appended; no existing ledger row is ever rewritten.
     */
    public function releaseProjectEarnings(Project $project): void
    {
        $payments = Payment::query()
            ->where('payable_type', Project::class)
            ->where('payable_id', $project->getKey())
            ->where('status', 'captured')
            ->get();

        foreach ($payments as $payment) {
            $this->ledger->release(
                userId: (int) $project->counterparty_user_id,
                kind: 'earnings',
                amountMinor: (int) $payment->amount_minor,
                currency: (string) $payment->currency,
                referenceType: Payment::class,
                referenceId: (int) $payment->getKey(),
                providerReference: $payment->provider_payment_id,
                idempotencyKey: 'release:payment:'.$payment->getKey(),
            );
            $payment->update(['status' => 'settled', 'confirmed_at' => $payment->confirmed_at ?? now()]);
        }

        $this->audit->record('project.earnings_released', $project, ['payments' => $payments->count()]);
    }

    public function issueInvoice(User $seller, User $buyer, $invoiceable, int $amountMinor, string $description): Invoice
    {
        $rule = CommissionRule::query()->where('is_active', true)->where('slug', 'platform')->first();
        $fee = (int) round($amountMinor * (($rule?->bps ?? 0) / 10000));
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
            'seller_user_id' => $seller->id,
            'buyer_user_id' => $buyer->id,
            'invoiceable_type' => $invoiceable::class,
            'invoiceable_id' => $invoiceable->getKey(),
            'subtotal_minor' => $amountMinor,
            'tax_minor' => 0,
            'fee_minor' => $fee,
            'total_minor' => $amountMinor + $fee,
            'currency' => config('vidlix.currency', 'INR'),
            'status' => 'issued',
            'due_date' => now()->addDays(14),
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => $description,
            'amount_minor' => $amountMinor,
        ]);
        $this->audit->record('invoice.issued', $invoice);

        return $invoice;
    }

    public function createAgreement($agreeable, array $terms): Agreement
    {
        $agreement = Agreement::query()->create([
            'agreement_uuid' => (string) Str::uuid(),
            'agreeable_type' => $agreeable::class,
            'agreeable_id' => $agreeable->getKey(),
            'version_number' => 1,
            'terms' => $terms,
            'status' => 'pending_acceptance',
        ]);
        $this->audit->record('agreement.created', $agreement);

        return $agreement;
    }

    public function acceptAgreement(Agreement $agreement, User $user, string $typedName, ?string $ip, ?string $ua): void
    {
        AgreementAcceptance::query()->create([
            'agreement_id' => $agreement->id,
            'user_id' => $user->id,
            'typed_name' => $typedName,
            'ip_address' => $ip,
            'user_agent' => $ua,
            'accepted_at' => now(),
        ]);
        $agreement->update(['status' => 'accepted']);
        $this->audit->record('agreement.accepted', $agreement, ['typed_name_not_legal_esign' => true]);
    }

    public function storeProjectFile(Project $project, User $user, UploadedFile $file, string $kind, bool $watermarked = false): ProjectFile
    {
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime', 'application/zip'];
        if (! in_array($file->getMimeType(), $allowed, true)) {
            throw ValidationException::withMessages(['file' => __('File type not allowed.')]);
        }
        if ($file->getSize() > (int) config('vidlix.media.max_bytes')) {
            throw ValidationException::withMessages(['file' => __('File too large.')]);
        }

        // Bytes go to object storage; the database keeps only the key.
        $key = $this->media->keyFor('projects/'.$project->id, $file);
        if (! $this->media->put($key, $file)) {
            throw ValidationException::withMessages(['file' => __('Upload to object storage failed.')]);
        }

        $record = ProjectFile::query()->create([
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'kind' => $kind,
            'original_name' => $file->getClientOriginalName(),
            'storage_key' => $key,
            'disk' => $this->media->disk(),
            'mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'watermarked' => $watermarked,
        ]);
        $this->audit->record('project.file_uploaded', $record, ['disk' => $record->disk]);

        return $record;
    }

    public function requestRevision(Project $project, User $user, string $feedback): ProjectRevision
    {
        if ($project->revisions_used >= $project->revision_limit) {
            throw ValidationException::withMessages(['revision' => __('Revision limit reached.')]);
        }
        $rev = ProjectRevision::query()->create([
            'project_id' => $project->id,
            'version_number' => $project->revisions()->count() + 1,
            'feedback' => $feedback,
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);
        $project->increment('revisions_used');
        $this->transitionProject($project->fresh(), 'revision_requested');

        return $rev;
    }

    public function openDispute(User $user, $disputable, string $reason, string $statement): Dispute
    {
        $dispute = Dispute::query()->create([
            'dispute_uuid' => (string) Str::uuid(),
            'disputable_type' => $disputable::class,
            'disputable_id' => $disputable->getKey(),
            'opened_by' => $user->id,
            'reason' => $reason,
            'statement' => $statement,
            'status' => 'open',
        ]);
        $this->audit->record('dispute.opened', $dispute);

        return $dispute;
    }

    public function requestWithdrawal(User $user, int $amountMinor): Withdrawal
    {
        $available = $user->availableLedgerMinor();
        if ($amountMinor > $available) {
            throw ValidationException::withMessages(['amount' => __('Insufficient available balance (ledger).')]);
        }

        $account = PayoutAccount::query()
            ->where('user_id', $user->id)
            ->where('status', 'verified')
            ->first();

        $withdrawal = Withdrawal::query()->create([
            'user_id' => $user->id,
            'payout_account_id' => $account?->id,
            'amount_minor' => $amountMinor,
            'currency' => config('vidlix.currency', 'INR'),
            'status' => 'requested',
            'last_provider_detail' => $this->payouts->isConfigured()
                ? 'Awaiting admin approval before the payout is instructed.'
                : 'No payout provider is configured, so nothing can be transferred yet.',
        ]);
        $this->audit->record('withdrawal.requested', $withdrawal, ['provider' => $this->payouts->name()]);

        return $withdrawal;
    }

    /**
     * Admin approval instructs the provider. The ledger is untouched here - the
     * debit only happens when the payout webhook confirms the transfer.
     *
     * @return array{status: string, detail: string}
     */
    public function approveWithdrawal(Withdrawal $withdrawal): array
    {
        if ($withdrawal->status !== 'requested') {
            return ['status' => $withdrawal->status, 'detail' => 'Only a requested withdrawal can be approved.'];
        }

        $available = $this->ledger->balance((int) $withdrawal->user_id, LedgerService::STATE_AVAILABLE);
        if ((int) $withdrawal->amount_minor > $available) {
            $withdrawal->update([
                'status' => 'rejected',
                'last_provider_detail' => 'The available ledger balance no longer covers this amount.',
            ]);

            return ['status' => 'rejected', 'detail' => 'The available ledger balance no longer covers this amount.'];
        }

        $account = $withdrawal->payoutAccount;
        if (! $account) {
            $withdrawal->update(['last_provider_detail' => 'No verified payout account is on file.']);

            return ['status' => 'blocked', 'detail' => 'No verified payout account is on file.'];
        }

        $result = $this->payouts->createPayout($withdrawal, $account);
        $withdrawal->update([
            'status' => $result['status'] === 'processing' ? 'processing' : $withdrawal->status,
            'provider_payout_id' => $result['provider_payout_id'] ?: $withdrawal->provider_payout_id,
            'last_provider_detail' => $result['detail'],
        ]);
        $this->audit->record('withdrawal.approved', $withdrawal, $result);

        return ['status' => $result['status'], 'detail' => $result['detail']];
    }

    public function inviteManager(User $creator, array $data): ManagerInvitation
    {
        $invite = ManagerInvitation::query()->create([
            'creator_user_id' => $creator->id,
            'email' => $data['email'],
            'name' => $data['name'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'token' => Str::lower(Str::ulid()),
            'permissions' => $data['permissions'] ?? [],
            'status' => 'invited',
        ]);
        $this->audit->record('manager.invited', $invite);

        return $invite;
    }

    public function acceptManagerInvite(User $manager, string $token): CreatorManagerRelationship
    {
        $invite = ManagerInvitation::query()->where('token', $token)->where('status', 'invited')->firstOrFail();
        if (strcasecmp($invite->email, $manager->email) !== 0) {
            abort(403);
        }
        $rel = CreatorManagerRelationship::query()->updateOrCreate(
            [
                'creator_user_id' => $invite->creator_user_id,
                'manager_user_id' => $manager->id,
            ],
            [
                'status' => 'active',
                'permissions' => $invite->permissions,
                'accepted_at' => now(),
                'revoked_at' => null,
            ],
        );
        $invite->update(['status' => 'accepted', 'accepted_at' => now()]);
        $this->audit->record('manager.accepted', $rel);

        return $rel;
    }

    public function logManager(int $creatorId, int $managerId, string $action, array $meta = []): void
    {
        ManagerActivityLog::query()->create([
            'creator_user_id' => $creatorId,
            'manager_user_id' => $managerId,
            'action' => $action,
            'meta' => $meta,
        ]);
    }

    public function subscribe(User $creator, int $planId): ManagementSubscription
    {
        return ManagementSubscription::query()->create([
            'creator_user_id' => $creator->id,
            'management_plan_id' => $planId,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    public function startInternalChat(User $a, User $b, string $subject): Conversation
    {
        $conversation = Conversation::query()->create([
            'conversation_uuid' => (string) Str::uuid(),
            'channel' => 'internal',
            'subject' => $subject,
            'status' => 'open',
            'routing_token' => Str::lower(Str::ulid()),
            'last_message_at' => now(),
        ]);
        $conversation->participants()->create(['user_id' => $a->id, 'role' => 'member']);
        $conversation->participants()->create(['user_id' => $b->id, 'role' => 'member']);

        return $conversation;
    }

    public function postInternalMessage(Conversation $conversation, User $actor, string $body): Message
    {
        abort_unless($conversation->participants()->where('user_id', $actor->id)->exists(), 403);
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'actor_user_id' => $actor->id,
            'acting_for_creator_id' => session('acting_for_creator_id'),
            'direction' => 'internal',
            'body' => $body,
            'delivery_status' => 'stored',
        ]);
        $conversation->update(['last_message_at' => now()]);

        return $message;
    }

    public function saveAutomation(int $creatorProfileId, array $data): Automation
    {
        $automation = Automation::query()->create([
            'creator_profile_id' => $creatorProfileId,
            'name' => $data['name'],
            'status' => $this->instagram->isConfigured() ? 'disabled' : 'unsupported',
            'config' => $data,
        ]);
        if (! $this->instagram->isConfigured()) {
            AutomationRun::query()->create([
                'automation_id' => $automation->id,
                'status' => 'provider_not_configured',
                'detail' => 'Comment-to-DM stays disabled until Meta messaging permissions exist.',
            ]);
        }

        return $automation;
    }

    public function review(User $reviewer, User $reviewee, $reviewable, int $rating, ?string $body): Review
    {
        return Review::query()->create([
            'reviewer_user_id' => $reviewer->id,
            'reviewee_user_id' => $reviewee->id,
            'reviewable_type' => $reviewable::class,
            'reviewable_id' => $reviewable->getKey(),
            'rating' => min(5, max(1, $rating)),
            'body' => $body,
        ]);
    }
}
