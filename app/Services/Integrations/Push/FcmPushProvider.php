<?php

namespace App\Services\Integrations\Push;

use App\Contracts\PushProviderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Firebase Cloud Messaging HTTP v1 (covers Android and, through FCM, APNs).
 *
 * Auth is a service-account JWT exchanged for a short-lived access token. The
 * credentials file is read from disk and never committed.
 */
class FcmPushProvider implements PushProviderInterface
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function name(): string
    {
        return 'fcm';
    }

    public function isConfigured(): bool
    {
        $path = (string) config('vidlix.push.fcm_credentials');

        return $path !== '' && is_readable($path) && filled($this->projectId());
    }

    public function send(array $deviceTokens, string $title, string $body, array $data = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'provider_not_configured',
                'sent' => 0,
                'failed' => 0,
                'detail' => 'FCM_CREDENTIALS_PATH / FCM_PROJECT_ID are missing. Nothing was sent.',
            ];
        }

        $accessToken = $this->accessToken();
        if ($accessToken === null) {
            return [
                'status' => 'authorization_failed',
                'sent' => 0,
                'failed' => 0,
                'detail' => 'Could not obtain an FCM access token. Nothing was sent.',
            ];
        }

        $endpoint = 'https://fcm.googleapis.com/v1/projects/'.$this->projectId().'/messages:send';
        $sent = 0;
        $failed = 0;

        foreach ($deviceTokens as $token) {
            try {
                $response = Http::withToken($accessToken)
                    ->asJson()
                    ->timeout((int) config('vidlix.push.timeout', 15))
                    ->post($endpoint, [
                        'message' => [
                            'token' => $token,
                            'notification' => ['title' => $title, 'body' => $body],
                            'data' => array_map(static fn ($v) => (string) $v, $data),
                        ],
                    ]);
            } catch (Throwable $e) {
                Log::warning('fcm.send.transport_failure', ['message' => $e->getMessage()]);
                $failed++;

                continue;
            }

            $response->successful() ? $sent++ : $failed++;
        }

        return [
            'status' => $failed === 0 ? 'sent' : ($sent === 0 ? 'failed' : 'partial'),
            'sent' => $sent,
            'failed' => $failed,
            'detail' => $sent.' accepted by FCM, '.$failed.' rejected.',
        ];
    }

    private function projectId(): ?string
    {
        $configured = (string) config('vidlix.push.fcm_project_id');
        if ($configured !== '') {
            return $configured;
        }

        return $this->credentials()['project_id'] ?? null;
    }

    private function credentials(): array
    {
        $path = (string) config('vidlix.push.fcm_credentials');
        if ($path === '' || ! is_readable($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function accessToken(): ?string
    {
        return Cache::remember('vidlix:fcm:access_token', now()->addMinutes(50), function () {
            $credentials = $this->credentials();
            $email = $credentials['client_email'] ?? null;
            $privateKey = $credentials['private_key'] ?? null;
            if (! is_string($email) || ! is_string($privateKey)) {
                return null;
            }

            $now = time();
            $claims = [
                'iss' => $email,
                'scope' => self::SCOPE,
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode($claims)),
            ];
            $signature = '';
            if (! openssl_sign(implode('.', $segments), $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                return null;
            }
            $segments[] = $this->base64Url($signature);

            try {
                $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => implode('.', $segments),
                ]);
            } catch (Throwable $e) {
                Log::warning('fcm.token.transport_failure', ['message' => $e->getMessage()]);

                return null;
            }

            return $response->successful() ? (string) $response->json('access_token') : null;
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
