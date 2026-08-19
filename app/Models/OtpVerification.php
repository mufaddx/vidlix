<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    protected $fillable = [
        'identifier', 'channel', 'purpose', 'code_hash',
        'attempts', 'expires_at', 'consumed_at', 'request_ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /** The code is never readable, not even to the application. */
    protected $hidden = ['code_hash'];
}
