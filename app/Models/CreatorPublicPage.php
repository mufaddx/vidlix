<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CreatorPublicPage extends Model
{
    protected $fillable = [
        'creator_profile_id',
        'draft_payload',
        'published_payload',
        'published_at',
        'status',
        'theme',
    ];

    protected function casts(): array
    {
        return [
            'draft_payload' => 'array',
            'published_payload' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function creatorProfile(): BelongsTo
    {
        return $this->belongsTo(CreatorProfile::class);
    }

    public function contactForm(): HasOne
    {
        return $this->hasOne(ContactForm::class);
    }
}
