<?php

namespace App\Services\Webhooks;

use App\Models\WebhookLog;
use App\Services\Email\DeliveryEventHandler;
use App\Services\Email\InboundEmailIngestor;
use App\Services\Email\InboundEmailNormalizer;
use App\Services\Instagram\MetaEventHandler;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Runs the side effect of a webhook, but only for a log row whose signature
 * verified. Anything rejected or replayed leaves this class doing nothing.
 */
class WebhookDispatcher
{
    public function __construct(
        private PaymentSettlementService $settlement,
        private InboundEmailNormalizer $normalizer,
        private InboundEmailIngestor $ingestor,
        private DeliveryEventHandler $deliveryEvents,
        private MetaEventHandler $meta,
    ) {}

    /**
     * @return array{status: string, detail: string}
     */
    public function afterAccepted(WebhookLog $log, Request $request): array
    {
        if ($log->processing_status !== 'accepted') {
            return ['status' => $log->processing_status, 'detail' => 'No side effect ran for this event.'];
        }

        $payload = is_array($log->payload) ? $log->payload : [];

        $result = match ($log->provider) {
            'payment' => $this->settlement->settlePaymentEvent($payload, $log->provider_event_id, $log->event_type),
            'payout' => $this->settlement->settlePayoutEvent($payload, $log->provider_event_id, $log->event_type),
            'email_inbound' => $this->handleInboundEmail($request),
            'email_events' => $this->handleDeliveryEvent($payload),
            'meta' => $this->meta->handle($payload),
            default => ['status' => 'ignored', 'detail' => 'Unknown webhook provider.'],
        };

        Log::info('webhook.dispatched', [
            'provider' => $log->provider,
            'event_type' => $log->event_type,
            'outcome' => $result['status'],
            'request_id' => $log->request_id,
        ]);

        $log->forceFill(['error_message' => $result['status'] === 'settled' ? null : $result['detail']])->save();

        return $result;
    }

    /** Only the inbound endpoint may create conversations or messages. */
    private function handleInboundEmail(Request $request): array
    {
        $result = $this->ingestor->ingest($this->normalizer->normalize($request));

        return ['status' => $result['status'], 'detail' => $result['detail']];
    }

    /**
     * The events endpoint only updates delivery status. Providers let you
     * subscribe an endpoint to unrelated event families (Resend will happily
     * send contact.* here), and those must be ignored outright — routing them
     * into the inbound path would fill the operator triage queue in
     * inbound_email_events with rows that are not mail at all.
     */
    private function handleDeliveryEvent(array $payload): array
    {
        $handled = $this->deliveryEvents->handle($payload);

        return ['status' => $handled['status'], 'detail' => $handled['detail']];
    }
}
