<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Automation extends Model
{
    protected $fillable = ['creator_profile_id', 'name', 'status', 'config'];

    protected function casts(): array
    {
        return ['config' => 'array'];
    }
}
