<?php

namespace App\Services\Integrations\Email;

use App\Contracts\EmailProviderInterface;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SendGrid v3 mail send.
 *
 * A 202 means SendGrid accepted the message for delivery. It is reported as
 * "accepted", never as "delivered" — only the event webhook can say that.
 */
class SendGridEmailProvider implements EmailProviderInterface
{
    public function name(): string
    {
        return 'sendgrid';
    }

    public function isConfigured(): bool
    {
        return filled(config('vidlix.email.api_key')) && filled(config('vidlix.email.from_address'));
    }

    public function sendThreadReply(Message $message, string $toEmail, string $replyTo): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'provider_not_configured',
                'provider_message_id' => null,
                'detail' => 'EMAIL_API_KEY / MAIL_FROM_ADDRESS are missing. The message is stored but not sent.',
            ];
        }

        $conversation = $message->conversation;
        $subject = filled($conversation?->subject) ? (string) $conversation->subject : 'Vidlix message';

        $payload = [
            'personalizations' => [[
                'to' => [['email' => $toEmail]],
                'headers' => array_filter([
                    'In-Reply-To' => $message->in_reply_to,
                    'References' => $message->in_reply_to,
                ]),
            ]],
            'from' => [
                'email' => (string) config('vidlix.email.from_address'),
                'name' => (string) config('vidlix.email.from_name'),
            ],
            'reply_to' => ['email' => $replyTo],
            'subject' => $subject,
            'content' => [['type' => 'text/plain', 'value' => (string) $message->body]],
            'custom_args' => [
                'vidlix_message_id' => (string) $message->getKey(),
                'vidlix_conversation' => (string) ($conversation?->conversation_uuid ?? ''),
            ],
        ];

        try {
            $response = Http::withToken((string) config('vidlix.email.api_key'))
                ->baseUrl(rtrim((string) config('vidlix.email.api_base') ?: 'https://api.sendgrid.com/v3', '/'))
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('vidlix.email.timeout', 20))
                ->post('/mail/send', $payload);
        } catch (Throwable $e) {
            Log::warning('sendgrid.send.transport_failure', ['message' => $e->getMessage()]);

            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'detail' => 'SendGrid could not be reached. Nothing was sent.',
            ];
        }

        if (! $response->successful()) {
            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'detail' => 'SendGrid returned '.$response->status().'. Nothing was sent.',
            ];
        }

        return [
            'status' => 'accepted',
            'provider_message_id' => $response->header('X-Message-Id') ?: null,
            'detail' => 'SendGrid accepted the message for delivery. Delivery is confirmed by the event webhook only.',
        ];
    }
}
