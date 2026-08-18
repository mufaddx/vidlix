<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactForm extends Model
{
    protected $fillable = ['creator_public_page_id', 'current_version'];

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
