<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorProfile extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isApproved(): bool
    {
        return $this->application_status === 'approved';
    }

    protected $fillable = [
        'user_id', 'username', 'display_name', 'bio', 'application_status',
        'software', 'specializations', 'starting_price_minor', 'availability',
        'visibility', 'accepts_inquiries',
    ];

    /** Only an approved, public editor page is reachable by the world. */
    public function isPublished(): bool
    {
        return $this->visibility === 'public' && $this->application_status === 'approved';
    }

    protected function casts(): array
    {
        return [
            'software' => 'array',
            'specializations' => 'array',
        ];
    }
}
