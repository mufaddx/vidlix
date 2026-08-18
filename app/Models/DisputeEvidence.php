<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisputeEvidence extends Model
{
    protected $fillable = ['dispute_id', 'label', 'reference'];
}
