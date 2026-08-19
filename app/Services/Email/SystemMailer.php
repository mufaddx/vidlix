<?php

namespace App\Services\Email;

use App\Contracts\EmailProviderInterface;
use App\Models\EmailEvent;

/**
 * Transactional mail from Vidlix itself.
 *
 * Sign-in codes, deal confirmations, receipts — anything the platform says on
 * its own behalf rather than on a person's. These carry the noreply identity
 * precisely because there is no conversation behind them: a reply would arrive
 * nowhere useful, and inventing a routing token for a one-way notice would be a
 * lie about where the reply goes.
 *
 * Person-to-person threads must never come through here. They go through
 * OutboundEmailService, which sends from creator@ / editor@ with a real
 * Reply-To, so the recipient can actually answer the human who wrote to them.
 */
class SystemMailer
{
    public function __construct(private EmailProviderInterface $provider) {}

    public function identity(): OutboundIdentity
    {
        $domain = (string) config('vidlix.email.inbound_domain');
        $prefix = (string) config('vidlix.email.system_prefix', 'noreply');
        $address = $domain !== ''
            ? $prefix.'@'.$domain
            : (string) config('vidlix.email.from_address');

        return new OutboundIdentity(
            fromAddress: $address,
            fromName: (string) config('vidlix.email.from_name', 'Vidlix'),
            replyTo: $address,
        );
    }

    /**
     * @return array{status: string, detail: string}
     */
    public function send(string $toEmail, string $subject, string $body): array
    {
        $result = $this->provider->sendSystemMail($toEmail, $subject, $body, $this->identity());

        // Recorded with no message_id: this belongs to no conversation, and
        // attaching it to one would misfile it in somebody's thread.
        EmailEvent::query()->create([
            'message_id' => null,
            'direction' => 'system',
            'status' => $result['status'],
            'provider' => $this->provider->name(),
            'provider_message_id' => $result['provider_message_id'],
            'detail' => $result['detail'],
        ]);

        return ['status' => $result['status'], 'detail' => $result['detail']];
    }
}
