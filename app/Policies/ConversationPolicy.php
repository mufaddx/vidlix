<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * Who may read a thread.
 *
 * Ownership or participation, and nothing else. The help desk is excluded here
 * rather than filtered later, because a support thread has its own screen with
 * its own abilities and must not be reachable through a member's inbox.
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        if ($conversation->channel === 'support') {
            return false;
        }

        return $conversation->owner_user_id === $user->id
            || $conversation->participants()->where('user_id', $user->id)->exists();
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
