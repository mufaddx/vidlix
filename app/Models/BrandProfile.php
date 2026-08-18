<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandProfile extends Model
{
    protected $fillable = [
        'user_id', 'company_name', 'slug', 'website', 'business_email',
        'verification_status', 'industry',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }
}
