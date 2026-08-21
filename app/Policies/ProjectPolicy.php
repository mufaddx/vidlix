<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Who may see and act on a project.
 *
 * A project belongs to the two people doing the work. There is no wider
 * audience — no observers, no delegated access — so every ability here reduces
 * to the same question, and saying it once is the point of the class.
 */
class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->involves($user);
    }

    public function update(User $user, Project $project): bool
    {
        return $project->involves($user);
    }

    /** Uploading work, requesting a revision, paying: all sides of the same deal. */
    public function participate(User $user, Project $project): bool
    {
        return $project->involves($user);
    }
}
