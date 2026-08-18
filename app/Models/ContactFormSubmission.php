<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactFormSubmission extends Model
{
    protected $fillable = [
        'contact_form_id', 'contact_form_version_id', 'conversation_id', 'answers', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['answers' => 'array'];
    }
}
