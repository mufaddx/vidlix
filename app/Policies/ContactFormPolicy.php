<?php

namespace App\Policies;

use App\Models\ContactForm;
use App\Models\User;

class ContactFormPolicy
{
    public function manage(User $user, ContactForm $form): bool
    {
        return $form->owner_user_id === $user->id;
    }
}
