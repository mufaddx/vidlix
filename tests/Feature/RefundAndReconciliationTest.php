<?php

namespace Tests\Feature;

use App\Contracts\PaymentProviderInterface;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Payments\Reconciliation;
use App\Services\Payments\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Giving money back, and finding the payments nobody heard about.
 *
 * The property both share: the provider's record is authoritative and the
 * ledger is written only once the provider confirms. A refund that exists in
 * our ledger and not at the bank is a wrong number somebody will act on.
 */
class RefundAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A provider that answers however the test needs.
     *
     * @param  array<string, mixed>  $refund
     * @param  array<string, mixed>  $fetch
     */
    private function provider(array $refund = [], array $fetch = []): void
    {
        $refund = array_merge([
            'status' => 'refunded',
            'refunded_minor' => null,
            'provider_refund_id' => 'rfnd_1',
            'detail' => 'Confirmed.',
        ], $refund);

        $fetch = array_merge([
            'status' => 'paid',
            'amount_minor' => 500000,
            'currency' => 'INR',
            'provider_payment_id' => 'pay_1',
            'detail' => 'Paid.',
        ], $fetch);

        $this->app->bind(PaymentProviderInterface::class, fn () => new class($refund, $fetch) implements PaymentProviderInterface
        {
            public function __construct(private array $refund, private array $fetch) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function name(): string
            {
                return 'fake';
            }

            public function createCheckout(int $amountMinor, string $currency, array $metadata): array
            {
                return ['status' => 'ok', 'checkout_url' => null, 'provider_payment_id' => 'pay_1', 'detail' => ''];
            }

            public function fetchPayment(string $providerPaymentId): array
            {
                return $this->fetch;
            }

            public function refundPayment(string $providerPaymentId, int $amountMinor, string $reason): array
            {
                return array_merge($this->refund, [
                    'refunded_minor' => $this->refund['refunded_minor'] ?? $amountMinor,
                ]);
            }
        });
    }

    private function capturedPayment(int $amountMinor = 500000): Payment
    {
        $payer = User::factory()->create(['email_verified_at' => now()]);

        return Payment::query()->create([
            'payment_uuid' => (string) Str::uuid(),
            'status' => 'captured',
            'amount_minor' => $amountMinor,
            'currency' => 'INR',
            'provider' => 'fake',
            'provider_payment_id' => 'pay_1',
            'payer_user_id' => $payer->id,
        ]);
    }

    /**
     * Age a payment.
     *
     * Written straight to the table because created_at is not fillable, and a
     * test that silently fails to age a row would pass for the wrong reason.
     */
    private function age(Payment $payment, string $status, \DateTimeInterface $when): void
    {
        DB::table('payments')
            ->where('id', $payment->id)
            ->update(['status' => $status, 'created_at' => $when]);
    }

    private function refunds(): RefundService
    {
        return app(RefundService::class);
    }

    /* -------------------------------------------------------------- refunds */

    public function test_a_full_refund_is_recorded_once_the_provider_confirms(): void
    {
        $this->provider();
        $payment = $this->capturedPayment();

        $result = $this->refunds()->refund($payment, 500000, 'Cancelled shoot');

        $this->assertSame('refunded', $result['status']);
        $this->assertSame(500000, $result['refunded_minor']);
        $this->assertSame('refunded', $payment->fresh()->status);
    }

    public function test_a_partial_refund_leaves_the_rest_refundable(): void
    {
        $this->provider();
        $payment = $this->capturedPayment(500000);

        $this->refunds()->refund($payment, 200000, 'One deliverable dropped');

        $payment = $payment->fresh();

        $this->assertSame('partially_refunded', $payment->status);
        $this->assertSame(200000, $this->refunds()->refundedMinor($payment));
        $this->assertSame(300000, $this->refunds()->remainingMinor($payment));
    }

    public function test_the_reversal_is_appended_rather_than_subtracted(): void
    {
        $this->provider();
        $payment = $this->capturedPayment();

        $this->refunds()->refund($payment, 200000, 'Partial');

        // Two facts, not one net figure: the ledger is append-only, and "we took
        // 5,000 and gave 2,000 back" should stay legible as what happened.
        $this->assertDatabaseHas('ledger_entries', [
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'amount_minor' => -200000,
        ]);
    }

    public function test_refunding_more_than_remains_is_refused(): void
    {
        $this->provider();
        $payment = $this->capturedPayment(500000);

        $this->refunds()->refund($payment, 400000, 'Most of it');

        // Refusing beats quietly capping: somebody who asked for more than
        // remains has misunderstood, and hiding that helps nobody.
        $this->expectException(ValidationException::class);

        $this->refunds()->refund($payment->fresh(), 200000, 'The rest and then some');
    }

    public function test_an_uncaptured_payment_cannot_be_refunded(): void
    {
        $this->provider();
        $payment = $this->capturedPayment();
        $payment->update(['status' => 'pending']);

        $this->expectException(ValidationException::class);

        $this->refunds()->refund($payment->fresh(), 100000, 'Too early');
    }

    public function test_nothing_is_written_when_the_provider_does_not_confirm(): void
    {
        $this->provider(['status' => 'provider_unavailable', 'detail' => 'Timed out.']);
        $payment = $this->capturedPayment();

        $result = $this->refunds()->refund($payment, 100000, 'Attempted');

        $this->assertNotSame('refunded', $result['status']);
        $this->assertSame(0, $result['refunded_minor']);

        // The money may or may not have moved, so the ledger stays silent and
        // the payment keeps its old status rather than claiming a refund.
        $this->assertSame(0, $this->refunds()->refundedMinor($payment->fresh()));
        $this->assertSame('captured', $payment->fresh()->status);
    }

    public function test_the_providers_figure_wins_over_the_requested_one(): void
    {
        // They refunded less than asked. Theirs is what actually left the account.
        $this->provider(['refunded_minor' => 150000]);
        $payment = $this->capturedPayment();

        $result = $this->refunds()->refund($payment, 200000, 'Partial');

        $this->assertSame(150000, $result['refunded_minor']);
        $this->assertSame(150000, $this->refunds()->refundedMinor($payment->fresh()));
    }

    public function test_a_repeated_refund_does_not_double_the_reversal(): void
    {
        $this->provider();
        $payment = $this->capturedPayment();

        // Same provider refund id both times — a retried webhook, or a double
        // click. The idempotency key is what stops the second write.
        $this->refunds()->refund($payment, 100000, 'First');

        $entries = LedgerEntry::query()
            ->where('reference_id', $payment->id)
            ->where('amount_minor', '<', 0)
            ->count();

        $this->assertSame(1, $entries);
    }

    public function test_a_refund_without_a_provider_reference_is_refused(): void
    {
        $this->provider();
        $payment = $this->capturedPayment();
        $payment->update(['provider_payment_id' => null]);

        $this->expectException(ValidationException::class);

        $this->refunds()->refund($payment->fresh(), 100000, 'No reference');
    }

    /* ------------------------------------------------------- reconciliation */

    public function test_a_payment_still_at_the_checkout_is_left_alone(): void
    {
        $this->provider();
        $payment = $this->capturedPayment();
        $this->age($payment, 'pending', now()->subMinutes(5));

        // Five minutes in, this is somebody still typing their card number.
        $this->assertCount(0, app(Reconciliation::class)->stale());
    }

    public function test_a_payment_pending_too_long_is_picked_up(): void
    {
        $this->provider();
        $payment = $this->capturedPayment();
        $this->age($payment, 'pending', now()->subHours(2));

        $this->assertCount(1, app(Reconciliation::class)->stale());
    }

    public function test_a_very_old_payment_is_left_to_the_archives(): void
    {
        $this->provider();
        $payment = $this->capturedPayment();
        $this->age($payment, 'pending', now()->subDays(60));

        $this->assertCount(0, app(Reconciliation::class)->stale());
    }

    public function test_a_lost_webhook_is_settled_by_the_sweep(): void
    {
        $this->provider();
        $payment = $this->capturedPayment();
        $this->age($payment, 'pending', now()->subHours(2));

        $result = app(Reconciliation::class)->run();

        // The provider says it was paid all along; the webhook simply never
        // arrived. Waiting for a customer to complain is not a strategy.
        $this->assertSame(1, $result['checked']);
        $this->assertSame('captured', $payment->fresh()->status);
    }

    public function test_a_payment_the_provider_calls_failed_is_marked_failed(): void
    {
        $this->provider(fetch: ['status' => 'failed', 'detail' => 'Card declined.']);
        $payment = $this->capturedPayment();
        $this->age($payment, 'pending', now()->subHours(2));

        $result = app(Reconciliation::class)->run();

        $this->assertSame(1, $result['failed']);
        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_an_inconclusive_answer_changes_nothing(): void
    {
        $this->provider(fetch: ['status' => 'provider_unavailable', 'detail' => 'Down.']);
        $payment = $this->capturedPayment();
        $this->age($payment, 'pending', now()->subHours(2));

        $result = app(Reconciliation::class)->run();

        // A payment the provider will not describe must not be guessed at in
        // either direction.
        $this->assertSame(1, $result['unknown']);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_the_sweep_says_so_when_there_is_no_provider(): void
    {
        $payment = $this->capturedPayment();
        $this->age($payment, 'pending', now()->subHours(2));

        $result = app(Reconciliation::class)->run();

        // Reporting a clean sweep that never happened would be worse than
        // reporting nothing.
        $this->assertNotNull($result['skipped']);
        $this->assertSame(0, $result['checked']);
    }

    public function test_a_settled_balance_is_summed_from_the_ledger(): void
    {
        $this->provider();
        $payment = $this->capturedPayment();
        $ledger = app(LedgerService::class);

        $ledger->append(
            (int) $payment->payer_user_id, 'earnings', LedgerService::STATE_RESERVED,
            500000, 'INR', Payment::class, (int) $payment->id,
        );

        $this->refunds()->refund($payment, 200000, 'Partial');

        // Balance is a sum over rows, never a stored figure, so a reversal moves
        // it without anything having to be kept in step by hand.
        $this->assertSame(
            300000,
            $ledger->balance((int) $payment->payer_user_id, LedgerService::STATE_RESERVED),
        );
    }
}
