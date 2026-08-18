<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'request_id',
        'actor_user_id',
        'acting_for_creator_id',
        'action',
        'auditable_type',
        'auditable_id',
        'meta',
        'ip_address',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
