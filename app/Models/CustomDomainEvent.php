<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomDomainEvent extends Model
{
    protected $fillable = ['custom_domain_id', 'event', 'from_status', 'to_status', 'detail'];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(CustomDomain::class, 'custom_domain_id');
    }
}
