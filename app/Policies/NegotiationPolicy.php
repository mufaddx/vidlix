<?php

namespace App\Policies;

use App\Models\Negotiation;
use App\Models\User;

/** Only the two sides of a negotiation can see or move it. */
class NegotiationPolicy
{
    public function view(User $user, Negotiation $negotiation): bool
    {
        return $negotiation->involves($user->id);
    }

    public function respond(User $user, Negotiation $negotiation): bool
    {
        return $negotiation->involves($user->id) && $negotiation->isOpen();
    }

    /** Cancelling is the opener's; the other side declines instead. */
    public function cancel(User $user, Negotiation $negotiation): bool
    {
        return $negotiation->initiator_user_id === $user->id && $negotiation->isOpen();
    }
}
