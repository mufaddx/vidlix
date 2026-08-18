<?php

namespace App\Services\Integrations;

use App\Contracts\PushProviderInterface;

class UnconfiguredPushProvider implements PushProviderInterface
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'unconfigured';
    }

    public function send(array $deviceTokens, string $title, string $body, array $data = []): array
    {
        return [
            'status' => 'provider_not_configured',
            'sent' => 0,
            'failed' => 0,
            'detail' => 'Push delivery needs PUSH_PROVIDER credentials. Nothing was sent.',
        ];
    }
}
