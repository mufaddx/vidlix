<?php

namespace App\Services\Integrations;

use App\Contracts\PayoutProviderInterface;
use App\Models\PayoutAccount;
use App\Models\Withdrawal;

class UnconfiguredPayoutProvider implements PayoutProviderInterface
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'unconfigured';
    }

    public function createPayout(Withdrawal $withdrawal, PayoutAccount $account): array
    {
        return [
            'status' => 'provider_not_configured',
            'provider_payout_id' => null,
            'detail' => 'Payouts cannot be instructed until PAYOUT_PROVIDER credentials are configured. The request stays queued.',
        ];
    }

    public function fetchPayout(string $providerPayoutId): array
    {
        return [
            'status' => 'provider_not_configured',
            'amount_minor' => null,
            'provider_payout_id' => null,
            'detail' => 'No payout provider is configured, so no authoritative status exists.',
        ];
    }
}
