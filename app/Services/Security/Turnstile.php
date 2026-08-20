<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile, checked server side.
 *
 * The widget on the page proves nothing on its own - anyone can post straight
 * to the endpoint - so the token is always verified against Cloudflare before
 * a submission is accepted.
 *
 * With no keys configured the check is skipped rather than failed. Refusing
 * every public form the moment this shipped, on a site whose keys are set by
 * hand, would take the product down to add a defence; the honeypot and the
 * rate limit are still there in the meantime.
 */
class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function isConfigured(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret_key'));
    }

    public function siteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }

    /**
     * @return array{passed: bool, reason: string}
     */
    public function verify(?string $token, ?string $ip = null): array
    {
        if (! $this->isConfigured()) {
            return ['passed' => true, 'reason' => 'not_configured'];
        }

        if (blank($token)) {
            return ['passed' => false, 'reason' => 'missing_token'];
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (\Throwable $e) {
            // Cloudflare being unreachable must not close the front door.
            // Logged so it is visible rather than assumed.
            Log::warning('Turnstile verification could not be reached.', ['error' => $e->getMessage()]);

            return ['passed' => true, 'reason' => 'verifier_unreachable'];
        }

        $body = $response->json();

        return [
            'passed' => (bool) ($body['success'] ?? false),
            'reason' => implode(',', (array) ($body['error-codes'] ?? [])) ?: 'rejected',
        ];
    }
}
