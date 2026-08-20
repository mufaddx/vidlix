<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationReport extends Model
{
    public const REASONS = ['spam', 'harassment', 'scam', 'impersonation', 'other'];

    protected $fillable = [
        'conversation_id', 'reported_by_user_id', 'reason', 'detail',
        'status', 'reviewed_by_user_id', 'review_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }
}
