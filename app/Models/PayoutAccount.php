<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutAccount extends Model
{
    protected $fillable = ['user_id', 'provider_beneficiary_ref', 'masked_account', 'status'];
}
