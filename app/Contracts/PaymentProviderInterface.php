<?php

namespace App\Contracts;

interface PaymentProviderInterface
{
    public function isConfigured(): bool;

    /** Machine name recorded on payments and audit entries. */
    public function name(): string;

    /**
     * Start a hosted checkout. Returning a URL is not a settlement.
     *
     * @return array{status: string, checkout_url: ?string, provider_payment_id: ?string, detail: string}
     */
    public function createCheckout(int $amountMinor, string $currency, array $metadata): array;

    /**
     * Authoritative status straight from the provider API. Webhook bodies are
     * only a trigger to call this; they never settle money on their own.
     *
     * @return array{status: string, amount_minor: ?int, currency: ?string, provider_payment_id: ?string, detail: string}
     */
    public function fetchPayment(string $providerPaymentId): array;

    /**
     * Refund a captured payment, in full or in part.
     *
     * Returning a result is not a refund. As with a capture, the provider's own
     * record is authoritative and the ledger is only written once the provider
     * confirms — a refund that exists locally and not at the bank is worse than
     * one that has not happened.
     *
     * @return array{status: string, refunded_minor: ?int, provider_refund_id: ?string, detail: string}
     */
    public function refundPayment(string $providerPaymentId, int $amountMinor, string $reason): array;
}
