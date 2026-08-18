<?php

namespace App\Services\Webhooks;

use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookProcessor
{
    public function __construct(private SignatureVerifier $verifier) {}

    public function ingest(string $provider, Request $request, ?string $configuredSecret = null): WebhookLog
    {
        $secret = $configuredSecret ?? $this->verifier->secretFor($provider);
        $signatureStatus = $this->verifier->verify($provider, $request, $secret);
        $accepted = $signatureStatus === SignatureVerifier::VALID;
        $eventId = $this->providerEventId($provider, $request);

        return DB::transaction(function () use ($provider, $eventId, $signatureStatus, $accepted, $request) {
            if ($accepted && $eventId !== null) {
                $existing = WebhookLog::query()
                    ->where('provider', $provider)
                    ->where('provider_event_id', $eventId)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    // Keep the original audit row intact; report the replay without
                    // rewriting what actually happened the first time.
                    $existing->setAttribute('processing_status', 'duplicate_ignored');

                    return $existing;
                }
            }

            return WebhookLog::query()->create([
                'provider' => $provider,
                'event_type' => $this->eventType($request),
                // An unverified event must never occupy the unique id slot, or a
                // forged call could suppress the genuine webhook that follows it.
                'provider_event_id' => $accepted
                    ? ($eventId ?? 'none_'.uniqid('', true))
                    : 'rejected_'.uniqid('', true),
                'signature_status' => $signatureStatus,
                'processing_status' => $accepted ? 'accepted' : 'rejected',
                'payload' => $this->payload($request),
                'request_id' => $request->attributes->get('request_id'),
                'error_message' => $accepted ? null : $signatureStatus.($eventId ? ' (claimed event '.$eventId.')' : ''),
            ]);
        });
    }

    private function providerEventId(string $provider, Request $request): ?string
    {
        $candidates = match ($provider) {
            'payment', 'payout' => [
                $request->header('X-Razorpay-Event-Id'),
                $request->input('id'),
            ],
            'meta' => [
                $request->header('X-Provider-Event-Id'),
                $request->input('id'),
                // Meta does not send an event id, so an exact replay of the same
                // signed body is what we deduplicate on.
                hash('sha256', $request->getContent()),
            ],
            'email' => [
                $request->input('id'),
                $request->input('MessageID'),
                $request->input('sg_event_id'),
                $request->header('X-Provider-Event-Id'),
            ],
            default => [$request->input('id'), $request->header('X-Provider-Event-Id')],
        };

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function eventType(Request $request): ?string
    {
        foreach (['type', 'event', 'RecordType', 'object'] as $key) {
            $value = $request->input($key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** Form-encoded inbound parse posts are not JSON, so fall back to all(). */
    private function payload(Request $request): array
    {
        $decoded = json_decode((string) $request->getContent(), true);

        return is_array($decoded) ? $decoded : $request->all();
    }
}
