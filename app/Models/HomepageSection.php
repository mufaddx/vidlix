<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageSection extends Model
{
    protected $fillable = ['key', 'title', 'subtitle', 'payload', 'is_visible', 'sort_order'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_visible' => 'boolean',
        ];
    }
}
