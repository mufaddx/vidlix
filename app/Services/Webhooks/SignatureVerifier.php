<?php

namespace App\Services\Webhooks;

use Illuminate\Http\Request;

/**
 * Per-provider webhook signature verification.
 *
 * Providers do not agree on a scheme, so each one declares which it uses in
 * config('vidlix.webhooks.schemes'). A webhook that cannot be proven authentic
 * is never processed — it is logged as rejected and nothing downstream runs.
 */
class SignatureVerifier
{
    public const VALID = 'valid';

    public const INVALID = 'invalid';

    public const MISSING_SIGNATURE = 'missing_signature';

    public const NOT_CONFIGURED = 'provider_not_configured';

    public const UNSUPPORTED = 'unsupported_scheme';

    /** Secret each provider signs with. Meta signs with the app secret, not the verify token. */
    public function secretFor(string $provider): ?string
    {
        $secret = match ($provider) {
            'payment' => config('vidlix.webhooks.payment_secret'),
            'payout' => config('vidlix.webhooks.payout_secret'),
            'email' => config('vidlix.webhooks.email_secret'),
            'meta' => config('vidlix.webhooks.meta_app_secret'),
            default => null,
        };

        return filled($secret) ? (string) $secret : null;
    }

    public function scheme(string $provider): string
    {
        return (string) (config('vidlix.webhooks.schemes.'.$provider) ?: 'hmac_hex');
    }

    public function verify(string $provider, Request $request, ?string $secret): string
    {
        $scheme = $this->scheme($provider);

        if ($scheme === 'basic') {
            return $this->verifyBasic($request);
        }

        if (! filled($secret)) {
            return self::NOT_CONFIGURED;
        }

        return match ($scheme) {
            'hmac_hex' => $this->verifyHmacHex($provider, $request, (string) $secret),
            'hub_signature' => $this->verifyHubSignature($request, (string) $secret),
            'sendgrid_ecdsa' => $this->verifySendGridEcdsa($request),
            default => self::UNSUPPORTED,
        };
    }

    /** Razorpay and the generic scheme: hex HMAC-SHA256 over the exact raw body. */
    private function verifyHmacHex(string $provider, Request $request, string $secret): string
    {
        $headers = array_merge(
            ['X-Webhook-Signature'],
            (array) config('vidlix.webhooks.headers.'.$provider, []),
        );

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        $sawSignature = false;

        foreach ($headers as $header) {
            $provided = (string) $request->header($header, '');
            if ($provided === '') {
                continue;
            }
            $sawSignature = true;
            // Some providers prefix the digest ("sha256=<hex>"); compare the
            // digest itself. Only a known prefix is stripped, so a base64
            // signature's own padding is never mistaken for one.
            if (str_starts_with($provided, 'sha256=')) {
                $provided = substr($provided, 7);
            }
            if (hash_equals($expected, $provided)) {
                return self::VALID;
            }
        }

        return $sawSignature ? self::INVALID : self::MISSING_SIGNATURE;
    }

    /** Meta: X-Hub-Signature-256: sha256=<hex hmac of raw body with the app secret>. */
    private function verifyHubSignature(Request $request, string $appSecret): string
    {
        $provided = (string) $request->header('X-Hub-Signature-256', '');
        if ($provided === '') {
            return self::MISSING_SIGNATURE;
        }
        if (! str_starts_with($provided, 'sha256=')) {
            return self::INVALID;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $provided) ? self::VALID : self::INVALID;
    }

    /**
     * SendGrid Event Webhook: ECDSA (P-256/SHA-256) over timestamp + raw body.
     * The verification key is the base64 DER public key from the SendGrid UI.
     */
    private function verifySendGridEcdsa(Request $request): string
    {
        $signature = (string) $request->header('X-Twilio-Email-Event-Webhook-Signature', '');
        $timestamp = (string) $request->header('X-Twilio-Email-Event-Webhook-Timestamp', '');
        if ($signature === '' || $timestamp === '') {
            return self::MISSING_SIGNATURE;
        }

        $publicKey = (string) config('vidlix.email.verification_key');
        if ($publicKey === '') {
            return self::NOT_CONFIGURED;
        }

        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(trim($publicKey), 64, "\n")."-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            return self::NOT_CONFIGURED;
        }

        $decoded = base64_decode($signature, true);
        if ($decoded === false) {
            return self::INVALID;
        }

        $result = openssl_verify($timestamp.$request->getContent(), $decoded, $key, OPENSSL_ALGO_SHA256);

        return $result === 1 ? self::VALID : self::INVALID;
    }

    /** Postmark-style inbound: HTTP Basic credentials on the webhook URL. */
    private function verifyBasic(Request $request): string
    {
        $user = (string) config('vidlix.email.webhook_username');
        $password = (string) config('vidlix.email.webhook_password');
        if ($user === '' || $password === '') {
            return self::NOT_CONFIGURED;
        }

        $givenUser = (string) ($request->server('PHP_AUTH_USER') ?? '');
        $givenPassword = (string) ($request->server('PHP_AUTH_PW') ?? '');
        if ($givenUser === '' && $givenPassword === '') {
            return self::MISSING_SIGNATURE;
        }

        return hash_equals($user, $givenUser) && hash_equals($password, $givenPassword)
            ? self::VALID
            : self::INVALID;
    }
}
