<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandDocument extends Model
{
    protected $fillable = [
        'brand_profile_id', 'kind', 'original_name', 'disk', 'storage_key',
        'size_bytes', 'mime', 'review_status', 'review_note',
        'reviewed_by_user_id', 'reviewed_at',
    ];

    /** What a brand can be asked to produce. */
    public const KINDS = [
        'gst_certificate' => 'GST certificate',
        'incorporation' => 'Certificate of incorporation',
        'pan_card' => 'Company PAN',
        'authorization_letter' => 'Authorisation letter',
        'address_proof' => 'Address proof',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function brandProfile(): BelongsTo
    {
        return $this->belongsTo(BrandProfile::class);
    }
}
