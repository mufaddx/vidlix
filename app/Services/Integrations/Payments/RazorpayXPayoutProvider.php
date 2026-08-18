<?php

namespace App\Services\Integrations\Payments;

use App\Contracts\PayoutProviderInterface;
use App\Models\PayoutAccount;
use App\Models\Withdrawal;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RazorpayX payouts to an already-verified beneficiary fund account.
 *
 * Creating a payout is an instruction, not a settlement. The ledger is only
 * debited after a signed payout webhook plus fetchPayout() report "processed".
 */
class RazorpayXPayoutProvider implements PayoutProviderInterface
{
    public function name(): string
    {
        return 'razorpayx';
    }

    public function isConfigured(): bool
    {
        return filled(config('vidlix.payout.key_id'))
            && filled(config('vidlix.payout.key_secret'))
            && filled(config('vidlix.payout.account_number'));
    }

    public function createPayout(Withdrawal $withdrawal, PayoutAccount $account): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'provider_not_configured',
                'provider_payout_id' => null,
                'detail' => 'PAYOUT_KEY_ID / PAYOUT_KEY_SECRET / PAYOUT_ACCOUNT_NUMBER are missing.',
            ];
        }

        if (! filled($account->provider_beneficiary_ref)) {
            return [
                'status' => 'beneficiary_missing',
                'provider_payout_id' => null,
                'detail' => 'No verified provider beneficiary reference is stored for this payout account.',
            ];
        }

        $payload = [
            'account_number' => (string) config('vidlix.payout.account_number'),
            'fund_account_id' => $account->provider_beneficiary_ref,
            'amount' => (int) $withdrawal->amount_minor,
            'currency' => strtoupper((string) $withdrawal->currency),
            'mode' => (string) config('vidlix.payout.mode', 'IMPS'),
            'purpose' => (string) config('vidlix.payout.purpose', 'payout'),
            'queue_if_low_balance' => true,
            'reference_id' => 'wd_'.$withdrawal->getKey(),
            'narration' => 'Vidlix payout',
        ];

        try {
            // Idempotency key keeps a retried approval from paying twice.
            $response = $this->client()
                ->withHeaders(['X-Payout-Idempotency' => 'vidlix-withdrawal-'.$withdrawal->getKey()])
                ->post('/payouts', $payload);
        } catch (Throwable $e) {
            Log::warning('razorpayx.payout.transport_failure', ['message' => $e->getMessage()]);

            return [
                'status' => 'provider_unavailable',
                'provider_payout_id' => null,
                'detail' => 'RazorpayX could not be reached. No payout was instructed.',
            ];
        }

        if (! $response->successful()) {
            return [
                'status' => 'provider_error',
                'provider_payout_id' => null,
                'detail' => 'RazorpayX rejected the payout: '.$this->errorText($response->json()),
            ];
        }

        $body = (array) $response->json();

        return [
            'status' => $this->normalizeStatus((string) ($body['status'] ?? '')),
            'provider_payout_id' => $body['id'] ?? null,
            'detail' => 'Payout instructed. The ledger is debited only after the payout webhook confirms it.',
        ];
    }

    public function fetchPayout(string $providerPayoutId): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'provider_not_configured',
                'amount_minor' => null,
                'provider_payout_id' => null,
                'detail' => 'RazorpayX credentials are missing.',
            ];
        }

        try {
            $response = $this->client()->get('/payouts/'.$providerPayoutId);
        } catch (Throwable $e) {
            Log::warning('razorpayx.payout.fetch_failure', ['message' => $e->getMessage()]);

            return [
                'status' => 'provider_unavailable',
                'amount_minor' => null,
                'provider_payout_id' => $providerPayoutId,
                'detail' => 'RazorpayX could not be reached. The status stays unknown.',
            ];
        }

        if (! $response->successful()) {
            return [
                'status' => 'provider_error',
                'amount_minor' => null,
                'provider_payout_id' => $providerPayoutId,
                'detail' => 'RazorpayX returned '.$response->status().': '.$this->errorText($response->json()),
            ];
        }

        $body = (array) $response->json();

        return [
            'status' => $this->normalizeStatus((string) ($body['status'] ?? '')),
            'amount_minor' => isset($body['amount']) ? (int) $body['amount'] : null,
            'provider_payout_id' => $body['id'] ?? $providerPayoutId,
            'detail' => 'RazorpayX reports status '.($body['status'] ?? 'unknown').'.',
        ];
    }

    private function normalizeStatus(string $raw): string
    {
        return match ($raw) {
            'processed' => 'processed',
            'reversed', 'cancelled', 'rejected', 'failed' => 'failed',
            'queued', 'pending', 'scheduled', 'processing' => 'processing',
            default => 'unknown',
        };
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth(
            (string) config('vidlix.payout.key_id'),
            (string) config('vidlix.payout.key_secret'),
        )
            ->baseUrl(rtrim((string) config('vidlix.payout.api_base'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('vidlix.payout.timeout', 20));
    }

    private function errorText(mixed $json): string
    {
        if (is_array($json) && isset($json['error']['description'])) {
            return (string) $json['error']['description'];
        }

        return 'unspecified provider error';
    }
}
