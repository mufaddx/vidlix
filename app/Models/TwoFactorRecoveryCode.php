<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwoFactorRecoveryCode extends Model
{
    protected $fillable = ['user_id', 'code_hash', 'used_at'];

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }
}
