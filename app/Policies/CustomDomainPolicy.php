<?php

namespace App\Policies;

use App\Models\CustomDomain;
use App\Models\User;

class CustomDomainPolicy
{
    public function manage(User $user, CustomDomain $domain): bool
    {
        return $domain->user_id === $user->id;
    }
}
