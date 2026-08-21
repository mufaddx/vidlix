<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

/** An invoice is readable by the two parties named on it. */
class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $invoice->seller_user_id === $user->id
            || $invoice->buyer_user_id === $user->id;
    }
}
