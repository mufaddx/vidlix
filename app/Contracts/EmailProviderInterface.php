<?php

namespace App\Contracts;

use App\Models\Message;
use App\Services\Email\OutboundIdentity;

interface EmailProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * A 2xx from the provider means "accepted for delivery", not "delivered".
     * Delivery/bounce is only known from the provider event webhook.
     *
     * @return array{status: string, provider_message_id: ?string, detail: string}
     */
    public function sendThreadReply(Message $message, string $toEmail, OutboundIdentity $identity): array;

    /**
     * Transactional mail from Vidlix itself — sign-in codes, confirmations,
     * receipts. These are not part of a conversation, so nobody should reply to
     * them and they carry the noreply identity.
     *
     * @return array{status: string, provider_message_id: ?string, detail: string}
     */
    public function sendSystemMail(string $toEmail, string $subject, string $body, OutboundIdentity $identity): array;
}
