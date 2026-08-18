<?php

namespace App\Services\Workspace;

use App\Models\CreatorManagerRelationship;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class WorkspaceContext
{
    public function hydrate(User $user): void
    {
        $roles = $user->roleSlugs();
        $active = session('active_role');

        if (! is_string($active) || ! in_array($active, $roles, true)) {
            $active = $roles[0] ?? null;
            session(['active_role' => $active]);
        }

        $actingFor = session('acting_for_creator_id');
        if ($actingFor && ! $this->managerCanAct($user, (int) $actingFor)) {
            session()->forget('acting_for_creator_id');
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
        if ($role !== 'manager') {
            session()->forget('acting_for_creator_id');
        }
    }

    public function switchManagedCreator(User $user, int $creatorUserId): void
    {
        if (! $this->managerCanAct($user, $creatorUserId)) {
            abort(403, __('You cannot manage this creator.'));
        }

        session([
            'active_role' => 'manager',
            'acting_for_creator_id' => $creatorUserId,
        ]);
    }

    public function managerCanAct(User $user, int $creatorUserId): bool
    {
        return CreatorManagerRelationship::query()
            ->where('manager_user_id', $user->id)
            ->where('creator_user_id', $creatorUserId)
            ->where('status', 'active')
            ->exists();
    }

    public function actingForCreatorId(): ?int
    {
        $id = session('acting_for_creator_id');

        return $id ? (int) $id : null;
    }
}
