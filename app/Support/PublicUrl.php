<?php

namespace App\Support;

/**
 * Addresses on the public site.
 *
 * The application is served from app.vidlix.in but a public profile lives on
 * vidlix.in, so these cannot be built with url() — that would hand somebody a
 * link to the wrong host. They are built from the configured site domain
 * instead, which is also what makes the copy-link button trustworthy.
 */
final class PublicUrl
{
    public static function profile(string $username): string
    {
        return self::site().'/'.ltrim($username, '/');
    }

    public static function contact(string $username): string
    {
        return self::profile($username).'/contact';
    }

    public static function site(): string
    {
        return rtrim((string) config('vidlix.domains.site', config('app.url')), '/');
    }
}
