<?php

namespace App\Services\Domains;

/**
 * Deciding whether a hostname is one we are willing to serve.
 *
 * Everything here is a refusal rather than a repair. A hostname that needs
 * fixing before it is safe is a hostname somebody chose carefully, and quietly
 * correcting it into something acceptable is how an internal name ends up
 * pointed at a public tenant.
 */
final class Hostname
{
    /**
     * Names that must never become a tenant hostname, because resolving one of
     * them inside our own network is what turns a domain feature into an SSRF.
     */
    private const FORBIDDEN_NAMES = [
        'localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback',
        'broadcasthost', 'metadata', 'metadata.google.internal', 'instance-data',
    ];

    private const FORBIDDEN_SUFFIXES = [
        '.localhost', '.local', '.internal', '.intranet', '.private',
        '.corp', '.home', '.lan', '.test', '.example', '.invalid', '.onion',
    ];

    /**
     * Lowercased, trailing dot removed, IDN converted to punycode.
     *
     * Punycode matters for more than tidiness: two visually identical unicode
     * hostnames are different strings, and comparing the displayed form would
     * let a lookalike slip past the uniqueness check.
     */
    public static function normalise(string $hostname): string
    {
        $host = mb_strtolower(trim($hostname));

        // Somebody pasting a URL rather than typing a hostname is a mistake
        // worth absorbing; anything after the host is not.
        if (str_contains($host, '://')) {
            $host = (string) parse_url($host, PHP_URL_HOST);
        }

        $host = explode('/', $host)[0];
        $host = explode('?', $host)[0];
        $host = explode(':', $host)[0];

        // The root label, stripped last: a pasted URL carries its trailing dot
        // inside the host, so removing it any earlier misses it.
        $host = rtrim($host, '.');

        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7f]/', $host) === 1) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (is_string($ascii) && $ascii !== '') {
                $host = $ascii;
            }
        }

        return $host;
    }

    /**
     * Why we will not take this hostname, or null if it is fine.
     *
     * Returns a reason rather than a boolean because every one of these is
     * something the person can act on, and "invalid domain" tells them nothing.
     */
    public static function refusalReason(string $hostname): ?string
    {
        $host = self::normalise($hostname);

        if ($host === '') {
            return __('Enter a domain.');
        }

        if (mb_strlen($host) > 253) {
            return __('That domain is too long.');
        }

        // A bare IP is never a domain somebody owns in the sense that matters,
        // and it is the shortest path to pointing us at a private address.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return __('Enter a domain name, not an IP address.');
        }

        if (! preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) {
            return __('That does not look like a domain name.');
        }

        if (in_array($host, self::FORBIDDEN_NAMES, true)) {
            return __('That hostname is not allowed.');
        }

        foreach (self::FORBIDDEN_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return __('Private and reserved domains cannot be connected.');
            }
        }

        // Our own hostnames. Letting a tenant claim one would mean a custom
        // domain that resolves to the application or the admin panel.
        foreach (self::ourOwnHostnames() as $ours) {
            if ($host === $ours || str_ends_with($host, '.'.$ours)) {
                return __('That domain already belongs to Vidlix.');
            }
        }

        return null;
    }

    public static function isAcceptable(string $hostname): bool
    {
        return self::refusalReason($hostname) === null;
    }

    /**
     * Does this hostname resolve to an address we are willing to talk to?
     *
     * Checked separately from the syntax rules and re-checked at verification
     * time, because a name that resolves publicly today can be repointed at
     * 127.0.0.1 tomorrow. Every resolved address must pass — one public answer
     * alongside a private one is still a private answer.
     */
    public static function resolvesPublicly(string $hostname): bool
    {
        $host = self::normalise($hostname);
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false || $records === []) {
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if ($ip === null) {
                continue;
            }

            if (! self::isPublicIp((string) $ip)) {
                return false;
            }
        }

        return true;
    }

    public static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /** @return list<string> */
    public static function ourOwnHostnames(): array
    {
        $hosts = [];

        foreach (['site', 'app', 'autodm', 'admin'] as $key) {
            $url = (string) config('vidlix.domains.'.$key);
            $host = parse_url($url, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                $hosts[] = mb_strtolower($host);
            }
        }

        return array_values(array_unique($hosts));
    }
}
