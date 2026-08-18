<?php

namespace App\Http\Controllers\App;

use App\Contracts\InstagramProviderInterface;
use App\Contracts\PaymentProviderInterface;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        InstagramProviderInterface $instagram,
        PaymentProviderInterface $payments,
    ): View {
        $user = $request->user();
        $profile = $user->creatorProfile;
        $inquiries = 0;
        $ledgerCount = 0;

        if ($profile) {
            $inquiries = Conversation::query()->where('creator_profile_id', $profile->id)->count();
        }

        $accountIds = $user->ledgerAccounts()->pluck('id');
        if ($accountIds->isNotEmpty()) {
            $ledgerCount = LedgerEntry::query()->whereIn('ledger_account_id', $accountIds)->count();
        }

        return view('app.dashboard', [
            'user' => $user,
            'profile' => $profile,
            'instagramConfigured' => $instagram->isConfigured(),
            'paymentsConfigured' => $payments->isConfigured(),
            'inquiryCount' => $inquiries,
            'ledgerCount' => $ledgerCount,
            'availableMinor' => $user->availableLedgerMinor(),
        ]);
    }
}
