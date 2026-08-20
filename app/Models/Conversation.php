<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'conversation_uuid', 'channel', 'subject', 'status',
        'creator_profile_id', 'owner_user_id', 'owner_scope',
        'external_contact_id', 'routing_token', 'last_message_at',
    ];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function creatorProfile(): BelongsTo
    {
        return $this->belongsTo(CreatorProfile::class);
    }

    public function externalContact(): BelongsTo
    {
        return $this->belongsTo(ExternalContact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasMany<ConversationParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }
}
