<?php

namespace App\Services\Email;

use App\Models\EmailEvent;
use App\Models\Message;

/**
 * Provider delivery/bounce events. This is the only place a message may become
 * "delivered" — accepting a message for delivery is not delivering it.
 */
class DeliveryEventHandler
{
    /**
     * @return array{status: string, handled: int, detail: string}
     */
    public function handle(array $payload): array
    {
        $events = $this->flatten($payload);
        $handled = 0;

        foreach ($events as $event) {
            $status = $this->normalizeStatus((string) ($event['event'] ?? $event['RecordType'] ?? $event['type'] ?? ''));
            if ($status === null) {
                continue;
            }

            $message = $this->resolveMessage($event);
            if (! $message) {
                continue;
            }

            EmailEvent::query()->create([
                'message_id' => $message->getKey(),
                'direction' => 'outbound',
                'status' => $status,
                'provider' => (string) config('vidlix.providers.email'),
                'provider_message_id' => $this->providerMessageId($event),
                'detail' => (string) ($event['reason'] ?? $event['Description'] ?? 'Provider delivery event.'),
            ]);

            // Never downgrade a terminal failure back to delivered.
            if (! in_array($message->delivery_status, ['bounced', 'complained'], true)) {
                $message->forceFill(['delivery_status' => $status])->save();
            }
            $handled++;
        }

        return [
            'status' => $handled > 0 ? 'handled' : 'no_matching_messages',
            'handled' => $handled,
            'detail' => $handled.' delivery event(s) applied.',
        ];
    }

    /** SendGrid posts an array of events; Postmark posts a single object. */
    private function flatten(array $payload): array
    {
        if (array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        return [$payload];
    }

    private function normalizeStatus(string $raw): ?string
    {
        return match (strtolower($raw)) {
            'delivered', 'delivery', 'email.delivered' => 'delivered',
            'bounce', 'blocked', 'dropped', 'email.bounced' => 'bounced',
            'spamreport', 'spamcomplaint', 'email.complained' => 'complained',
            'deferred', 'email.delivery_delayed' => 'deferred',
            'processed', 'email.sent' => 'accepted',
            default => null,
        };
    }

    private function providerMessageId(array $event): ?string
    {
        // Resend nests the id under data.email_id; the others keep it flat.
        foreach (['sg_message_id', 'MessageID', 'message_id', 'provider_message_id', 'data.email_id', 'email_id'] as $key) {
            $value = data_get($event, $key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** Resend may send tags as a list of {name, value} pairs rather than a map. */
    private function resendTag(array $event): ?string
    {
        foreach ((array) data_get($event, 'data.tags', []) as $tag) {
            if (is_array($tag) && ($tag['name'] ?? null) === 'vidlix_message_id') {
                return (string) ($tag['value'] ?? '');
            }
        }

        return null;
    }

    private function resolveMessage(array $event): ?Message
    {
        // custom_args / Metadata carry our own id, which survives provider rewrites.
        $ourId = $event['vidlix_message_id']
            ?? data_get($event, 'Metadata.vidlix_message_id')
            ?? data_get($event, 'data.tags.vidlix_message_id')
            ?? $this->resendTag($event);
        if (filled($ourId)) {
            $message = Message::query()->find((int) $ourId);
            if ($message) {
                return $message;
            }
        }

        $providerId = $this->providerMessageId($event);
        if (! filled($providerId)) {
            return null;
        }

        // SendGrid appends a suffix to sg_message_id on events.
        $base = explode('.', (string) $providerId)[0];

        return Message::query()
            ->where('provider_message_id', $providerId)
            ->orWhere('provider_message_id', $base)
            ->first();
    }
}
