<?php

namespace App\Services\Integrations;

use App\Contracts\EmailProviderInterface;
use App\Models\Message;
use App\Services\Email\OutboundIdentity;

class UnconfiguredEmailProvider implements EmailProviderInterface
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'unconfigured';
    }

    public function sendThreadReply(Message $message, string $toEmail, OutboundIdentity $identity): array
    {
        return [
            'status' => 'provider_not_configured',
            'provider_message_id' => null,
            'detail' => 'Outbound email is queued until EMAIL_PROVIDER credentials are configured. The message is stored.',
        ];
    }
}
