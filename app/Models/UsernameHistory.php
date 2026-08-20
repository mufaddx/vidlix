<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A name somebody used to hold, so an old link can still find them. */
class UsernameHistory extends Model
{
    protected $table = 'username_history';

    protected $fillable = ['username', 'user_id', 'profile_type', 'held_from', 'held_until'];

    protected function casts(): array
    {
        return ['held_from' => 'datetime', 'held_until' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
