<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'conversation_id', 'actor_user_id', 'acting_for_creator_id', 'direction',
        'body', 'provider_message_id', 'email_message_id', 'in_reply_to',
        'email_references', 'delivery_status',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function emailEvents(): HasMany
    {
        return $this->hasMany(EmailEvent::class);
    }
}
