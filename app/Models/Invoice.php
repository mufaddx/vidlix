<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $due_date
 */
class Invoice extends Model
{
    protected $fillable = [
        'invoice_number', 'seller_user_id', 'buyer_user_id', 'invoiceable_type', 'invoiceable_id',
        'subtotal_minor', 'tax_minor', 'fee_minor', 'total_minor', 'currency', 'status',
        'due_date', 'pdf_storage_key',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }
}
