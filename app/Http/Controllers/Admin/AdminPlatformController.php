<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutodmAutomation;
use App\Models\AutodmRun;
use App\Models\FeatureFlag;
use App\Models\InstagramAccount;
use App\Models\WebhookLog;
use App\Services\Audit\AuditLogger;
use App\Services\Platform\Features;
use App\Services\Platform\HealthCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Switches an operator can throw without a deploy, and a page that says what
 * is actually working.
 */
class AdminPlatformController extends Controller
{
    public function index(Features $features): View
    {
        return view('admin.platform', [
            'flags' => $features->flags(),
            'audiences' => FeatureFlag::AUDIENCES,
            'maintenance' => $features->isUnderMaintenance(),
            'maintenanceMessage' => $features->maintenanceMessage(),
        ]);
    }

    public function saveFlag(Request $request, Features $features, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64'],
            'enabled' => ['nullable', 'boolean'],
            'audience' => ['required', 'string', 'max:16'],
        ]);

        $enabled = (bool) ($data['enabled'] ?? false);
        $features->setFlag($data['key'], $enabled, $data['audience'], $request->user()->id);
        $audit->record('platform.flag_changed', null, $data + ['enabled' => $enabled]);

        return back()->with('status', __('Switch saved. It takes effect on the next page load.'));
    }

    public function saveMaintenance(Request $request, Features $features, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'message' => ['nullable', 'string', 'max:300'],
        ]);

        $enabled = (bool) ($data['enabled'] ?? false);

        $features->putSetting(Features::MAINTENANCE_KEY, $enabled ? '1' : '0', $request->user()->id);
        $features->putSetting(Features::MAINTENANCE_MESSAGE_KEY, $data['message'] ?? null, $request->user()->id);
        $audit->record('platform.maintenance_changed', null, ['enabled' => $enabled]);

        return back()->with('status', $enabled
            ? __('The site is now closed to members. Staff, sign-in and webhooks stay open.')
            : __('The site is open again.'));
    }

    public function health(HealthCheck $health): View
    {
        return view('admin.health', ['checks' => $health->all()]);
    }

    /**
     * Instagram connections and what their automations are doing.
     *
     * Grouped by account rather than by run, because the question an operator
     * actually has is "whose integration is broken" — a flat list of failed runs
     * answers "what failed" and leaves them to work out whose.
     */
    public function autodm(): View
    {
        $accounts = InstagramAccount::query()
            ->with('creatorProfile:id,user_id,username,display_name')
            ->latest('authorized_at')
            ->limit(100)
            ->get();

        $since = now()->subDays(7);

        return view('admin.autodm', [
            'accounts' => $accounts->map(fn (InstagramAccount $account) => [
                'account' => $account,
                'automations' => AutodmAutomation::query()
                    ->where('instagram_account_id', $account->id)
                    ->where('status', AutodmAutomation::ACTIVE)
                    ->count(),
                // Sent, skipped and failed are three different things. One
                // total would hide whichever of them needs attention.
                'sent' => $this->runCount($account->id, [AutodmRun::SENT], $since),
                'skipped' => $this->runCount($account->id, [AutodmRun::SKIPPED], $since),
                'failed' => $this->runCount(
                    $account->id,
                    [AutodmRun::FAILED, AutodmRun::PERMANENTLY_FAILED],
                    $since,
                ),
            ]),
            'recentFailures' => AutodmRun::query()
                ->whereIn('status', [AutodmRun::FAILED, AutodmRun::PERMANENTLY_FAILED, AutodmRun::SKIPPED])
                ->with('automation:id,name,user_id')
                ->latest()
                ->limit(50)
                ->get(),
        ]);
    }

    /**
     * Webhook deliveries, most recent first.
     *
     * Rejections are the point of the page: a run of them usually means a
     * rotated secret, and nothing else in the interface would show that.
     */
    public function webhooks(): View
    {
        return view('admin.webhooks', [
            'logs' => WebhookLog::query()->latest('id')->paginate(50),
            'rejectedLastDay' => WebhookLog::query()
                ->where('processing_status', 'rejected')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ]);
    }

    /** @param list<string> $statuses */
    private function runCount(int $accountId, array $statuses, \DateTimeInterface $since): int
    {
        return AutodmRun::query()
            ->whereIn('autodm_automation_id', AutodmAutomation::query()
                ->where('instagram_account_id', $accountId)
                ->select('id'))
            ->whereIn('status', $statuses)
            ->where('created_at', '>=', $since)
            ->count();
    }
}
