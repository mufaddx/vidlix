<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    protected $fillable = [
        'ledger_account_id', 'entry_uuid', 'state', 'amount_minor', 'currency',
        'reference_type', 'reference_id', 'provider_reference', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }
}
