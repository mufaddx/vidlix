<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactFormVersion extends Model
{
    protected $fillable = ['contact_form_id', 'version_number', 'schema_json', 'published_at'];

    protected function casts(): array
    {
        return [
            'schema_json' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(ContactForm::class, 'contact_form_id');
    }
}
