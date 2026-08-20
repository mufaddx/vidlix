<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One public handle, and what it points at.
 *
 * This is the authority for vidlix.in/{username}. The username columns on the
 * profile tables are still written, but resolution goes through here, because
 * only here is the name unique across creators and editors alike.
 */
class Username extends Model
{
    public const ACTIVE = 'active';

    public const RESERVED = 'reserved';

    public const RETIRED = 'retired';

    protected $fillable = [
        'username', 'user_id', 'profile_type', 'profile_id', 'status', 'released_at',
    ];

    protected function casts(): array
    {
        return ['released_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
