<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    protected $fillable = ['slug', 'bps', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
