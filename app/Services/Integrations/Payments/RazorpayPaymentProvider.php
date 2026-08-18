<?php

namespace App\Services\Integrations\Payments;

use App\Contracts\PaymentProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Razorpay (INR) hosted Payment Links.
 *
 * createCheckout() only produces a URL a payer may open. Nothing in this class
 * marks anything as paid; settlement runs through a signed webhook that calls
 * fetchPayment() for the authoritative status.
 */
class RazorpayPaymentProvider implements PaymentProviderInterface
{
    public function name(): string
    {
        return 'razorpay';
    }

    public function isConfigured(): bool
    {
        return filled(config('vidlix.payment.key_id')) && filled(config('vidlix.payment.key_secret'));
    }

    public function createCheckout(int $amountMinor, string $currency, array $metadata): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'provider_not_configured',
                'checkout_url' => null,
                'provider_payment_id' => null,
                'detail' => 'PAYMENT_KEY_ID / PAYMENT_KEY_SECRET are missing.',
            ];
        }

        $notes = array_map(
            static fn ($value) => is_scalar($value) ? (string) $value : json_encode($value),
            array_filter($metadata, static fn ($v) => $v !== null),
        );

        $payload = [
            'amount' => $amountMinor,
            'currency' => strtoupper($currency),
            'accept_partial' => false,
            'description' => (string) ($metadata['description'] ?? 'Vidlix project payment'),
            'reference_id' => (string) ($metadata['payment_uuid'] ?? ''),
            'notes' => $notes,
            'reminder_enable' => true,
        ];

        if (filled(config('vidlix.payment.callback_url'))) {
            $payload['callback_url'] = (string) config('vidlix.payment.callback_url');
            // The browser return is informational only; it never settles a payment.
            $payload['callback_method'] = 'get';
        }

        if (filled($metadata['customer_email'] ?? null) || filled($metadata['customer_name'] ?? null)) {
            $payload['customer'] = array_filter([
                'name' => $metadata['customer_name'] ?? null,
                'email' => $metadata['customer_email'] ?? null,
                'contact' => $metadata['customer_contact'] ?? null,
            ]);
            $payload['notify'] = ['email' => filled($metadata['customer_email'] ?? null), 'sms' => false];
        }

        try {
            $response = $this->client()->post('/payment_links', $payload);
        } catch (Throwable $e) {
            Log::warning('razorpay.payment_link.transport_failure', ['message' => $e->getMessage()]);

            return [
                'status' => 'provider_unavailable',
                'checkout_url' => null,
                'provider_payment_id' => null,
                'detail' => 'Razorpay could not be reached. No payment was created.',
            ];
        }

        if (! $response->successful()) {
            return [
                'status' => 'provider_error',
                'checkout_url' => null,
                'provider_payment_id' => null,
                'detail' => 'Razorpay rejected the payment link request: '.$this->errorText($response->json()),
            ];
        }

        $body = (array) $response->json();

        return [
            'status' => 'checkout_created',
            'checkout_url' => $body['short_url'] ?? null,
            'provider_payment_id' => $body['id'] ?? null,
            'detail' => 'Checkout link created. The payment stays unpaid until a signed webhook confirms it.',
        ];
    }

    public function fetchPayment(string $providerPaymentId): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'provider_not_configured',
                'amount_minor' => null,
                'currency' => null,
                'provider_payment_id' => null,
                'detail' => 'PAYMENT_KEY_ID / PAYMENT_KEY_SECRET are missing.',
            ];
        }

        $path = str_starts_with($providerPaymentId, 'plink_')
            ? '/payment_links/'.$providerPaymentId
            : '/payments/'.$providerPaymentId;

        try {
            $response = $this->client()->get($path);
        } catch (Throwable $e) {
            Log::warning('razorpay.payment.fetch_failure', ['message' => $e->getMessage()]);

            return [
                'status' => 'provider_unavailable',
                'amount_minor' => null,
                'currency' => null,
                'provider_payment_id' => $providerPaymentId,
                'detail' => 'Razorpay could not be reached. The status stays unknown.',
            ];
        }

        if (! $response->successful()) {
            return [
                'status' => 'provider_error',
                'amount_minor' => null,
                'currency' => null,
                'provider_payment_id' => $providerPaymentId,
                'detail' => 'Razorpay returned '.$response->status().': '.$this->errorText($response->json()),
            ];
        }

        $body = (array) $response->json();
        $raw = (string) ($body['status'] ?? '');

        return [
            'status' => $this->normalizeStatus($raw),
            'amount_minor' => $this->paidAmount($body),
            'currency' => $body['currency'] ?? null,
            'provider_payment_id' => $body['id'] ?? $providerPaymentId,
            'detail' => 'Razorpay reports status '.$raw.'.',
        ];
    }

    /** Map both payment-link and payment entity states onto our own vocabulary. */
    private function normalizeStatus(string $raw): string
    {
        return match ($raw) {
            'paid', 'captured' => 'paid',
            'authorized' => 'authorized',
            'created', 'issued', 'pending', 'partially_paid' => 'pending',
            'failed', 'cancelled', 'expired' => 'failed',
            'refunded' => 'refunded',
            default => 'unknown',
        };
    }

    private function paidAmount(array $body): ?int
    {
        if (array_key_exists('amount_paid', $body)) {
            return (int) $body['amount_paid'];
        }
        if (array_key_exists('amount', $body)) {
            return (int) $body['amount'];
        }

        return null;
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth(
            (string) config('vidlix.payment.key_id'),
            (string) config('vidlix.payment.key_secret'),
        )
            ->baseUrl(rtrim((string) config('vidlix.payment.api_base'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('vidlix.payment.timeout', 20));
    }

    private function errorText(mixed $json): string
    {
        if (is_array($json) && isset($json['error']['description'])) {
            return (string) $json['error']['description'];
        }

        return 'unspecified provider error';
    }
}
