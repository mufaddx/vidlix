<?php

namespace App\Services\Domains;

use App\Contracts\CustomHostnameProviderInterface;
use App\Models\CustomDomain;
use App\Models\CustomDomainEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Connecting, verifying and retiring a tenant hostname.
 *
 * The rule the whole class exists to enforce: a domain is served only when DNS
 * points at us, ownership has been proved, and a certificate exists. Those are
 * three separate facts and the state machine keeps them separate, because
 * collapsing them into one boolean is how a domain gets published while
 * browsers are still refusing it.
 */
class CustomDomainService
{
    public function __construct(
        private CustomHostnameProviderInterface $provider,
        private AuditLogger $audit,
    ) {}

    public function isAvailable(): bool
    {
        return $this->provider->isConfigured();
    }

    public function providerName(): string
    {
        return $this->provider->name();
    }

    public function forUser(User $user, string $scope): ?CustomDomain
    {
        return CustomDomain::query()
            ->where('user_id', $user->id)
            ->where('owner_scope', $scope)
            ->whereNot('status', CustomDomain::DISCONNECTED)
            ->first();
    }

    /**
     * Claim a hostname. Nothing is served yet — this only records the intent
     * and hands back the DNS instructions.
     */
    public function connect(User $user, string $scope, string $hostname): CustomDomain
    {
        $host = Hostname::normalise($hostname);

        if ($reason = Hostname::refusalReason($host)) {
            throw ValidationException::withMessages(['hostname' => $reason]);
        }

        /*
         | Two tenants cannot hold one hostname. The check is here for the
         | message; the unique index is what actually enforces it, because two
         | requests can pass this check in the same instant.
         */
        $taken = CustomDomain::query()
            ->where('hostname', $host)
            ->whereNot('status', CustomDomain::DISCONNECTED)
            ->where(fn ($q) => $q->where('user_id', '!=', $user->id)->orWhere('owner_scope', '!=', $scope))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'hostname' => __('That domain is already connected to another account.'),
            ]);
        }

        return DB::transaction(function () use ($user, $scope, $host) {
            $existing = $this->forUser($user, $scope);

            if ($existing !== null) {
                $existing->update(['status' => CustomDomain::DISCONNECTED]);
                $this->record($existing, 'replaced', $existing->status, CustomDomain::DISCONNECTED);
            }

            $domain = CustomDomain::query()->create([
                'user_id' => $user->id,
                'owner_scope' => $scope,
                'hostname' => $host,
                'status' => CustomDomain::DNS_REQUIRED,
                // Fresh every time. Reusing a token would let whoever held the
                // domain previously replay an old proof of ownership.
                'verification_token' => 'vidlix-verify-'.Str::lower(Str::random(32)),
                'dns_target' => $this->provider->dnsTarget(),
                'provider' => $this->provider->name(),
            ]);

            $result = $this->provider->register($domain);

            $domain->update([
                'provider_hostname_id' => $result['provider_hostname_id'],
                'last_error' => $result['status'] === 'ok' ? null : $result['detail'],
            ]);

            $this->record($domain, 'connected', null, $domain->status, $result['detail']);
            $this->audit->record('custom_domain.connected', $domain, ['hostname' => $host], $user->id);

            return $domain->fresh();
        });
    }

    /**
     * Re-check where the domain stands, and move it along if it has earned it.
     *
     * Deliberately never skips a step. Even if the provider reports a
     * certificate, the domain does not go active until this method has also
     * seen DNS and ownership, because a provider answer is one source and the
     * status column is the thing routing trusts.
     */
    public function refresh(CustomDomain $domain): CustomDomain
    {
        $from = $domain->status;

        if (in_array($domain->status, [CustomDomain::DISCONNECTED, CustomDomain::SUSPENDED], true)) {
            return $domain;
        }

        // Re-checked every time, not just on connect: a hostname that resolved
        // publicly last week can be repointed at a private address today, and
        // that would make us a proxy into somebody's network.
        if (! Hostname::resolvesPublicly($domain->hostname)) {
            return $this->fail($domain, __('The domain does not resolve to a public address.'));
        }

        $state = $this->provider->status($domain);

        $domain->forceFill([
            'last_checked_at' => now(),
            'dns_verified_at' => $state['dns_ok'] ? ($domain->dns_verified_at ?? now()) : null,
            'ssl_issued_at' => $state['ssl_ok'] ? ($domain->ssl_issued_at ?? now()) : null,
            'last_error' => $state['status'] === 'ok' ? null : $state['detail'],
        ]);

        // Ownership rides on DNS here: publishing the CNAME we asked for is
        // itself the proof, because only whoever controls the zone can do it.
        if ($state['dns_ok']) {
            $domain->ownership_verified_at ??= now();
        }

        $domain->status = $this->deriveStatus($domain, $state);
        $domain->save();

        if ($domain->status !== $from) {
            $this->record($domain, 'refreshed', $from, $domain->status, $state['detail']);
        }

        return $domain;
    }

    public function disconnect(CustomDomain $domain, ?User $actor = null): void
    {
        $from = $domain->status;
        $result = $this->provider->release($domain);

        $domain->update([
            'status' => CustomDomain::DISCONNECTED,
            'last_error' => null,
        ]);

        $this->record($domain, 'disconnected', $from, CustomDomain::DISCONNECTED, $result['detail']);
        $this->audit->record('custom_domain.disconnected', $domain, [
            'hostname' => $domain->hostname,
        ], $actor?->id);
    }

    /**
     * The hostname a public request arrived on, if we serve it.
     *
     * Only ever returns an ACTIVE row. This is the single place routing asks,
     * so a domain that is halfway through setup cannot be reached by guessing
     * at a Host header.
     */
    public function resolveActive(string $hostname): ?CustomDomain
    {
        return CustomDomain::query()
            ->where('hostname', Hostname::normalise($hostname))
            ->where('status', CustomDomain::ACTIVE)
            ->first();
    }

    /** @param array{dns_ok: bool, ssl_ok: bool, status: string, detail: string} $state */
    private function deriveStatus(CustomDomain $domain, array $state): string
    {
        if (! $state['dns_ok']) {
            return CustomDomain::DNS_REQUIRED;
        }

        if ($domain->ownership_verified_at === null) {
            return CustomDomain::OWNERSHIP_PENDING;
        }

        if (! $state['ssl_ok']) {
            // DNS is right and the domain is proven, but a browser would still
            // refuse it. This is the state people most want to call active and
            // it is exactly the one that must not be.
            return CustomDomain::SSL_PROVISIONING;
        }

        return CustomDomain::ACTIVE;
    }

    private function fail(CustomDomain $domain, string $detail): CustomDomain
    {
        $from = $domain->status;

        $domain->update([
            'status' => CustomDomain::FAILED,
            'last_error' => $detail,
            'last_checked_at' => now(),
            // Cleared, not kept: whatever was proved before is no longer true.
            'dns_verified_at' => null,
            'ssl_issued_at' => null,
        ]);

        $this->record($domain, 'failed', $from, CustomDomain::FAILED, $detail);

        return $domain;
    }

    private function record(CustomDomain $domain, string $event, ?string $from, ?string $to, ?string $detail = null): void
    {
        CustomDomainEvent::query()->create([
            'custom_domain_id' => $domain->id,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'detail' => $detail,
        ]);
    }
}
