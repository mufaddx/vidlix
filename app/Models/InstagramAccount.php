<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property list<string>|null $granted_scopes what Meta actually granted, which
 *                                             is not the same as what was asked for
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $authorized_at
 * @property Carbon|null $last_synced_at
 */
class InstagramAccount extends Model
{
    protected $fillable = [
        'creator_profile_id', 'status', 'ig_user_id', 'username',
        'authorized_at', 'last_synced_at', 'token_expires_at',
        'token_encrypted', 'granted_scopes', 'insights', 'insights_synced_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'authorized_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'granted_scopes' => 'array',
            'insights' => 'array',
            'insights_synced_at' => 'datetime',
        ];
    }

    protected $hidden = ['token_encrypted'];

    public function creatorProfile(): BelongsTo
    {
        return $this->belongsTo(CreatorProfile::class);
    }
}
