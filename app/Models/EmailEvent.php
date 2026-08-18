<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailEvent extends Model
{
    protected $fillable = [
        'message_id', 'direction', 'status', 'provider', 'provider_message_id', 'detail',
    ];
}
