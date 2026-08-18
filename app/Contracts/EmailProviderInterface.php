<?php

namespace App\Contracts;

use App\Models\Message;

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
    public function sendThreadReply(Message $message, string $toEmail, string $replyTo): array;
}
