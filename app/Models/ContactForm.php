<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactForm extends Model
{
    protected $fillable = [
        'creator_public_page_id', 'owner_user_id', 'owner_scope', 'current_version', 'is_enabled',
    ];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CreatorPublicPage::class, 'creator_public_page_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContactFormVersion::class);
    }

    public function publishedVersion(): ?ContactFormVersion
    {
        return $this->versions()->whereNotNull('published_at')->orderByDesc('version_number')->first();
    }
}
