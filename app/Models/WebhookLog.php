<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = [
        'provider',
        'event_type',
        'provider_event_id',
        'signature_status',
        'processing_status',
        'payload',
        'request_id',
        'error_message',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
