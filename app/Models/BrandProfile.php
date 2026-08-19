<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandProfile extends Model
{
    protected $fillable = [
        'user_id', 'company_name', 'legal_name', 'slug', 'website', 'business_email',
        'verification_status', 'industry',
        'gstin', 'pan', 'cin', 'registered_address',
        'billing_state', 'billing_country', 'billing_pincode',
        'authorized_person_name', 'authorized_person_designation',
        'authorized_person_email', 'authorized_person_phone',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BrandDocument::class);
    }

    /**
     * What verification still needs from this brand.
     *
     * Returned as a list of labels rather than a bare boolean so the brand can
     * be told what is actually missing instead of just being refused.
     *
     * @return array<int, string>
     */
    public function missingForVerification(): array
    {
        $missing = [];

        foreach ([
            'legal_name' => 'Registered legal name',
            'gstin' => 'GSTIN',
            'pan' => 'Company PAN',
            'registered_address' => 'Registered address',
            'authorized_person_name' => 'Authorised person',
            'authorized_person_email' => 'Authorised person email',
        ] as $field => $label) {
            if (blank($this->{$field})) {
                $missing[] = $label;
            }
        }

        $uploaded = $this->documents()->pluck('kind')->all();
        foreach (['gst_certificate', 'authorization_letter'] as $kind) {
            if (! in_array($kind, $uploaded, true)) {
                $missing[] = BrandDocument::KINDS[$kind];
            }
        }

        return $missing;
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }
}
