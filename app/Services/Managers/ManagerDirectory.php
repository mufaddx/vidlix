<?php

namespace App\Services\Managers;

use App\Models\ManagerAssignment;
use App\Models\ManagerInvitation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\GenericNotice;
use App\Services\Audit\AuditLogger;
use App\Services\Email\OutboundEmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Appointing and activating managers.
 *
 * Nobody applies to be a manager. A manager exists only because an account
 * holder appointed them, or because Vidlix provided one — so there is
 * deliberately no self-service path into this class.
 */
class ManagerDirectory
{
    public function __construct(
        private AuditLogger $audit,
        private OutboundEmailService $outbound,
    ) {}

    /**
     * Invite somebody to manage one side of an account.
     *
     * @param  string  $scope  creator | brand | editor
     */
    public function invite(User $owner, string $scope, array $data, string $source = 'owner', ?User $invitedBy = null): ManagerInvitation
    {
        $this->assertScopeOwned($owner, $scope);

        $email = strtolower(trim($data['email']));

        if (strcasecmp($email, $owner->email) === 0) {
            throw ValidationException::withMessages([
                'email' => __('You cannot appoint yourself as your own manager.'),
            ]);
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing && $this->isAssigned($owner->id, $existing->id, $scope)) {
            throw ValidationException::withMessages([
                'email' => __('That person already manages this account.'),
            ]);
        }

        // Supersede any open invitation for the same person and scope rather
        // than leaving two live tokens for one seat.
        ManagerInvitation::query()
            ->where('owner_user_id', $owner->id)
            ->where('scope', $scope)
            ->where('email', $email)
            ->where('status', 'invited')
            ->update(['status' => 'revoked']);

        $invitation = ManagerInvitation::query()->create([
            'owner_user_id' => $owner->id,
            'scope' => $scope,
            'email' => $email,
            'mobile' => $data['mobile'] ?? null,
            'name' => $data['name'] ?? null,
            'token' => Str::lower(Str::ulid()).Str::lower(Str::random(16)),
            'source' => $source,
            'invited_by_user_id' => $invitedBy?->id,
            'permissions' => $data['permissions'] ?? [],
            'status' => 'invited',
            'expires_at' => now()->addDays(14),
        ]);

        $this->audit->record('manager.invited', $invitation, ['scope' => $scope, 'source' => $source]);
        $this->notify($invitation);

        return $invitation;
    }

    /** The token in the link is the invitation; it is never looked up by email. */
    public function findOpenInvitation(string $token): ?ManagerInvitation
    {
        $invitation = ManagerInvitation::query()->where('token', $token)->first();

        return $invitation && $invitation->isOpen() ? $invitation : null;
    }

    /**
     * Activate an invitation for somebody who has no Vidlix account yet. They
     * choose their password here; following the emailed token is what proves
     * they control the address, so the email is marked verified.
     */
    public function activateAsNewUser(ManagerInvitation $invitation, array $data): User
    {
        if (User::query()->where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('An account already exists for this email. Please sign in to accept.'),
            ]);
        }

        return DB::transaction(function () use ($invitation, $data) {
            $user = User::query()->create([
                'name' => $data['name'] ?? $invitation->name ?? Str::before($invitation->email, '@'),
                'email' => $invitation->email,
                'mobile' => $data['mobile'] ?? $invitation->mobile,
                'password' => $data['password'],
                'status' => 'active',
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->accept($invitation, $user);

            return $user;
        });
    }

    /** Accept an invitation as the already-signed-in owner of that email. */
    public function acceptAsExistingUser(ManagerInvitation $invitation, User $user): ManagerAssignment
    {
        if (strcasecmp($invitation->email, $user->email) !== 0) {
            abort(403, __('This invitation belongs to a different email address.'));
        }

        return $this->accept($invitation, $user);
    }

    private function accept(ManagerInvitation $invitation, User $manager): ManagerAssignment
    {
        return DB::transaction(function () use ($invitation, $manager) {
            $this->ensureManagerRole($manager);

            $assignment = ManagerAssignment::query()->updateOrCreate(
                [
                    'owner_user_id' => $invitation->owner_user_id,
                    'manager_user_id' => $manager->id,
                    'scope' => $invitation->scope,
                ],
                [
                    'status' => 'active',
                    'source' => $invitation->source,
                    'assigned_by_user_id' => $invitation->invited_by_user_id,
                    'permissions' => $invitation->permissions ?? [],
                    'accepted_at' => now(),
                    'revoked_at' => null,
                ],
            );

            $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);
            $this->audit->record('manager.accepted', $assignment, ['scope' => $invitation->scope]);

            return $assignment;
        });
    }

    public function revoke(User $owner, ManagerAssignment $assignment): void
    {
        abort_unless($assignment->owner_user_id === $owner->id, 403);

        $assignment->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->audit->record('manager.revoked', $assignment);
    }

    /** @return array<int, string> scopes this user actually has an account for */
    public function ownedScopes(User $user): array
    {
        return array_values(array_filter([
            $user->creatorProfile ? 'creator' : null,
            $user->brandProfile ? 'brand' : null,
            $user->editorProfile ? 'editor' : null,
        ]));
    }

    private function assertScopeOwned(User $owner, string $scope): void
    {
        if (! in_array($scope, ManagerAssignment::SCOPES, true)) {
            throw ValidationException::withMessages(['scope' => __('Unknown account type.')]);
        }

        if (! in_array($scope, $this->ownedScopes($owner), true)) {
            throw ValidationException::withMessages([
                'scope' => __('You do not have a :scope account to delegate.', ['scope' => $scope]),
            ]);
        }
    }

    private function isAssigned(int $ownerId, int $managerId, string $scope): bool
    {
        return ManagerAssignment::query()
            ->active()
            ->where('owner_user_id', $ownerId)
            ->where('manager_user_id', $managerId)
            ->where('scope', $scope)
            ->exists();
    }

    private function ensureManagerRole(User $user): void
    {
        $role = Role::query()->where('slug', 'manager')->first();
        if ($role && ! $user->roles()->where('roles.id', $role->id)->exists()) {
            $user->roles()->attach($role);
        }
    }

    private function notify(ManagerInvitation $invitation): void
    {
        // Delivery follows the same honest path as every other outbound mail:
        // stored, queued, and only "sent" when the provider says so.
        $invitation->owner?->notify(new GenericNotice('manager_invited', [
            'invitation_id' => $invitation->id,
            'scope' => $invitation->scope,
        ]));
    }
}
