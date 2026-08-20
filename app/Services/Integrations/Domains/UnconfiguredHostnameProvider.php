<?php

namespace App\Services\Integrations\Domains;

use App\Contracts\CustomHostnameProviderInterface;
use App\Models\CustomDomain;

/**
 * What custom domains do when no provider is set up.
 *
 * It refuses clearly rather than pretending. A domain that appears to connect
 * and then serves nothing is worse than one that says up front that the feature
 * is not wired to anything yet — the person has already changed their DNS by
 * the time they find out.
 */
class UnconfiguredHostnameProvider implements CustomHostnameProviderInterface
{
    public function name(): string
    {
        return 'unconfigured';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function dnsTarget(): ?string
    {
        return null;
    }

    public function register(CustomDomain $domain): array
    {
        return [
            'status' => 'provider_not_configured',
            'provider_hostname_id' => null,
            'detail' => __('No custom-domain provider is configured, so nothing was requested.'),
        ];
    }

    public function status(CustomDomain $domain): array
    {
        return [
            'dns_ok' => false,
            'ssl_ok' => false,
            'status' => 'provider_not_configured',
            'detail' => __('No custom-domain provider is configured.'),
        ];
    }

    public function release(CustomDomain $domain): array
    {
        return [
            'status' => 'provider_not_configured',
            'detail' => __('Nothing to release: no provider is configured.'),
        ];
    }
}
