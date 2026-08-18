<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundEmailEvent extends Model
{
    protected $fillable = [
        'provider_event_id', 'conversation_id', 'match_status',
        'from_email', 'subject', 'raw_excerpt',
    ];
}
