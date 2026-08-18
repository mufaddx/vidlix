<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    protected $fillable = [
        'name', 'status', 'total_amount_minor', 'advance_amount_minor', 'deadline',
        'revision_limit', 'owner_user_id', 'counterparty_user_id', 'conversation_id',
        'campaign_id', 'revisions_used',
    ];

    protected function casts(): array
    {
        return ['deadline' => 'date'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counterparty_user_id');
    }

    public function files()
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function revisions()
    {
        return $this->hasMany(ProjectRevision::class);
    }

    public function involves(User $user): bool
    {
        return in_array($user->id, array_filter([$this->owner_user_id, $this->counterparty_user_id]), true);
    }
}
