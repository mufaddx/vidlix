<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A conversation about terms, kept as a record rather than as chat.
 *
 * The status is what each side is waiting on. The offers underneath it are
 * append-only, so "what did we actually agree" has one answer that cannot be
 * edited after the fact.
 */
class Negotiation extends Model
{
    public const NEGOTIATING = 'negotiating';

    public const OFFER_SENT = 'offer_sent';

    public const COUNTER_OFFER = 'counter_offer';

    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    /** Statuses from which no further move is possible. */
    public const CLOSED = [self::ACCEPTED, self::REJECTED, self::EXPIRED, self::CANCELLED];

    protected $fillable = [
        'uuid', 'campaign_id', 'campaign_application_id',
        'initiator_user_id', 'counterparty_user_id', 'counterparty_scope',
        'status', 'accepted_offer_id', 'accepted_at', 'expires_at', 'project_id',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function offers(): HasMany
    {
        return $this->hasMany(NegotiationOffer::class)->orderBy('sequence');
    }

    public function latestOffer(): ?NegotiationOffer
    {
        return NegotiationOffer::query()
            ->where('negotiation_id', $this->getKey())
            ->orderByDesc('sequence')
            ->first();
    }

    public function acceptedOffer(): ?NegotiationOffer
    {
        return $this->accepted_offer_id
            ? NegotiationOffer::query()->find($this->accepted_offer_id)
            : null;
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_user_id');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counterparty_user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, self::CLOSED, true);
    }

    /** Is this person one of the two sides? */
    public function involves(int $userId): bool
    {
        return $this->initiator_user_id === $userId
            || $this->counterparty_user_id === $userId;
    }

    /** The other side, from a given person's point of view. */
    public function otherSideOf(int $userId): ?int
    {
        return match ($userId) {
            $this->initiator_user_id => $this->counterparty_user_id,
            $this->counterparty_user_id => $this->initiator_user_id,
            default => null,
        };
    }
}
