<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupportThread extends Model
{
    protected $fillable = [
        'conversation_id', 'reference', 'user_id', 'status',
        'priority', 'assigned_to_user_id', 'closed_at',
    ];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'HD-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
