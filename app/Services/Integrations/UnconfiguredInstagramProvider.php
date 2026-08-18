<?php

namespace App\Services\Integrations;

use App\Contracts\InstagramProviderInterface;
use App\Models\CreatorProfile;

class UnconfiguredInstagramProvider implements InstagramProviderInterface
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'unconfigured';
    }

    public function authorizationUrl(CreatorProfile $profile): ?string
    {
        return null;
    }

    public function completeAuthorization(CreatorProfile $profile, string $code): array
    {
        return [
            'status' => 'provider_not_configured',
            'detail' => 'Meta OAuth is not configured. No Instagram account was linked.',
        ];
    }

    public function syncPermittedData(CreatorProfile $profile): array
    {
        return [
            'status' => 'provider_not_configured',
            'insights' => [],
            'detail' => 'Instagram is disconnected until Meta OAuth is configured. No analytics were invented.',
        ];
    }
}
