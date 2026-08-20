<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrandProfile;
use App\Models\Campaign;
use App\Models\ConversationReport;
use App\Models\Dispute;
use App\Models\EditorProfile;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Audit\AuditLogger;
use App\Services\Marketplace\MarketplaceEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminOpsController extends Controller
{
    public function users(): View
    {
        return view('admin.table', [
            'title' => __('Users'),
            'rows' => User::query()->latest()->limit(100)->get()->map(fn (User $u) => [
                $u->id, $u->name, $u->email, $u->status, implode(',', $u->roleSlugs()),
            ]),
            'headers' => ['ID', 'Name', 'Email', 'Status', 'Roles'],
        ]);
    }

    public function verification(): View
    {
        return view('admin.verification', [
            'editors' => EditorProfile::query()->where('application_status', 'pending_review')->get(),
            'brands' => BrandProfile::query()->where('verification_status', 'pending_review')->get(),
            'campaigns' => Campaign::query()->where('status', 'pending_review')->get(),
        ]);
    }

    public function decideEditor(Request $request, EditorProfile $editor): RedirectResponse
    {
        $editor->update(['application_status' => $request->validate(['decision' => ['required', 'in:approved,rejected']])['decision']]);

        return back()->with('status', __('Editor updated.'));
    }

    public function decideBrand(Request $request, BrandProfile $brand): RedirectResponse
    {
        $brand->update(['verification_status' => $request->validate(['decision' => ['required', 'in:verified,rejected']])['decision']]);

        return back()->with('status', __('Brand updated.'));
    }

    public function decideCampaign(Request $request, Campaign $campaign): RedirectResponse
    {
        $to = $request->validate(['decision' => ['required', 'in:published,cancelled']])['decision'];
        $campaign->update(['status' => $to]);

        return back()->with('status', __('Campaign updated.'));
    }

    public function finance(): View
    {
        return view('admin.finance', [
            'withdrawals' => Withdrawal::query()->latest()->limit(50)->get(),
        ]);
    }

    /**
     * Approval instructs the payout provider. "paid" is deliberately not an
     * option here - only a signed payout webhook confirmed against the provider
     * API may move a withdrawal to paid and debit the ledger.
     */
    public function withdrawal(Request $request, Withdrawal $withdrawal, MarketplaceEngine $engine): RedirectResponse
    {
        $decision = $request->validate(['decision' => ['required', 'in:approve,reject']])['decision'];

        if ($decision === 'reject') {
            $withdrawal->update([
                'status' => 'rejected',
                'last_provider_detail' => 'Rejected by an administrator.',
            ]);

            return back()->with('status', __('Withdrawal rejected.'));
        }

        $result = $engine->approveWithdrawal($withdrawal);

        return back()->with('status', $result['detail']);
    }

    public function disputes(): View
    {
        return view('admin.disputes', ['items' => Dispute::query()->latest()->get()]);
    }

    /**
     * Conversations members have reported.
     *
     * Open ones first and oldest first within that, because the complaint that
     * has been waiting longest is the one most likely to have been forgotten.
     */
    public function reports(): View
    {
        return view('admin.reports', [
            'items' => ConversationReport::query()
                ->with(['reporter:id,name,email', 'conversation'])
                ->orderByRaw("case status when 'open' then 0 when 'reviewing' then 1 else 2 end")
                ->orderBy('created_at')
                ->paginate(50),
        ]);
    }

    public function resolveReport(Request $request, ConversationReport $report, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:reviewing,actioned,dismissed'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $report->update([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Moderation decisions are the kind somebody asks about months later,
        // so who decided what, and when, is recorded rather than inferred.
        $audit->record('conversation_report.reviewed', $report, [
            'status' => $data['status'],
        ], $request->user()->id);

        return back()->with('status', __('Report updated.'));
    }

    public function resolveDispute(Request $request, Dispute $dispute): RedirectResponse
    {
        $dispute->update([
            'status' => 'resolved',
            'resolution' => $request->validate(['resolution' => ['required', 'string']])['resolution'],
        ]);

        return back();
    }

    public function tickets(): View
    {
        return view('admin.tickets', ['items' => SupportTicket::query()->latest()->get()]);
    }
}
