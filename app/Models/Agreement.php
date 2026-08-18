<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agreement extends Model
{
    protected $fillable = [
        'agreement_uuid', 'agreeable_type', 'agreeable_id', 'version_number', 'terms', 'status',
    ];

    protected function casts(): array
    {
        return ['terms' => 'array'];
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(AgreementAcceptance::class);
    }
}
