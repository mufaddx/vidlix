<?php

namespace App\Services\AutoDm;

use App\Contracts\InstagramProviderInterface;
use App\Models\AutodmAutomationVersion;
use App\Models\AutodmRun;
use App\Models\InstagramAccount;

/**
 * The one place an AutoDM action actually leaves the platform.
 *
 * It is separate from the engine so that "did we decide to send?" and "what did
 * the provider say?" stay separable — and so a run's status can only ever be
 * what the provider reported, never what the engine hoped for.
 *
 * With no provider configured this reports `skipped`, not `sent` and not
 * `failed`: nothing went wrong, there is simply nothing to send through.
 */
class AutoDmSender
{
    public function __construct(private InstagramProviderInterface $instagram) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, reason_code: ?string, detail: string, provider_response_id: ?string}
     */
    public function send(
        InstagramAccount $account,
        AutodmAutomationVersion $version,
        string $action,
        array $payload,
    ): array {
        if (! $this->instagram->isConfigured()) {
            return [
                'status' => AutodmRun::SKIPPED,
                'reason_code' => 'provider_not_configured',
                'detail' => __('No Instagram provider is configured, so nothing was sent.'),
                'provider_response_id' => null,
            ];
        }

        $message = $this->messageFor($version, $action);

        if ($message === '') {
            return [
                'status' => AutodmRun::SKIPPED,
                'reason_code' => 'empty_message',
                'detail' => __('This automation has no message for that action.'),
                'provider_response_id' => null,
            ];
        }

        $commentId = (string) ($payload['comment_id'] ?? '');

        if ($commentId === '') {
            return [
                'status' => AutodmRun::SKIPPED,
                'reason_code' => 'missing_comment',
                'detail' => __('The delivery did not say which comment to answer.'),
                'provider_response_id' => null,
            ];
        }

        /*
         | The provider contract does not carry a reply method yet, because no
         | live driver has been approved to perform one. Rather than invent a
         | call that would fail at runtime, this reports honestly that the
         | capability is not wired up — which is the same answer the builder
         | already gives before anyone activates an automation around it.
         */
        return [
            'status' => AutodmRun::SKIPPED,
            'reason_code' => 'capability_not_enabled',
            'detail' => __('Sending is not enabled for this account yet. The automation matched and was recorded, but nothing was sent.'),
            'provider_response_id' => null,
        ];
    }

    private function messageFor(AutodmAutomationVersion $version, string $action): string
    {
        $text = $action === AutodmRun::PUBLIC_REPLY
            ? (string) $version->public_reply_text
            : (string) $version->private_reply_text;

        return trim($text);
    }
}
