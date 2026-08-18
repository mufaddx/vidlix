<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManagerActivityLog extends Model
{
    protected $fillable = ['creator_user_id', 'manager_user_id', 'action', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
