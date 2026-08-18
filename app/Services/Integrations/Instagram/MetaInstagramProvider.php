<?php

namespace App\Services\Integrations\Instagram;

use App\Contracts\InstagramProviderInterface;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Instagram through the official Meta Graph API only.
 *
 * Facebook Login for Business grants a page token; the Instagram professional
 * account hangs off that page. Nothing is scraped, and no metric is ever
 * synthesised: if Graph does not return a field, the field stays absent and the
 * UI shows the account as needing a reconnect or a sync.
 */
class MetaInstagramProvider implements InstagramProviderInterface
{
    public function name(): string
    {
        return 'meta';
    }

    public function isConfigured(): bool
    {
        return filled(config('vidlix.instagram.app_id'))
            && filled(config('vidlix.instagram.app_secret'))
            && filled(config('vidlix.instagram.redirect_uri'));
    }

    public function authorizationUrl(CreatorProfile $profile): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $query = http_build_query([
            'client_id' => (string) config('vidlix.instagram.app_id'),
            'redirect_uri' => (string) config('vidlix.instagram.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(',', (array) config('vidlix.instagram.scopes')),
            'state' => $this->stateFor($profile),
        ]);

        return 'https://www.facebook.com/'.config('vidlix.instagram.graph_version').'/dialog/oauth?'.$query;
    }

    /** Signed state so a callback cannot be replayed against a different creator. */
    public function stateFor(CreatorProfile $profile): string
    {
        $payload = $profile->getKey().'.'.now()->timestamp;

        return $payload.'.'.hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    public function creatorProfileIdFromState(string $state): ?int
    {
        $parts = explode('.', $state);
        if (count($parts) !== 3) {
            return null;
        }
        [$profileId, $timestamp, $signature] = $parts;
        $expected = hash_hmac('sha256', $profileId.'.'.$timestamp, (string) config('app.key'));
        if (! hash_equals($expected, $signature)) {
            return null;
        }
        if (now()->timestamp - (int) $timestamp > 3600) {
            return null;
        }

        return (int) $profileId;
    }

    public function completeAuthorization(CreatorProfile $profile, string $code): array
    {
        if (! $this->isConfigured()) {
            return ['status' => 'provider_not_configured', 'detail' => 'META_APP_ID / META_APP_SECRET / META_REDIRECT_URI are missing.'];
        }

        try {
            $tokenResponse = $this->client()->get('/oauth/access_token', [
                'client_id' => (string) config('vidlix.instagram.app_id'),
                'client_secret' => (string) config('vidlix.instagram.app_secret'),
                'redirect_uri' => (string) config('vidlix.instagram.redirect_uri'),
                'code' => $code,
            ]);
        } catch (Throwable $e) {
            Log::warning('meta.oauth.transport_failure', ['message' => $e->getMessage()]);

            return ['status' => 'provider_unavailable', 'detail' => 'Meta could not be reached. No account was linked.'];
        }

        if (! $tokenResponse->successful()) {
            return ['status' => 'authorization_failed', 'detail' => $this->errorText($tokenResponse->json())];
        }

        $shortLived = (string) ($tokenResponse->json('access_token') ?? '');
        if ($shortLived === '') {
            return ['status' => 'authorization_failed', 'detail' => 'Meta returned no access token.'];
        }

        $longLived = $this->exchangeForLongLivedToken($shortLived);
        $binding = $this->resolveInstagramAccount($longLived['token']);

        if ($binding['status'] !== 'ok') {
            $this->storeAccount($profile, [
                'status' => $binding['status'],
                'token_encrypted' => Crypt::encryptString($longLived['token']),
                'token_expires_at' => $longLived['expires_at'],
                'authorized_at' => now(),
                'granted_scopes' => (array) config('vidlix.instagram.scopes'),
            ]);

            return ['status' => $binding['status'], 'detail' => $binding['detail']];
        }

        $this->storeAccount($profile, [
            'status' => 'connected',
            'ig_user_id' => $binding['ig_user_id'],
            'username' => $binding['username'],
            'token_encrypted' => Crypt::encryptString($binding['page_token']),
            'token_expires_at' => $longLived['expires_at'],
            'authorized_at' => now(),
            'granted_scopes' => (array) config('vidlix.instagram.scopes'),
        ]);
        $profile->update(['instagram_connection_status' => 'connected']);

        return ['status' => 'connected', 'detail' => 'Instagram professional account linked. Insights appear after the first sync.'];
    }

    public function syncPermittedData(CreatorProfile $profile): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'provider_not_configured',
                'insights' => [],
                'detail' => 'Instagram is disconnected until Meta OAuth is configured. No analytics were invented.',
            ];
        }

        $account = $profile->instagramAccount;
        if (! $account || ! filled($account->token_encrypted) || ! filled($account->ig_user_id)) {
            return [
                'status' => 'not_connected',
                'insights' => [],
                'detail' => 'This creator has not linked an Instagram professional account yet.',
            ];
        }

        try {
            $token = Crypt::decryptString((string) $account->token_encrypted);
        } catch (Throwable) {
            return [
                'status' => 'reauth_required',
                'insights' => [],
                'detail' => 'The stored token could not be read. The creator must reconnect Instagram.',
            ];
        }

        try {
            $response = $this->client()->get('/'.$account->ig_user_id, [
                'fields' => 'username,followers_count,follows_count,media_count',
                'access_token' => $token,
            ]);
        } catch (Throwable $e) {
            Log::warning('meta.sync.transport_failure', ['message' => $e->getMessage()]);

            return ['status' => 'provider_unavailable', 'insights' => [], 'detail' => 'Meta could not be reached. Nothing was updated.'];
        }

        if (! $response->successful()) {
            $status = $this->isAuthError($response->json()) ? 'reauth_required' : 'sync_failed';
            $account->update(['status' => $status]);
            $profile->update(['instagram_connection_status' => $status]);

            return [
                'status' => $status,
                'insights' => [],
                'detail' => 'Meta rejected the request: '.$this->errorText($response->json()),
            ];
        }

        $body = (array) $response->json();
        // Only fields Graph actually returned are kept. Absent means absent.
        $insights = array_filter([
            'username' => $body['username'] ?? null,
            'followers_count' => $body['followers_count'] ?? null,
            'follows_count' => $body['follows_count'] ?? null,
            'media_count' => $body['media_count'] ?? null,
        ], static fn ($value) => $value !== null);

        $insights = array_merge($insights, $this->accountInsights($account->ig_user_id, $token));

        $account->update([
            'status' => 'connected',
            'username' => $body['username'] ?? $account->username,
            'last_synced_at' => now(),
            'insights' => $insights,
            'insights_synced_at' => now(),
            'last_error' => null,
        ]);
        $profile->update(['instagram_connection_status' => 'connected']);

        return [
            'status' => 'synced',
            'insights' => $insights,
            'detail' => 'Fetched from the Instagram Graph API at '.now()->toDateTimeString().'.',
        ];
    }

    /**
     * Account-level insights. A permission gap here is not an error worth
     * failing the whole sync over — the metric is simply left out.
     */
    private function accountInsights(string $igUserId, string $token): array
    {
        try {
            $response = $this->client()->get('/'.$igUserId.'/insights', [
                'metric' => 'reach,profile_views',
                'period' => 'day',
                'metric_type' => 'total_value',
                'access_token' => $token,
            ]);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $metrics = [];
        foreach ((array) $response->json('data', []) as $item) {
            $name = $item['name'] ?? null;
            $value = $item['total_value']['value'] ?? ($item['values'][0]['value'] ?? null);
            if (is_string($name) && $value !== null) {
                $metrics[$name] = $value;
            }
        }

        return $metrics === [] ? [] : ['period_day' => $metrics];
    }

    /**
     * @return array{token: string, expires_at: ?Carbon}
     */
    private function exchangeForLongLivedToken(string $shortLived): array
    {
        try {
            $response = $this->client()->get('/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => (string) config('vidlix.instagram.app_id'),
                'client_secret' => (string) config('vidlix.instagram.app_secret'),
                'fb_exchange_token' => $shortLived,
            ]);
        } catch (Throwable) {
            return ['token' => $shortLived, 'expires_at' => null];
        }

        if (! $response->successful() || ! filled($response->json('access_token'))) {
            return ['token' => $shortLived, 'expires_at' => null];
        }

        $expiresIn = (int) ($response->json('expires_in') ?? 0);

        return [
            'token' => (string) $response->json('access_token'),
            'expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
        ];
    }

    /**
     * @return array{status: string, detail: string, ig_user_id?: string, username?: ?string, page_token?: string}
     */
    private function resolveInstagramAccount(string $userToken): array
    {
        try {
            $pages = $this->client()->get('/me/accounts', [
                'fields' => 'id,name,access_token,instagram_business_account{id,username}',
                'access_token' => $userToken,
            ]);
        } catch (Throwable) {
            return ['status' => 'provider_unavailable', 'detail' => 'Meta could not be reached while listing pages.'];
        }

        if (! $pages->successful()) {
            return ['status' => 'authorization_failed', 'detail' => $this->errorText($pages->json())];
        }

        foreach ((array) $pages->json('data', []) as $page) {
            $ig = $page['instagram_business_account'] ?? null;
            if (is_array($ig) && filled($ig['id'] ?? null)) {
                return [
                    'status' => 'ok',
                    'detail' => 'Instagram professional account found.',
                    'ig_user_id' => (string) $ig['id'],
                    'username' => $ig['username'] ?? null,
                    'page_token' => (string) ($page['access_token'] ?? $userToken),
                ];
            }
        }

        return [
            'status' => 'no_professional_account',
            'detail' => 'No Instagram professional account is linked to a Facebook Page on this login. Connect one in Meta, then retry.',
        ];
    }

    private function storeAccount(CreatorProfile $profile, array $attributes): void
    {
        InstagramAccount::query()->updateOrCreate(
            ['creator_profile_id' => $profile->getKey()],
            $attributes,
        );
    }

    private function isAuthError(mixed $json): bool
    {
        $code = is_array($json) ? ($json['error']['code'] ?? null) : null;

        return in_array((int) $code, [190, 102, 463, 467], true);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(
            rtrim((string) config('vidlix.instagram.graph_base'), '/').'/'.config('vidlix.instagram.graph_version'),
        )
            ->acceptJson()
            ->timeout((int) config('vidlix.instagram.timeout', 20));
    }

    private function errorText(mixed $json): string
    {
        if (is_array($json) && isset($json['error']['message'])) {
            return (string) $json['error']['message'];
        }

        return 'unspecified Meta error';
    }
}
