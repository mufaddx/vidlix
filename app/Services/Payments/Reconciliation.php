<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProviderInterface;
use App\Models\Payment;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;

/**
 * Finding the payments nobody heard back about.
 *
 * Webhooks are lost. Providers have outages, our own queue can be down, and a
 * delivery that never arrives leaves a payment sitting in limbo while somebody's
 * money has already moved. Waiting for a customer to complain is not a
 * reconciliation strategy.
 *
 * So this asks the provider directly about anything that has been pending too
 * long, and settles what turns out to have been paid. It is the same
 * authoritative fetch the webhook path uses — this only changes what prompts
 * the question.
 */
class Reconciliation
{
    /** Below this, a pending payment is simply somebody still at the checkout. */
    public const STALE_AFTER_MINUTES = 30;

    /** After this, chasing it is archaeology rather than reconciliation. */
    public const GIVE_UP_AFTER_DAYS = 30;

    public function __construct(
        private PaymentProviderInterface $payments,
        private PaymentSettlementService $settlement,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{checked: int, settled: int, failed: int, unknown: int, skipped: string|null}
     */
    public function run(int $limit = 200): array
    {
        if (! $this->payments->isConfigured()) {
            // Nothing to reconcile against. Saying so beats reporting a clean
            // sweep that never happened.
            return [
                'checked' => 0, 'settled' => 0, 'failed' => 0, 'unknown' => 0,
                'skipped' => 'No payment provider is configured.',
            ];
        }

        $checked = 0;
        $settled = 0;
        $failed = 0;
        $unknown = 0;

        foreach ($this->stale($limit) as $payment) {
            $checked++;

            $authoritative = $this->payments->fetchPayment((string) $payment->provider_payment_id);

            match ($authoritative['status']) {
                'paid' => $settled += $this->settle($payment, $authoritative),
                'failed' => $failed += $this->fail($payment, $authoritative),
                default => $unknown += $this->leaveAlone($payment, $authoritative),
            };
        }

        return [
            'checked' => $checked,
            'settled' => $settled,
            'failed' => $failed,
            'unknown' => $unknown,
            'skipped' => null,
        ];
    }

    /**
     * Payments old enough to be suspicious and young enough to be worth asking
     * about.
     *
     * @return Collection<int, Payment>
     */
    public function stale(int $limit = 200): Collection
    {
        return Payment::query()
            ->whereNotIn('status', ['captured', 'settled', 'failed', 'refunded', 'partially_refunded'])
            ->whereNotNull('provider_payment_id')
            ->where('created_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->where('created_at', '>', now()->subDays(self::GIVE_UP_AFTER_DAYS))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /** @param array<string, mixed> $authoritative */
    private function settle(Payment $payment, array $authoritative): int
    {
        /*
         | Routed through the same settlement service the webhook uses, rather
         | than crediting the ledger here. Two paths that both move money are
         | two places for the amount check to drift apart.
         */
        $result = $this->settlement->settlePaymentEvent(
            ['payment_uuid' => $payment->payment_uuid],
            'reconciliation:'.$payment->id.':'.now()->format('Ymd'),
            'reconciliation.sweep',
        );

        $this->audit->record('payment.reconciled', $payment, [
            'provider_status' => $authoritative['status'],
            'settlement' => $result['status'],
        ]);

        return $result['status'] === 'settled' ? 1 : 0;
    }

    /** @param array<string, mixed> $authoritative */
    private function fail(Payment $payment, array $authoritative): int
    {
        $payment->update(['status' => 'failed', 'last_provider_detail' => $authoritative['detail']]);

        $this->audit->record('payment.reconciled_failed', $payment, $authoritative);

        return 1;
    }

    /** @param array<string, mixed> $authoritative */
    private function leaveAlone(Payment $payment, array $authoritative): int
    {
        // Recorded and left where it is. A payment the provider will not
        // describe must not be guessed at in either direction.
        $this->audit->record('payment.reconciliation_inconclusive', $payment, $authoritative);

        return 1;
    }
}
