<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One comment Instagram told us about. */
class AutodmEvent extends Model
{
    protected $fillable = [
        'provider_event_id', 'instagram_account_id', 'media_id', 'comment_id',
        'commenter_id', 'comment_text', 'status',
    ];
}
