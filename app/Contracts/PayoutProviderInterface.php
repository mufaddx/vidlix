<?php

namespace App\Contracts;

use App\Models\PayoutAccount;
use App\Models\Withdrawal;

interface PayoutProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Instruct the provider to pay a verified beneficiary. Money is only
     * debited from the ledger once a signed payout webhook plus fetchPayout()
     * confirm the transfer.
     *
     * @return array{status: string, provider_payout_id: ?string, detail: string}
     */
    public function createPayout(Withdrawal $withdrawal, PayoutAccount $account): array;

    /**
     * @return array{status: string, amount_minor: ?int, provider_payout_id: ?string, detail: string}
     */
    public function fetchPayout(string $providerPayoutId): array;
}
