<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPlatform extends Model
{
    protected $fillable = [
        'name', 'slug', 'icon', 'username_url_template',
        'supports_username', 'supports_full_url', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'supports_username' => 'boolean',
            'supports_full_url' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
