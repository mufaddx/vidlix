<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $fillable = ['identifier', 'ip_address', 'successful'];

    protected function casts(): array
    {
        return ['successful' => 'boolean'];
    }
}
