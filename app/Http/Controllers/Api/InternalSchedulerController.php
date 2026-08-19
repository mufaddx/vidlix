<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * HTTP trigger for the scheduler, for hosts with no cron.
 *
 * Hostinger's cheaper shared plans expose no crontab, no cron spool and no
 * systemd timers, so an external scheduling service calls this endpoint instead.
 * It is authenticated by a shared secret in a header — never a query string,
 * which would land the secret in access logs and referrers.
 *
 * With no token configured the route 404s, so an unconfigured deployment does
 * not expose a way to make the server do work.
 */
class InternalSchedulerController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        $expected = (string) config('vidlix.cron.token');

        // Behave exactly as if the route did not exist until a token is set.
        if ($expected === '') {
            abort(404);
        }

        $presented = (string) $request->header('X-Cron-Token', '');
        if ($presented === '' || ! hash_equals($expected, $presented)) {
            Log::warning('scheduler.trigger.rejected', ['ip' => $request->ip()]);

            abort(404);
        }

        // schedule:run dispatches the queue worker in the background, so this
        // request returns immediately rather than holding a PHP worker open.
        Artisan::call('schedule:run');

        Log::info('scheduler.trigger.ran', ['ip' => $request->ip()]);

        return response()->json([
            'success' => true,
            'code' => 'SCHEDULER_RAN',
            'ran_at' => now()->toIso8601String(),
            'request_id' => $request->attributes->get('request_id'),
        ]);
    }
}
