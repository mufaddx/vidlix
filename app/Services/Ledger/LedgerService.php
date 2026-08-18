<?php

namespace App\Services\Ledger;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * The ledger is the append-only source of truth for money.
 *
 * Nothing here ever updates or deletes an entry. State changes (reserved money
 * becoming available, an available balance leaving on a payout) are expressed
 * as additional entries, including negative reversing ones. Every UI balance is
 * a SUM over these rows and is never stored anywhere else.
 */
class LedgerService
{
    public const STATE_RESERVED = 'reserved';

    public const STATE_AVAILABLE = 'available';

    public const STATE_WITHDRAWN = 'withdrawn';

    public function account(int $userId, string $kind, string $currency): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate([
            'user_id' => $userId,
            'kind' => $kind,
            'currency' => strtoupper($currency),
        ]);
    }

    /**
     * Append one entry. When an idempotency key is supplied the entry uuid is
     * derived from it, so a replayed webhook can never double-post.
     */
    public function append(
        int $userId,
        string $kind,
        string $state,
        int $amountMinor,
        string $currency,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $providerReference = null,
        array $meta = [],
        ?string $idempotencyKey = null,
    ): LedgerEntry {
        $account = $this->account($userId, $kind, $currency);
        $uuid = $idempotencyKey === null
            ? (string) Str::uuid()
            : (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'vidlix:ledger:'.$idempotencyKey);

        return DB::transaction(fn () => LedgerEntry::query()->firstOrCreate(
            ['entry_uuid' => $uuid],
            [
                'ledger_account_id' => $account->id,
                'state' => $state,
                'amount_minor' => $amountMinor,
                'currency' => strtoupper($currency),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'provider_reference' => $providerReference,
                'meta' => $meta,
            ],
        ));
    }

    /**
     * Release escrowed money: reverse the reserved leg and add an available leg.
     * Both are appends; the original reservation row is left untouched.
     */
    public function release(
        int $userId,
        string $kind,
        int $amountMinor,
        string $currency,
        ?string $referenceType,
        ?int $referenceId,
        ?string $providerReference,
        string $idempotencyKey,
    ): void {
        $this->append(
            $userId, $kind, self::STATE_RESERVED, -$amountMinor, $currency,
            $referenceType, $referenceId, $providerReference,
            ['reason' => 'release_reversal'], $idempotencyKey.':reserved-out',
        );
        $this->append(
            $userId, $kind, self::STATE_AVAILABLE, $amountMinor, $currency,
            $referenceType, $referenceId, $providerReference,
            ['reason' => 'release'], $idempotencyKey.':available-in',
        );
    }

    /** Money leaving on a confirmed payout. */
    public function withdraw(
        int $userId,
        string $kind,
        int $amountMinor,
        string $currency,
        ?string $referenceType,
        ?int $referenceId,
        ?string $providerReference,
        string $idempotencyKey,
    ): void {
        $this->append(
            $userId, $kind, self::STATE_AVAILABLE, -$amountMinor, $currency,
            $referenceType, $referenceId, $providerReference,
            ['reason' => 'payout_debit'], $idempotencyKey.':available-out',
        );
        $this->append(
            $userId, $kind, self::STATE_WITHDRAWN, $amountMinor, $currency,
            $referenceType, $referenceId, $providerReference,
            ['reason' => 'payout_settled'], $idempotencyKey.':withdrawn',
        );
    }

    public function balance(int $userId, string $state, string $kind = 'earnings', ?string $currency = null): int
    {
        $currency = strtoupper($currency ?? (string) config('vidlix.currency', 'INR'));

        return (int) LedgerEntry::query()
            ->whereIn('ledger_account_id', LedgerAccount::query()
                ->where('user_id', $userId)
                ->where('kind', $kind)
                ->where('currency', $currency)
                ->select('id'))
            ->where('state', $state)
            ->sum('amount_minor');
    }
}
