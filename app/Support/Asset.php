<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * A URL for a hand-written asset that changes when the file does.
 *
 * `asset('css/app.css')` returns the same URL forever, so a returning visitor
 * keeps whatever their browser cached the first time. After the interface was
 * rebuilt, people who had visited before carried on seeing the old stylesheet -
 * the site had changed and they could not tell.
 *
 * Vite solves this for bundled assets by hashing the filename. This project
 * serves CSS and JS straight out of public/, so the version comes from the
 * file's own modification time.
 */
final class Asset
{
    public static function url(string $path): string
    {
        return asset($path).'?v='.self::version($path);
    }

    /**
     * The stat call is cheap, but it happens on every page for every asset, so
     * it is remembered for a few minutes. A deploy changes the file and the
     * next cache miss picks the new value up.
     */
    private static function version(string $path): string
    {
        return (string) Cache::remember(
            'asset.version.'.$path,
            300,
            function () use ($path) {
                $full = public_path($path);

                return is_file($full) ? (string) filemtime($full) : '0';
            },
        );
    }
}
