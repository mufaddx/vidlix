<?php

namespace App\Contracts;

use App\Models\CreatorProfile;

interface InstagramProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    public function authorizationUrl(CreatorProfile $profile): ?string;

    /**
     * Exchange an OAuth authorization code for a long-lived token and bind the
     * Instagram professional account. Official Meta Graph only.
     *
     * @return array{status: string, detail: string}
     */
    public function completeAuthorization(CreatorProfile $profile, string $code): array;

    /**
     * Never invent metrics. Return empty insights unless a live API response exists.
     *
     * @return array{status: string, insights: array<string, mixed>, detail: string}
     */
    public function syncPermittedData(CreatorProfile $profile): array;
}
