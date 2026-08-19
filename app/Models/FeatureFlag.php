<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $fillable = ['key', 'name', 'description', 'is_enabled', 'audience', 'updated_by_user_id'];

    /** Who a flag is open to once it is enabled. */
    public const AUDIENCES = ['everyone' => 'Everyone', 'staff' => 'Staff only'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }
}
