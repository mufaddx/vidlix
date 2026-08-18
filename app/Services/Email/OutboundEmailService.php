<?php

namespace App\Services\Email;

use App\Contracts\EmailProviderInterface;
use App\Jobs\SendThreadReplyEmail;
use App\Models\Conversation;
use App\Models\EmailEvent;
use App\Models\Message;

/**
 * Outbound thread replies.
 *
 * The message row is stored first and always. Its delivery_status only ever
 * reflects what the provider actually told us: nothing reads "sent" because a
 * form was submitted.
 */
class OutboundEmailService
{
    public function __construct(private EmailProviderInterface $provider) {}

    /** reply+<routing_token>@<inbound_domain>, the address inbound routing depends on. */
    public function replyAddressFor(Conversation $conversation): ?string
    {
        $domain = (string) config('vidlix.email.inbound_domain');
        if ($domain === '' || ! filled($conversation->routing_token)) {
            return null;
        }

        $prefix = (string) config('vidlix.email.reply_prefix', 'reply');

        return $prefix.'+'.$conversation->routing_token.'@'.$domain;
    }

    /** Status a freshly stored outbound message should carry before any send is attempted. */
    public function initialStatus(): string
    {
        return $this->provider->isConfigured() ? 'queued' : 'provider_not_configured';
    }

    public function queue(Message $message): void
    {
        if (! $this->provider->isConfigured()) {
            $this->record($message, 'provider_not_configured', null, 'No email provider is configured, so nothing was sent.');

            return;
        }

        SendThreadReplyEmail::dispatch((int) $message->getKey());
    }

    /**
     * @return array{status: string, detail: string}
     */
    public function send(Message $message): array
    {
        $conversation = $message->conversation()->with('externalContact')->first();
        $toEmail = $conversation?->externalContact?->email;

        if (! filled($toEmail)) {
            $this->record($message, 'no_recipient', null, 'The conversation has no external email address to reply to.');

            return ['status' => 'no_recipient', 'detail' => 'No external recipient on this conversation.'];
        }

        $replyTo = $this->replyAddressFor($conversation)
            ?? (string) config('vidlix.email.from_address');

        $result = $this->provider->sendThreadReply($message, (string) $toEmail, $replyTo);
        $this->record($message, $result['status'], $result['provider_message_id'], $result['detail']);

        return ['status' => $result['status'], 'detail' => $result['detail']];
    }

    private function record(Message $message, string $status, ?string $providerMessageId, string $detail): void
    {
        EmailEvent::query()->create([
            'message_id' => $message->getKey(),
            'direction' => 'outbound',
            'status' => $status,
            'provider' => $this->provider->name(),
            'provider_message_id' => $providerMessageId,
            'detail' => $detail,
        ]);

        $message->forceFill(array_filter([
            'delivery_status' => $status,
            'provider_message_id' => $providerMessageId ?: $message->provider_message_id,
        ]))->save();
    }
}
