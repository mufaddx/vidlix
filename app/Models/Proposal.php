<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proposal extends Model
{
    protected $fillable = [
        'proposal_uuid', 'proposible_type', 'proposible_id', 'from_user_id', 'to_user_id', 'status',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(ProposalVersion::class);
    }

    public function latestVersion(): ?ProposalVersion
    {
        return $this->versions()->orderByDesc('version_number')->first();
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }
}
