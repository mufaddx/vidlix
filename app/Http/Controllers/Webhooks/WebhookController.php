<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function __construct(
        private WebhookProcessor $processor,
        private WebhookDispatcher $dispatcher,
    ) {}

    /** Meta verifies a subscription over GET and delivers events over POST. */
    public function meta(Request $request): JsonResponse|Response
    {
        if ($request->isMethod('get')) {
            $token = (string) config('vidlix.webhooks.meta_secret');
            $presented = (string) $request->query('hub_verify_token', '');
            if ($token === '' || ! hash_equals($token, $presented)) {
                return response()->json(['success' => false, 'code' => 'WEBHOOK_FORBIDDEN'], 403);
            }

            return response((string) $request->query('hub_challenge', ''), 200, ['Content-Type' => 'text/plain']);
        }

        return $this->handle('meta', $request);
    }

    public function email(Request $request): JsonResponse
    {
        return $this->handle('email', $request);
    }

    public function payment(Request $request): JsonResponse
    {
        return $this->handle('payment', $request);
    }

    public function payout(Request $request): JsonResponse
    {
        return $this->handle('payout', $request);
    }

    private function handle(string $provider, Request $request): JsonResponse
    {
        $log = $this->processor->ingest($provider, $request);
        $outcome = $this->dispatcher->afterAccepted($log, $request);

        return $this->respond($log, $outcome);
    }

    private function respond(WebhookLog $log, array $outcome): JsonResponse
    {
        $ok = in_array($log->processing_status, ['accepted', 'duplicate_ignored'], true);

        return response()->json([
            'success' => $ok,
            'code' => strtoupper($log->processing_status),
            'signature_status' => $log->signature_status,
            // What actually happened downstream. "accepted" is receipt, not settlement.
            'outcome' => $outcome['status'],
            'request_id' => request()->attributes->get('request_id'),
        ], $ok ? 200 : 401);
    }
}
