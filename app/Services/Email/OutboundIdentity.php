<?php

namespace App\Services\Email;

/**
 * Who an outbound thread reply appears to come from.
 *
 * A brand writing to a creator should see the creator's name and Instagram
 * handle, not a generic no-reply address — and creator threads leave from
 * creator@, editor threads from editor@, so a reply can be routed even if the
 * plus-address token is stripped somewhere along the way.
 */
final class OutboundIdentity
{
    public function __construct(
        public readonly string $fromAddress,
        public readonly string $fromName,
        public readonly string $replyTo,
    ) {}
}
