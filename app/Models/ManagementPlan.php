<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManagementPlan extends Model
{
    protected $fillable = ['name', 'slug', 'price_minor', 'currency', 'features', 'is_active'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
