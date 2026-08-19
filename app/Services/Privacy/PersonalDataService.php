<?php

namespace App\Services\Privacy;

use App\Models\LedgerEntry;
use App\Models\Message;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Getting your own data out, and getting your account closed.
 *
 * Closing an account cannot mean deleting every row. The ledger is append-only
 * and financial records have to be kept, so closure strips the identity out of
 * the account and leaves the money history standing with nobody's name on it.
 * The export says exactly that, rather than promising an erasure that would not
 * happen.
 */
class PersonalDataService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Everything held about this person that is theirs to see.
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $user->loadMissing(['roles', 'creatorProfile', 'editorProfile', 'brandProfile', 'managerProfile']);

        $this->audit->record('privacy.exported', $user, [], $user->id);

        return [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => optional($user->email_verified_at)->toIso8601String(),
                'created_at' => optional($user->created_at)->toIso8601String(),
                'roles' => $user->roles->pluck('slug')->all(),
            ],
            'profiles' => array_filter([
                'creator' => $user->creatorProfile?->toArray(),
                'editor' => $user->editorProfile?->toArray(),
                'brand' => $user->brandProfile?->toArray(),
                'manager' => $user->managerProfile?->toArray(),
            ]),
            'messages' => Message::query()
                ->where('actor_user_id', $user->id)
                ->get(['conversation_id', 'direction', 'body', 'created_at'])
                ->toArray(),
            'ledger_entries' => LedgerEntry::query()
                ->whereIn('ledger_account_id', $user->ledgerAccounts()->select('id'))
                ->get(['ledger_account_id', 'entry_uuid', 'state', 'amount_minor', 'currency', 'reference_type', 'reference_id', 'created_at'])
                ->toArray(),
            'notes' => [
                'This file holds what Vidlix stores about you. Money records are '
                .'included because they are yours to read, but they cannot be '
                .'deleted on request: the ledger is append-only and financial '
                .'records must be retained.',
                'Files you uploaded live in object storage and are not inlined '
                .'here. Their keys appear in the project records.',
            ],
        ];
    }

    /**
     * Close the account: strip the identity, keep the records that must stay.
     *
     * The email is replaced rather than blanked so the unique index still holds
     * and nobody can re-register into the closed account's history.
     */
    public function closeAccount(User $user, string $reason = ''): void
    {
        DB::transaction(function () use ($user, $reason) {
            $this->audit->record('privacy.account_closed', $user, [
                'reason' => $reason,
                'retained' => 'ledger entries and invoices, with the identity removed',
            ], $user->id);

            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $user->creatorProfile?->delete();
            $user->editorProfile?->delete();
            $user->brandProfile?->delete();
            $user->managerProfile?->delete();

            $user->roles()->detach();

            $user->forceFill([
                'name' => 'Closed account',
                'email' => 'closed-'.$user->id.'@accounts.invalid',
                'password' => bcrypt(bin2hex(random_bytes(32))),
                'email_verified_at' => null,
                'remember_token' => null,
            ])->save();
        });
    }
}
