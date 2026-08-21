<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One set of terms, as offered.
 *
 * Never updated. A change of mind is the next offer, which is what makes the
 * history worth keeping — and what makes an accepted offer safe to rely on.
 *
 * @property array<int, mixed>|null $deliverables JSON, so it holds whatever was
 *                                                written into it rather than whatever we hoped for
 */
class NegotiationOffer extends Model
{
    protected $fillable = [
        'negotiation_id', 'sequence', 'offered_by_user_id',
        'amount_minor', 'currency', 'deliverables', 'deadline',
        'revision_limit', 'usage_rights', 'note',
    ];

    protected function casts(): array
    {
        return [
            'deliverables' => 'array',
            'deadline' => 'date',
        ];
    }

    /**
     * The deliverables as a plain list of strings.
     *
     * schema-less JSON comes back as whatever was written into it, so this is
     * where it is made safe to iterate rather than at each call site.
     *
     * @return list<string>
     */
    public function deliverableList(): array
    {
        $items = [];

        foreach ((array) ($this->deliverables ?? []) as $item) {
            if (is_scalar($item)) {
                $text = trim((string) $item);

                if ($text !== '') {
                    $items[] = $text;
                }
            }
        }

        return $items;
    }

    public function negotiation(): BelongsTo
    {
        return $this->belongsTo(Negotiation::class);
    }

    public function offeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offered_by_user_id');
    }
}
