<?php

namespace App\Services\Workspace;

use App\Models\User;
use App\Services\Profiles\ProfileDirectory;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Which of their own profiles the signed-in person is currently working in.
 *
 * Identity never changes: the session always belongs to the logged-in user, and
 * one human keeps one account no matter how many profiles they hold. Nobody can
 * act on somebody else's behalf — that was the manager system, and it is gone.
 *
 * The session only ever holds the name of a profile type, never a permission.
 * Whether that profile is usable is re-read from the database on every request,
 * so a profile suspended a second ago stops working immediately rather than at
 * the next sign-in, and a tampered session grants nothing.
 */
class WorkspaceContext
{
    public function __construct(private ProfileDirectory $directory) {}

    public function hydrate(User $user): void
    {
        // Only profiles a reviewer has approved. A pending application, or one
        // that was suspended a moment ago, must stop being usable at once.
        $available = $this->directory->activeTypes($user);
        $active = session('active_role');

        if (! is_string($active) || ! in_array($active, $available, true)) {
            session(['active_role' => $available[0] ?? null]);
        }
    }

    public function isRole(string $role): bool
    {
        return session('active_role') === $role;
    }

    public function switchRole(User $user, string $role): void
    {
        if (! in_array($role, $this->directory->activeTypes($user), true)) {
            throw ValidationException::withMessages([
                'role' => __('That profile is not active on your account.'),
            ]);
        }

        session(['active_role' => $role]);
    }

    /**
     * The account whose data should be read.
     *
     * Always the signed-in user. The method is kept because callers read better
     * for saying whose data they want than for assuming it, and because it is
     * the one place a future delegation feature would have to go through.
     */
    public function effectiveUser(User $user): User
    {
        return $user;
    }

    /**
     * Everything the profile switcher should offer: the approved profiles on
     * this person's own account, and nothing else.
     *
     * @return Collection<int, array{type: string, label: string, active: bool}>
     */
    public function switchableProfiles(User $user): Collection
    {
        $active = session('active_role');

        return collect($this->directory->activeTypes($user))->map(fn (string $type) => [
            'type' => $type,
            'label' => ProfileDirectory::LABELS[$type] ?? ucfirst($type),
            'active' => $active === $type,
        ])->values();
    }
}
