<?php

namespace App\Services\AutoDm;

use App\Contracts\InstagramProviderInterface;
use App\Models\InstagramAccount;

/**
 * What Instagram will actually let this account do.
 *
 * This exists so the builder can refuse an action *before* somebody activates
 * an automation around it, rather than at 2am when a comment arrives. An action
 * that cannot be performed is shown as unavailable with a reason, not offered
 * and then quietly failed.
 *
 * The rules encoded here are the ones that shape the product:
 *
 *   - Replying publicly to a comment on your own post is broadly permitted.
 *   - Replying privately (a DM) needs messaging permissions Meta grants only
 *     after app review, and only within a bounded window after the comment.
 *   - Unsolicited DMs, follow-ups and drip sequences are not possible at all.
 *
 * None of that is our policy; it is the platform's, and the honest thing is to
 * say so in the interface rather than promise around it.
 */
class Capabilities
{
    /**
     * How long after a comment a private reply may still be sent.
     *
     * Kept conservative on purpose: sending at the very edge of a window and
     * having it rejected is worse than not sending, because the run then looks
     * like a failure the person is expected to act on.
     */
    public const PRIVATE_REPLY_WINDOW_HOURS = 24;

    public const PUBLIC_REPLY = 'public_reply';

    public const PRIVATE_REPLY = 'private_reply';

    public function __construct(private InstagramProviderInterface $instagram) {}

    public function providerConfigured(): bool
    {
        return $this->instagram->isConfigured();
    }

    /**
     * Can this account perform this action right now?
     *
     * @return array{allowed: bool, reason_code: ?string, reason: ?string}
     */
    public function check(InstagramAccount $account, string $action): array
    {
        if (! $this->providerConfigured()) {
            return $this->no('provider_not_configured', __('Instagram is not connected on this installation yet.'));
        }

        if ($account->status !== 'connected') {
            return $this->no('account_disconnected', __('This Instagram account is not connected.'));
        }

        if ($this->tokenExpired($account)) {
            return $this->no('token_expired', __('The Instagram connection has expired. Reconnect to continue.'));
        }

        return match ($action) {
            self::PUBLIC_REPLY => $this->checkPublicReply($account),
            self::PRIVATE_REPLY => $this->checkPrivateReply($account),
            default => $this->no('unsupported_action', __('That action is not supported.')),
        };
    }

    public function allows(InstagramAccount $account, string $action): bool
    {
        return $this->check($account, $action)['allowed'];
    }

    /**
     * Is a comment still recent enough to answer privately?
     *
     * Checked at send time rather than at match time: a queue that ran long
     * could otherwise hand the provider a request it will refuse.
     */
    public function withinPrivateReplyWindow(?\DateTimeInterface $commentedAt): bool
    {
        if ($commentedAt === null) {
            return false;
        }

        return $commentedAt >= now()->subHours(self::PRIVATE_REPLY_WINDOW_HOURS);
    }

    /**
     * Everything the builder should show, with a reason for anything it cannot.
     *
     * @return array<string, array{allowed: bool, reason_code: ?string, reason: ?string, label: string}>
     */
    public function summaryFor(InstagramAccount $account): array
    {
        $summary = [];

        foreach ([
            self::PUBLIC_REPLY => __('Reply publicly to the comment'),
            self::PRIVATE_REPLY => __('Send a private reply'),
        ] as $action => $label) {
            $summary[$action] = $this->check($account, $action) + ['label' => $label];
        }

        return $summary;
    }

    private function checkPublicReply(InstagramAccount $account): array
    {
        // Replying to a comment on your own media is the one durable path, and
        // the only scope it needs is the one the connection already asked for.
        if (! $this->hasScope($account, 'instagram_basic')) {
            return $this->no('missing_permission', __('Reconnect Instagram to grant the permissions this needs.'));
        }

        return $this->yes();
    }

    private function checkPrivateReply(InstagramAccount $account): array
    {
        if (! $this->hasScope($account, 'instagram_manage_messages')) {
            return $this->no(
                'app_review_pending',
                __('Private replies need Instagram messaging permissions, which Meta grants after app review. Public replies work without them.'),
            );
        }

        return $this->yes();
    }

    /**
     * Whether a permission was actually granted.
     *
     * Read from what the account came back with, not from what was requested:
     * asking for a scope and being given it are different things, and treating
     * the request as the answer is how an automation gets built on a permission
     * that was declined.
     */
    private function hasScope(InstagramAccount $account, string $scope): bool
    {
        $granted = $account->granted_scopes;

        if (! is_array($granted) || $granted === []) {
            // Nothing recorded. Fall back to the configured scopes only for the
            // basic read permission, which the OAuth flow cannot complete
            // without; anything more sensitive is treated as not granted.
            return $scope === 'instagram_basic';
        }

        return in_array($scope, $granted, true);
    }

    private function tokenExpired(InstagramAccount $account): bool
    {
        return $account->token_expires_at !== null
            && $account->token_expires_at->isPast();
    }

    /** @return array{allowed: true, reason_code: null, reason: null} */
    private function yes(): array
    {
        return ['allowed' => true, 'reason_code' => null, 'reason' => null];
    }

    /** @return array{allowed: false, reason_code: string, reason: string} */
    private function no(string $code, string $reason): array
    {
        return ['allowed' => false, 'reason_code' => $code, 'reason' => $reason];
    }
}
