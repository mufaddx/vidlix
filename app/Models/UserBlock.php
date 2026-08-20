<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person choosing not to hear from another. */
class UserBlock extends Model
{
    protected $fillable = ['user_id', 'blocked_user_id', 'reason'];

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_user_id');
    }
}
