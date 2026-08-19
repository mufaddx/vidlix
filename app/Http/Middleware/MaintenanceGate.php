<?php

namespace App\Http\Middleware;

use App\Services\Platform\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the site to members while leaving staff in.
 *
 * Laravel's own `artisan down` locks out everybody including the people who
 * need to fix things, and needs shell access this host makes awkward. This
 * keeps the admin panel, sign-in and the webhook endpoints open, because a
 * provider confirming a payment must not be turned away — the money moved
 * whether the site is up or not, and a refused webhook is a lost confirmation.
 */
class MaintenanceGate
{
    public function __construct(private Features $features) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->features->isUnderMaintenance() || $this->isAlwaysOpen($request)) {
            return $next($request);
        }

        if ($request->user()?->employee()->exists()) {
            return $next($request);
        }

        $message = $this->features->maintenanceMessage();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'code' => 'MAINTENANCE',
                'message' => $message,
            ], 503);
        }

        return response()->view('errors.maintenance', ['message' => $message], 503);
    }

    private function isAlwaysOpen(Request $request): bool
    {
        return $request->is(
            'admin', 'admin/*',
            'login', 'logout', 'staff/*',
            'webhooks/*',
            'api/internal/*',
            'up',
        );
    }
}
