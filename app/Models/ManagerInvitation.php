<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManagerInvitation extends Model
{
    protected $fillable = [
        'creator_user_id', 'email', 'name', 'mobile', 'token', 'permissions', 'status', 'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'accepted_at' => 'datetime',
        ];
    }
}
