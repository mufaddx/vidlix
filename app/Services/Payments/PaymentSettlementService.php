<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProviderInterface;
use App\Contracts\PayoutProviderInterface;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Withdrawal;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\LedgerService;
use App\Services\Marketplace\MarketplaceEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns a *verified* provider webhook into ledger movement.
 *
 * Two independent facts are required before money moves:
 *   1. the webhook signature verified against our configured secret, and
 *   2. the provider API itself reports the payment as paid for the same amount.
 *
 * A browser redirect, a webhook body on its own, or a provider we hold no
 * credentials for can never settle anything.
 */
class PaymentSettlementService
{
    public function __construct(
        private PaymentProviderInterface $payments,
        private PayoutProviderInterface $payouts,
        private LedgerService $ledger,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{status: string, detail: string}
     */
    public function settlePaymentEvent(array $payload, ?string $eventId, ?string $eventType): array
    {
        $reference = $this->paymentReference($payload);
        $payment = $this->findPayment($reference['provider_payment_id'], $reference['payment_uuid']);

        if (! $payment) {
            Log::info('payment.webhook.unmatched', ['event' => $eventType, 'reference' => $reference]);

            return ['status' => 'unmatched', 'detail' => 'No local payment matches this event. Nothing was settled.'];
        }

        if (in_array($payment->status, ['captured', 'settled'], true)) {
            return ['status' => 'already_settled', 'detail' => 'Payment was already settled.'];
        }

        if (! $this->payments->isConfigured()) {
            $payment->update(['status' => 'awaiting_provider']);
            $this->audit->record('payment.webhook_without_provider', $payment, [
                'event_type' => $eventType,
                'detail' => 'Signature verified but no payment provider credentials exist to confirm it.',
            ]);

            return [
                'status' => 'provider_not_configured',
                'detail' => 'PAYMENT_PROVIDER credentials are missing, so the amount cannot be confirmed. Nothing was settled.',
            ];
        }

        $lookupId = $payment->provider_payment_id ?: $reference['provider_payment_id'];
        if (! filled($lookupId)) {
            return ['status' => 'unverifiable', 'detail' => 'No provider payment id to re-check against the API.'];
        }

        $authoritative = $this->payments->fetchPayment((string) $lookupId);

        if ($authoritative['status'] === 'failed') {
            $payment->update(['status' => 'failed']);
            $this->audit->record('payment.failed', $payment, $authoritative);

            return ['status' => 'failed', 'detail' => $authoritative['detail']];
        }

        if ($authoritative['status'] !== 'paid') {
            $this->audit->record('payment.not_confirmed', $payment, $authoritative + ['event_type' => $eventType]);

            return [
                'status' => 'not_confirmed',
                'detail' => 'The provider does not report this payment as paid. Nothing was settled. '.$authoritative['detail'],
            ];
        }

        $paidMinor = (int) ($authoritative['amount_minor'] ?? 0);
        if ($paidMinor < (int) $payment->amount_minor) {
            $this->audit->record('payment.amount_mismatch', $payment, [
                'expected_minor' => (int) $payment->amount_minor,
                'provider_minor' => $paidMinor,
            ]);

            return [
                'status' => 'amount_mismatch',
                'detail' => 'The provider reports a smaller amount than the invoice. Settlement was refused.',
            ];
        }

        $this->credit($payment, (string) $lookupId, $eventId);

        return ['status' => 'settled', 'detail' => 'Payment confirmed by the provider API and credited to the ledger as reserved.'];
    }

    private function credit(Payment $payment, string $providerPaymentId, ?string $eventId): void
    {
        DB::transaction(function () use ($payment, $providerPaymentId, $eventId) {
            $payment->load('payable');
            $payment->forceFill([
                'status' => 'captured',
                'provider_payment_id' => $payment->provider_payment_id ?: $providerPaymentId,
            ])->save();

            $sellerId = $payment->payer_user_id;
            if ($payment->payable instanceof Project) {
                $sellerId = $payment->payable->counterparty_user_id;
            }

            $this->ledger->append(
                userId: (int) $sellerId,
                kind: 'earnings',
                state: LedgerService::STATE_RESERVED,
                amountMinor: (int) $payment->amount_minor,
                currency: (string) $payment->currency,
                referenceType: Payment::class,
                referenceId: (int) $payment->getKey(),
                providerReference: $providerPaymentId,
                meta: ['reason' => 'payment_captured', 'provider_event_id' => $eventId],
                idempotencyKey: 'payment:'.$payment->getKey().':'.($eventId ?? $providerPaymentId),
            );

            if ($payment->payable instanceof Project) {
                $this->advanceProject($payment->payable);
            }

            $this->audit->record('payment.captured', $payment, ['provider_event_id' => $eventId]);
        });
    }

    private function advanceProject(Project $project): void
    {
        $engine = app(MarketplaceEngine::class);
        if ($project->status === 'awaiting_advance') {
            $engine->transitionProject($project, 'advance_paid');
            $engine->transitionProject($project->fresh(), 'active');
        } elseif ($project->status === 'remaining_payment') {
            $engine->transitionProject($project, 'client_approved');
        }
    }

    /**
     * @return array{status: string, detail: string}
     */
    public function settlePayoutEvent(array $payload, ?string $eventId, ?string $eventType): array
    {
        $reference = $this->payoutReference($payload);
        $withdrawal = $this->findWithdrawal($reference['provider_payout_id'], $reference['withdrawal_id']);

        if (! $withdrawal) {
            Log::info('payout.webhook.unmatched', ['event' => $eventType, 'reference' => $reference]);

            return ['status' => 'unmatched', 'detail' => 'No local withdrawal matches this payout event.'];
        }

        if ($withdrawal->status === 'paid') {
            return ['status' => 'already_settled', 'detail' => 'Withdrawal was already settled.'];
        }

        if (! $this->payouts->isConfigured()) {
            return [
                'status' => 'provider_not_configured',
                'detail' => 'PAYOUT_PROVIDER credentials are missing, so the transfer cannot be confirmed.',
            ];
        }

        $lookupId = $withdrawal->provider_payout_id ?: $reference['provider_payout_id'];
        if (! filled($lookupId)) {
            return ['status' => 'unverifiable', 'detail' => 'No provider payout id to re-check against the API.'];
        }

        $authoritative = $this->payouts->fetchPayout((string) $lookupId);

        if ($authoritative['status'] === 'failed') {
            $withdrawal->update(['status' => 'failed']);
            $this->audit->record('withdrawal.failed', $withdrawal, $authoritative);

            return ['status' => 'failed', 'detail' => $authoritative['detail']];
        }

        if ($authoritative['status'] !== 'processed') {
            $this->audit->record('withdrawal.not_confirmed', $withdrawal, $authoritative);

            return ['status' => 'not_confirmed', 'detail' => 'The provider has not processed this payout yet.'];
        }

        DB::transaction(function () use ($withdrawal, $lookupId, $eventId) {
            $this->ledger->withdraw(
                userId: (int) $withdrawal->user_id,
                kind: 'earnings',
                amountMinor: (int) $withdrawal->amount_minor,
                currency: (string) $withdrawal->currency,
                referenceType: Withdrawal::class,
                referenceId: (int) $withdrawal->getKey(),
                providerReference: (string) $lookupId,
                idempotencyKey: 'payout:'.$withdrawal->getKey(),
            );
            $withdrawal->update(['status' => 'paid', 'provider_payout_id' => $lookupId]);
            $this->audit->record('withdrawal.paid', $withdrawal, ['provider_event_id' => $eventId]);
        });

        return ['status' => 'settled', 'detail' => 'Payout confirmed by the provider API and debited from the ledger.'];
    }

    /**
     * Razorpay nests entities under payload.*; a generic provider may post flat keys.
     *
     * @return array{provider_payment_id: ?string, payment_uuid: ?string}
     */
    private function paymentReference(array $payload): array
    {
        $link = $payload['payload']['payment_link']['entity'] ?? [];
        $entity = $payload['payload']['payment']['entity'] ?? [];

        $providerId = $this->firstFilled([
            $link['id'] ?? null,
            $entity['id'] ?? null,
            $payload['provider_payment_id'] ?? null,
            $payload['id'] ?? null,
        ]);

        $uuid = $this->firstFilled([
            $link['reference_id'] ?? null,
            $link['notes']['payment_uuid'] ?? null,
            $entity['notes']['payment_uuid'] ?? null,
            $payload['payment_uuid'] ?? null,
        ]);

        return ['provider_payment_id' => $providerId, 'payment_uuid' => $uuid];
    }

    /**
     * @return array{provider_payout_id: ?string, withdrawal_id: ?int}
     */
    private function payoutReference(array $payload): array
    {
        $entity = $payload['payload']['payout']['entity'] ?? [];
        $providerId = $this->firstFilled([
            $entity['id'] ?? null,
            $payload['provider_payout_id'] ?? null,
            $payload['id'] ?? null,
        ]);

        $reference = (string) $this->firstFilled([
            $entity['reference_id'] ?? null,
            $payload['reference_id'] ?? null,
        ]);
        $withdrawalId = str_starts_with($reference, 'wd_') ? (int) substr($reference, 3) : null;

        return ['provider_payout_id' => $providerId, 'withdrawal_id' => $withdrawalId ?: null];
    }

    private function findPayment(?string $providerPaymentId, ?string $paymentUuid): ?Payment
    {
        if (filled($paymentUuid)) {
            $byUuid = Payment::query()->where('payment_uuid', $paymentUuid)->first();
            if ($byUuid) {
                return $byUuid;
            }
        }

        return filled($providerPaymentId)
            ? Payment::query()->where('provider_payment_id', $providerPaymentId)->first()
            : null;
    }

    private function findWithdrawal(?string $providerPayoutId, ?int $withdrawalId): ?Withdrawal
    {
        if ($withdrawalId) {
            $byId = Withdrawal::query()->find($withdrawalId);
            if ($byId) {
                return $byId;
            }
        }

        return filled($providerPayoutId)
            ? Withdrawal::query()->where('provider_payout_id', $providerPayoutId)->first()
            : null;
    }

    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
