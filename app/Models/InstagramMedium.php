<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One post or reel, as metadata.
 *
 * No bytes are stored. Instagram serves the media from URLs that expire, and
 * copying the files would be pointless as well as a licence question nobody
 * asked us to answer — so a thumbnail that has gone stale shows as stale rather
 * than as a broken image pretending to be current.
 */
class InstagramMedium extends Model
{
    protected $table = 'instagram_media';

    protected $fillable = [
        'instagram_account_id', 'media_id', 'media_type', 'permalink',
        'thumbnail_url', 'caption_excerpt', 'published_at',
        'sync_status', 'sync_error', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(InstagramAccount::class, 'instagram_account_id');
    }
}
