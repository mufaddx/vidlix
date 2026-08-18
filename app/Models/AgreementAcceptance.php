<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgreementAcceptance extends Model
{
    protected $fillable = [
        'agreement_id', 'user_id', 'typed_name', 'ip_address', 'user_agent', 'accepted_at',
    ];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }
}
