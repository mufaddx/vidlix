<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Somebody in the running for one particular campaign. */
class Shortlist extends Model
{
    protected $fillable = ['campaign_id', 'user_id', 'subject_type', 'subject_id', 'note'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
