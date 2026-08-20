<?php

namespace App\Contracts;

use App\Models\CustomDomain;

/**
 * Whoever terminates TLS for a tenant hostname.
 *
 * Cloudflare for SaaS is the intended one, but the shape is deliberately
 * generic: what the platform needs to know is whether a hostname has been
 * accepted, whether DNS points at it, and whether a certificate exists — not
 * how any particular vendor words those answers.
 */
interface CustomHostnameProviderInterface
{
    public function name(): string;

    public function isConfigured(): bool;

    /** The CNAME value the owner should publish. */
    public function dnsTarget(): ?string;

    /**
     * Ask the provider to take this hostname on.
     *
     * @return array{status: string, provider_hostname_id: ?string, detail: string}
     */
    public function register(CustomDomain $domain): array;

    /**
     * What the provider currently believes about the hostname.
     *
     * @return array{dns_ok: bool, ssl_ok: bool, status: string, detail: string}
     */
    public function status(CustomDomain $domain): array;

    /** @return array{status: string, detail: string} */
    public function release(CustomDomain $domain): array;
}
