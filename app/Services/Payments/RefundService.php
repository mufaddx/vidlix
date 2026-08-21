<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProviderInterface;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Giving money back.
 *
 * The rule is the same one capture follows: the provider's record is
 * authoritative, and the ledger is written only after the provider confirms. A
 * refund that exists in our ledger and not at the bank is worse than one that
 * has not happened — the first is a wrong number somebody will act on, the
 * second is a task somebody can still do.
 *
 * Reversals are appended, never subtracted from an existing entry. The ledger
 * is append-only, so "we took 5,000 and gave 2,000 back" stays legible as two
 * facts rather than collapsing into a 3,000 that explains nothing.
 */
class RefundService
{
    public function __construct(
        private PaymentProviderInterface $payments,
        private LedgerService $ledger,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{status: string, refunded_minor: int, detail: string}
     */
    public function refund(Payment $payment, int $amountMinor, string $reason, ?User $actor = null): array
    {
        if (! in_array($payment->status, ['captured', 'settled'], true)) {
            throw ValidationException::withMessages([
                'payment' => __('Only a captured payment can be refunded.'),
            ]);
        }

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages([
                'amount_minor' => __('Enter the amount to refund.'),
            ]);
        }

        $alreadyRefunded = $this->refundedMinor($payment);
        $remaining = (int) $payment->amount_minor - $alreadyRefunded;

        if ($amountMinor > $remaining) {
            // Refusing beats capping. Somebody who asked for more than remains
            // has misunderstood something, and quietly refunding less would
            // hide the misunderstanding rather than surface it.
            throw ValidationException::withMessages([
                'amount_minor' => __('Only :amount remains refundable on this payment.', [
                    'amount' => number_format($remaining / 100, 2),
                ]),
            ]);
        }

        if (! filled($payment->provider_payment_id)) {
            throw ValidationException::withMessages([
                'payment' => __('This payment has no provider reference, so it cannot be refunded automatically.'),
            ]);
        }

        $result = $this->payments->refundPayment(
            (string) $payment->provider_payment_id,
            $amountMinor,
            $reason,
        );

        if ($result['status'] !== 'refunded') {
            // Recorded, but nothing is written to the ledger. An unknown result
            // in particular must not be retried blindly — the money may already
            // have moved.
            $this->audit->record('payment.refund_not_confirmed', $payment, [
                'requested_minor' => $amountMinor,
                'provider_status' => $result['status'],
                'detail' => $result['detail'],
            ], $actor?->id);

            return [
                'status' => $result['status'],
                'refunded_minor' => 0,
                'detail' => $result['detail'],
            ];
        }

        // Trust the provider's figure over the requested one. If they refunded
        // a different amount, theirs is what actually left the account.
        $confirmed = (int) ($result['refunded_minor'] ?? $amountMinor);

        $this->writeReversal($payment, $confirmed, $reason, $result['provider_refund_id'] ?? null, $actor);

        return [
            'status' => 'refunded',
            'refunded_minor' => $confirmed,
            'detail' => $result['detail'],
        ];
    }

    /** What has already been given back on this payment. */
    public function refundedMinor(Payment $payment): int
    {
        return (int) DB::table('ledger_entries')
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->where('state', LedgerService::STATE_RESERVED)
            ->where('amount_minor', '<', 0)
            ->sum('amount_minor') * -1;
    }

    public function remainingMinor(Payment $payment): int
    {
        return (int) $payment->amount_minor - $this->refundedMinor($payment);
    }

    private function writeReversal(
        Payment $payment,
        int $amountMinor,
        string $reason,
        ?string $providerRefundId,
        ?User $actor,
    ): void {
        DB::transaction(function () use ($payment, $amountMinor, $reason, $providerRefundId, $actor) {
            $payment->load('payable');

            $sellerId = $payment->payer_user_id;

            if ($payment->payable instanceof Project) {
                $sellerId = $payment->payable->counterparty_user_id;
            }

            if ($sellerId !== null) {
                $this->ledger->append(
                    $sellerId,
                    'earnings',
                    LedgerService::STATE_RESERVED,
                    // Negative: an appended reversal, never an edited entry.
                    -$amountMinor,
                    (string) $payment->currency,
                    Payment::class,
                    (int) $payment->id,
                    $providerRefundId,
                    ['reason' => $reason, 'kind' => 'refund'],
                    // Idempotency on the provider's own refund id, so a repeated
                    // webhook or a double click cannot write the reversal twice.
                    'refund:'.($providerRefundId ?: $payment->id.':'.$amountMinor),
                );
            }

            $fullyRefunded = $this->refundedMinor($payment->fresh()) >= (int) $payment->amount_minor;

            $payment->forceFill([
                'status' => $fullyRefunded ? 'refunded' : 'partially_refunded',
                'last_provider_detail' => 'Refunded '.number_format($amountMinor / 100, 2).': '.$reason,
            ])->save();

            $this->audit->record('payment.refunded', $payment, [
                'amount_minor' => $amountMinor,
                'provider_refund_id' => $providerRefundId,
                'reason' => $reason,
                'fully_refunded' => $fullyRefunded,
            ], $actor?->id);
        });
    }
}
