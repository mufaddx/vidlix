<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by_user_id'];

    protected function casts(): array
    {
        // The column is json. Without this cast a plain string like a
        // maintenance message is written raw and MySQL rejects it as invalid
        // json - SQLite would have accepted it, so the failure would only have
        // appeared in production.
        return ['value' => 'json'];
    }
}
