<?php

namespace App\Services\Email;

use App\Contracts\EmailProviderInterface;
use App\Jobs\SendThreadReplyEmail;
use App\Models\Conversation;
use App\Models\EmailEvent;
use App\Models\Message;
use App\Models\User;

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

    /**
     * <prefix>+<routing_token>@<inbound_domain>, the address inbound routing
     * depends on. The prefix follows the thread's scope, so a creator thread
     * replies to creator+… and an editor thread to editor+….
     */
    public function replyAddressFor(Conversation $conversation): ?string
    {
        $domain = (string) config('vidlix.email.inbound_domain');
        if ($domain === '' || ! filled($conversation->routing_token)) {
            return null;
        }

        return $this->prefixFor($conversation).'+'.$conversation->routing_token.'@'.$domain;
    }

    /**
     * Who the message appears to come from.
     *
     * The recipient is a brand who contacted a specific person, so they should
     * see that person's name and Instagram handle — not a generic address that
     * tells them nothing about which enquiry this answers.
     */
    public function identityFor(Conversation $conversation): OutboundIdentity
    {
        $scope = $this->prefixFor($conversation);
        $fallback = (string) config('vidlix.email.from_address');

        /*
         | A creator thread leaves from creator@, an editor thread from editor@,
         | support from help@. The addresses are configured rather than built
         | from the inbound domain, because the domain mail arrives on and the
         | domain it is sent from are allowed to differ — and only a configured
         | address has SPF and DKIM behind it.
         */
        $fromAddress = (string) (config('vidlix.email.identities.'.$scope) ?: $fallback);

        return new OutboundIdentity(
            fromAddress: $fromAddress,
            fromName: $this->displayNameFor($conversation),
            replyTo: $this->replyAddressFor($conversation) ?? $fallback,
        );
    }

    /** creator | editor | help, falling back to the neutral reply prefix. */
    private function prefixFor(Conversation $conversation): string
    {
        if ($conversation->channel === 'support' || $conversation->owner_scope === 'support') {
            return (string) config('vidlix.email.support_prefix', 'help');
        }

        $scope = $conversation->owner_scope;
        if ($scope === 'creator' || $scope === 'editor') {
            return $scope;
        }

        return $conversation->creator_profile_id
            ? 'creator'
            : (string) config('vidlix.email.reply_prefix', 'reply');
    }

    /** "Mursalim (@mursalim) via Vidlix" — name plus the handle they know. */
    private function displayNameFor(Conversation $conversation): string
    {
        $suffix = (string) config('vidlix.email.from_name', 'Vidlix');

        if ($conversation->channel === 'support' || $conversation->owner_scope === 'support') {
            return $suffix.' Support';
        }

        $profile = $conversation->creatorProfile;

        if ($profile === null && $conversation->owner_user_id) {
            $owner = User::query()->find($conversation->owner_user_id);
            $profile = $conversation->owner_scope === 'editor'
                ? $owner?->editorProfile
                : $owner?->creatorProfile;
        }

        if ($profile === null) {
            return $suffix;
        }

        $handle = $profile->instagramAccount?->username ?? $profile->username ?? null;
        $name = $profile->display_name ?: $suffix;

        return $handle ? $name.' (@'.$handle.') via '.$suffix : $name.' via '.$suffix;
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

        $result = $this->provider->sendThreadReply(
            $message,
            (string) $toEmail,
            $this->identityFor($conversation),
        );
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
