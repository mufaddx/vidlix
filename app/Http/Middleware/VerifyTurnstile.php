<?php

namespace App\Http\Middleware;

use App\Services\Security\Turnstile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the Turnstile token on a public form before the controller sees it.
 */
class VerifyTurnstile
{
    public function __construct(private Turnstile $turnstile) {}

    public function handle(Request $request, Closure $next): Response
    {
        $result = $this->turnstile->verify(
            $request->input('cf-turnstile-response'),
            $request->ip(),
        );

        if ($result['passed']) {
            return $next($request);
        }

        // Deliberately vague to the sender: a bot told exactly why it failed
        // learns how to pass.
        throw ValidationException::withMessages([
            'form' => __('We could not verify that you are human. Please try again.'),
        ]);
    }
}
