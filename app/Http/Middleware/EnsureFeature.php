<?php

namespace App\Http\Middleware;

use App\Services\Platform\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a route behind a feature switch.
 *
 * Applied at the route rather than inside each controller, so the answer to
 * "what does this switch actually turn off?" is readable in one file.
 */
class EnsureFeature
{
    public function __construct(private Features $features) {}

    public function handle(Request $request, Closure $next, string $flag): Response
    {
        if ($this->features->enabled($flag, $request->user())) {
            return $next($request);
        }

        $message = __('This is temporarily unavailable. Nothing you have already done is affected.');

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'code' => 'FEATURE_DISABLED', 'message' => $message], 503);
        }

        return response()->view('errors.feature-off', ['message' => $message], 503);
    }
}
