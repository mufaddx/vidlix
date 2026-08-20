<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The per-request nonce that lets one trusted inline script run.
 *
 * The policy is `script-src 'self'`, which blocks every inline block. Almost
 * all of our JavaScript can live in a file and be served from 'self' - but the
 * theme bootstrap cannot: it has to run before first paint, or a returning
 * visitor sees a flash of the wrong theme before their choice is applied. A
 * nonce lets exactly that one block through without opening the door to
 * 'unsafe-inline', which would let any injected script run too.
 */
final class Csp
{
    private static ?string $nonce = null;

    /** The same value for the whole request, and a new one for the next. */
    public static function nonce(): string
    {
        return self::$nonce ??= Str::random(24);
    }

    /** Test helper: forget the value so each request starts clean. */
    public static function reset(): void
    {
        self::$nonce = null;
    }
}
