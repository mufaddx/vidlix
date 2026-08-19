<?php

namespace App\Services\Integrations\Email;

use App\Contracts\EmailProviderInterface;
use App\Models\Message;
use App\Services\Email\OutboundIdentity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resend (https://resend.com).
 *
 * A 200 means Resend accepted the message and returned an id. That is
 * acceptance, not delivery — only the `email.delivered` webhook may move a
 * message to delivered.
 */
class ResendEmailProvider implements EmailProviderInterface
{
    public function name(): string
    {
        return 'resend';
    }

    public function isConfigured(): bool
    {
        return filled(config('vidlix.email.api_key')) && filled(config('vidlix.email.from_address'));
    }

    public function sendThreadReply(Message $message, string $toEmail, OutboundIdentity $identity): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'provider_not_configured',
                'provider_message_id' => null,
                'detail' => 'EMAIL_API_KEY / MAIL_FROM_ADDRESS are missing. The message is stored but not sent.',
            ];
        }

        $conversation = $message->conversation;

        $payload = [
            'from' => $identity->fromName !== ''
                ? $identity->fromName.' <'.$identity->fromAddress.'>'
                : $identity->fromAddress,
            'to' => [$toEmail],
            'subject' => filled($conversation?->subject) ? (string) $conversation->subject : 'Vidlix message',
            'text' => (string) $message->body,
            'reply_to' => [$identity->replyTo],
            // Tags survive into the webhook, so a delivery event can be tied back
            // to our own row without depending on the provider id alone.
            'tags' => [
                ['name' => 'vidlix_message_id', 'value' => (string) $message->getKey()],
            ],
        ];

        if (filled($message->in_reply_to)) {
            $payload['headers'] = [
                'In-Reply-To' => (string) $message->in_reply_to,
                'References' => (string) $message->in_reply_to,
            ];
        }

        try {
            $response = Http::withToken((string) config('vidlix.email.api_key'))
                ->baseUrl(rtrim((string) config('vidlix.email.api_base') ?: 'https://api.resend.com', '/'))
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('vidlix.email.timeout', 20))
                ->post('/emails', $payload);
        } catch (Throwable $e) {
            Log::warning('resend.send.transport_failure', ['message' => $e->getMessage()]);

            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'detail' => 'Resend could not be reached. Nothing was sent.',
            ];
        }

        if (! $response->successful()) {
            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'detail' => 'Resend returned '.$response->status().': '.$this->errorText($response->json()),
            ];
        }

        return [
            'status' => 'accepted',
            'provider_message_id' => $response->json('id'),
            'detail' => 'Resend accepted the message for delivery. Delivery is confirmed by the event webhook only.',
        ];
    }

    public function sendSystemMail(string $toEmail, string $subject, string $body, OutboundIdentity $identity): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'provider_not_configured',
                'provider_message_id' => null,
                'detail' => 'EMAIL_API_KEY / MAIL_FROM_ADDRESS are missing. Nothing was sent.',
            ];
        }

        return $this->post([
            'from' => $identity->fromName !== ''
                ? $identity->fromName.' <'.$identity->fromAddress.'>'
                : $identity->fromAddress,
            'to' => [$toEmail],
            'subject' => $subject,
            'text' => $body,
            'reply_to' => [$identity->replyTo],
        ]);
    }

    /**
     * @return array{status: string, provider_message_id: ?string, detail: string}
     */
    private function post(array $payload): array
    {
        try {
            $response = Http::withToken((string) config('vidlix.email.api_key'))
                ->baseUrl(rtrim((string) config('vidlix.email.api_base') ?: 'https://api.resend.com', '/'))
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('vidlix.email.timeout', 20))
                ->post('/emails', $payload);
        } catch (Throwable $e) {
            Log::warning('resend.send.transport_failure', ['message' => $e->getMessage()]);

            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'detail' => 'Resend could not be reached. Nothing was sent.',
            ];
        }

        if (! $response->successful()) {
            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'detail' => 'Resend returned '.$response->status().': '.$this->errorText($response->json()),
            ];
        }

        return [
            'status' => 'accepted',
            'provider_message_id' => $response->json('id'),
            'detail' => 'Resend accepted the message for delivery. Delivery is confirmed by the event webhook only.',
        ];
    }

    private function errorText(mixed $json): string
    {
        if (is_array($json) && isset($json['message'])) {
            return (string) $json['message'];
        }

        return 'unspecified provider error';
    }
}
