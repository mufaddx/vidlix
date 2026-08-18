<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispute extends Model
{
    protected $fillable = [
        'dispute_uuid', 'disputable_type', 'disputable_id', 'opened_by',
        'reason', 'statement', 'status', 'resolution',
    ];

    public function evidence(): HasMany
    {
        return $this->hasMany(DisputeEvidence::class);
    }
}
