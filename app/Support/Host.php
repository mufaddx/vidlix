<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Which face of Vidlix a request arrived on.
 *
 * One application serves four hosts, and the host decides what a visitor may
 * see. Without this the whole product appears on every address: the AutoDM
 * domain shows a creator's inbox, the landing site shows a dashboard, and the
 * separation the four domains exist to create is cosmetic.
 *
 * Resolved from the configured domains rather than from a guess, so a request
 * on an unexpected host falls back to the public site rather than to whichever
 * face happened to be listed first.
 */
final class Host
{
    public const SITE = 'site';

    public const APP = 'app';

    public const AUTODM = 'autodm';

    public const ADMIN = 'admin';

    /** Set on the request so views and middleware agree on one answer. */
    public const ATTRIBUTE = 'vidlix_face';

    public static function of(Request $request): string
    {
        $known = $request->attributes->get(self::ATTRIBUTE);

        if (is_string($known)) {
            return $known;
        }

        return self::resolve((string) $request->getHost()) ?? self::SITE;
    }

    /**
     * Which face this hostname is, or null if it is none of them.
     *
     * Null is the important answer. Local development, tests and a staging box
     * all arrive on a hostname that is not one of the four, and the honest
     * response is "I do not know" rather than a guess — because the caller uses
     * it to decide whether host-based routing applies at all, and guessing
     * "site" there would make most of the application unreachable.
     */
    public static function resolve(string $host): ?string
    {
        $host = mb_strtolower(trim($host));

        foreach ([self::APP, self::AUTODM, self::ADMIN, self::SITE] as $face) {
            $configured = self::hostFor($face);

            if ($configured !== null && $host === $configured) {
                return $face;
            }
        }

        return null;
    }

    public static function hostFor(string $face): ?string
    {
        $url = (string) config('vidlix.domains.'.$face);
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? mb_strtolower($host) : null;
    }

    public static function urlFor(string $face, string $path = '/'): string
    {
        $base = rtrim((string) config('vidlix.domains.'.$face), '/');

        return $base.'/'.ltrim($path, '/');
    }

    /**
     * Are the four faces actually on separate hosts?
     *
     * When they are not — one domain on a staging box — host routing must not
     * fire, or most of the application becomes unreachable.
     */
    public static function isSingleHostEnvironment(): bool
    {
        $hosts = array_filter([
            self::hostFor(self::SITE),
            self::hostFor(self::APP),
            self::hostFor(self::AUTODM),
            self::hostFor(self::ADMIN),
        ]);

        return count(array_unique($hosts)) < 2;
    }
}
