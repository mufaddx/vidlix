<?php

namespace App\Services\Workspace;

use App\Models\ManagerAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Which account the signed-in person is currently acting as.
 *
 * Identity never changes: the session always belongs to the logged-in user. A
 * manager may *act for* somebody else's account, and every request re-checks
 * that assignment against the database. The session only ever holds ids, never
 * a permission — so a tampered session grants nothing.
 */
class WorkspaceContext
{
    public const ACTING_USER = 'acting_for_user_id';

    public const ACTING_SCOPE = 'acting_scope';

    public function hydrate(User $user): void
    {
        $roles = $user->roleSlugs();
        $active = session('active_role');

        if (! is_string($active) || ! in_array($active, $roles, true)) {
            session(['active_role' => $roles[0] ?? null]);
        }

        // Re-authorise on every request. An assignment revoked a second ago must
        // stop working immediately, not at the next login.
        $actingFor = session(self::ACTING_USER);
        $scope = session(self::ACTING_SCOPE);
        if ($actingFor && ! $this->canActFor($user, (int) $actingFor, (string) $scope)) {
            $this->actAsSelf();
        }
    }

    public function isRole(string $role): bool
    {
        return session('active_role') === $role;
    }

    public function switchRole(User $user, string $role): void
    {
        if (! in_array($role, $user->roleSlugs(), true)) {
            throw ValidationException::withMessages([
                'role' => __('You are not assigned that role.'),
            ]);
        }

        session(['active_role' => $role]);
        $this->actAsSelf();
    }

    /** Switch to a managed account. Authorisation is looked up, never passed in. */
    public function actAs(User $user, int $ownerUserId, string $scope): void
    {
        if ($ownerUserId === $user->id) {
            $this->actAsSelf();

            return;
        }

        abort_unless($this->canActFor($user, $ownerUserId, $scope), 403, __('You do not manage that account.'));

        session([
            'active_role' => 'manager',
            self::ACTING_USER => $ownerUserId,
            self::ACTING_SCOPE => $scope,
        ]);
    }

    public function actAsSelf(): void
    {
        session()->forget([self::ACTING_USER, self::ACTING_SCOPE, 'acting_for_creator_id']);
    }

    public function canActFor(User $user, int $ownerUserId, string $scope): bool
    {
        if (! in_array($scope, ManagerAssignment::SCOPES, true)) {
            return false;
        }

        return ManagerAssignment::query()
            ->active()
            ->where('manager_user_id', $user->id)
            ->where('owner_user_id', $ownerUserId)
            ->where('scope', $scope)
            ->exists();
    }

    public function actingForUserId(): ?int
    {
        $id = session(self::ACTING_USER);

        return $id ? (int) $id : null;
    }

    public function actingScope(): ?string
    {
        $scope = session(self::ACTING_SCOPE);

        return is_string($scope) ? $scope : null;
    }

    /** The account whose data should be read: the managed one, or the user's own. */
    public function effectiveUser(User $user): User
    {
        $id = $this->actingForUserId();

        return $id ? (User::query()->find($id) ?? $user) : $user;
    }

    public function isActingForSomeoneElse(): bool
    {
        return $this->actingForUserId() !== null;
    }

    /**
     * Everything the switcher should offer: the person's own account first,
     * then every account they currently manage.
     *
     * @return Collection<int, array{owner_user_id: int, scope: ?string, label: string, sublabel: string, is_self: bool, company_provided: bool, active: bool}>
     */
    public function switchableAccounts(User $user): Collection
    {
        $actingId = $this->actingForUserId();
        $actingScope = $this->actingScope();

        $own = collect([[
            'owner_user_id' => $user->id,
            'scope' => null,
            'label' => $user->name,
            'sublabel' => __('Your own account'),
            'is_self' => true,
            'company_provided' => false,
            'active' => $actingId === null,
        ]]);

        $managed = ManagerAssignment::query()
            ->active()
            ->where('manager_user_id', $user->id)
            ->with('owner:id,name,email')
            ->get()
            ->map(fn (ManagerAssignment $a) => [
                'owner_user_id' => (int) $a->owner_user_id,
                'scope' => $a->scope,
                'label' => $a->owner?->name ?? __('Unknown account'),
                'sublabel' => $a->isCompanyProvided()
                    ? __(':scope · provided by Vidlix', ['scope' => ucfirst($a->scope)])
                    : ucfirst($a->scope),
                'is_self' => false,
                'company_provided' => $a->isCompanyProvided(),
                'active' => $actingId === (int) $a->owner_user_id && $actingScope === $a->scope,
            ]);

        return $own->concat($managed);
    }
}
