<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    protected $fillable = [
        'payment_uuid', 'status', 'amount_minor', 'currency', 'provider',
        'provider_payment_id', 'checkout_url', 'confirmed_at', 'last_provider_detail',
        'payer_user_id', 'payable_type', 'payable_id',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
