<?php

namespace App\Services\Integrations;

use App\Contracts\PaymentProviderInterface;

class UnconfiguredPaymentProvider implements PaymentProviderInterface
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'unconfigured';
    }

    public function createCheckout(int $amountMinor, string $currency, array $metadata): array
    {
        return [
            'status' => 'provider_not_configured',
            'checkout_url' => null,
            'provider_payment_id' => null,
            'detail' => 'Payments cannot start until PAYMENT_PROVIDER credentials are configured. No success state was recorded.',
        ];
    }

    public function fetchPayment(string $providerPaymentId): array
    {
        return [
            'status' => 'provider_not_configured',
            'amount_minor' => null,
            'currency' => null,
            'provider_payment_id' => null,
            'detail' => 'No payment provider is configured, so no authoritative status exists.',
        ];
    }

    public function refundPayment(string $providerPaymentId, int $amountMinor, string $reason): array
    {
        return [
            'status' => 'provider_not_configured',
            'refunded_minor' => null,
            'provider_refund_id' => null,
            'detail' => 'No payment provider is configured, so nothing was refunded.',
        ];
    }
}
