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
}
