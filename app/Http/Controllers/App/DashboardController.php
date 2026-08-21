<?php

namespace App\Http\Controllers\App;

use App\Contracts\InstagramProviderInterface;
use App\Contracts\PaymentProviderInterface;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\CreatorProfile;
use App\Models\LedgerEntry;
use App\Models\Negotiation;
use App\Models\Project;
use App\Services\Messaging\InboxQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The first screen after signing in.
 *
 * Everything on it is counted from real rows. A dashboard that invents a figure
 * is the same lie as a balance that invents itself, and this is the screen
 * people trust most because it is the one they read fastest.
 *
 * Where there is genuinely nothing to show, it says so rather than showing a
 * zero — "no transactions" and "₹0.00" mean different things, and only one of
 * them is true of an account that has never traded.
 */
class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        InstagramProviderInterface $instagram,
        PaymentProviderInterface $payments,
        InboxQuery $inbox,
    ): View {
        $user = $request->user();
        $creator = $user->creatorProfile;

        return view('app.dashboard', [
            'user' => $user,
            'profile' => $creator,
            'editorProfile' => $user->editorProfile,

            'instagramConfigured' => $instagram->isConfigured(),
            'paymentsConfigured' => $payments->isConfigured(),

            'unread' => $this->unreadTotal($user, $inbox),
            'openNegotiations' => $this->openNegotiations($user),
            'activeProjects' => $this->activeProjects($user),
            'pendingApplications' => $this->pendingApplications($creator),
            'recommended' => $this->recommended($creator),

            'ledgerCount' => $this->ledgerCount($user),
            'availableMinor' => $user->availableLedgerMinor(),

            'notifications' => $user->unreadNotifications()->take(5)->get(),
        ]);
    }

    private function unreadTotal($user, InboxQuery $inbox): int
    {
        $conversations = $inbox->forUser($user, 'all', null, 50);

        return array_sum($inbox->unreadCounts($user, $conversations->items()));
    }

    /** @return Collection<int, Negotiation> */
    private function openNegotiations($user)
    {
        return Negotiation::query()
            ->whereNotIn('status', Negotiation::CLOSED)
            ->where(fn ($q) => $q
                ->where('initiator_user_id', $user->id)
                ->orWhere('counterparty_user_id', $user->id))
            ->with(['initiator:id,name', 'counterparty:id,name'])
            ->latest()
            ->limit(5)
            ->get();
    }

    /** @return Collection<int, Project> */
    private function activeProjects($user)
    {
        return Project::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where(fn ($q) => $q
                ->where('owner_user_id', $user->id)
                ->orWhere('counterparty_user_id', $user->id))
            ->latest()
            ->limit(5)
            ->get();
    }

    /** @return Collection<int, CampaignApplication> */
    private function pendingApplications(?CreatorProfile $creator)
    {
        if ($creator === null) {
            return collect();
        }

        return CampaignApplication::query()
            ->where('creator_profile_id', $creator->id)
            ->whereIn('status', ['applied', 'viewed', 'shortlisted', 'negotiation'])
            ->with('campaign:id,name,status')
            ->latest()
            ->limit(5)
            ->get();
    }

    /**
     * Campaigns worth a look.
     *
     * Filtered by the follower range a brand actually asked for, and only when
     * the creator's own reach has been synced — recommending against a number
     * nobody has confirmed would be guessing dressed as advice. Campaigns they
     * have already applied to are excluded, because suggesting one twice reads
     * as the platform not having noticed.
     *
     * @return Collection<int, Campaign>
     */
    private function recommended(?CreatorProfile $creator)
    {
        if ($creator === null) {
            return collect();
        }

        $applied = CampaignApplication::query()
            ->where('creator_profile_id', $creator->id)
            ->pluck('campaign_id');

        $followers = $creator->follower_count_synced_at !== null
            ? (int) $creator->follower_count
            : null;

        return Campaign::query()
            ->where('status', 'published')
            ->whereNotIn('id', $applied)
            ->when($followers !== null, fn ($q) => $q
                ->where(fn ($inner) => $inner->whereNull('min_followers')->orWhere('min_followers', '<=', $followers))
                ->where(fn ($inner) => $inner->whereNull('max_followers')->orWhere('max_followers', '>=', $followers)))
            ->latest('published_at')
            ->limit(5)
            ->get();
    }

    private function ledgerCount($user): int
    {
        $accountIds = $user->ledgerAccounts()->pluck('id');

        return $accountIds->isEmpty()
            ? 0
            : LedgerEntry::query()->whereIn('ledger_account_id', $accountIds)->count();
    }
}
