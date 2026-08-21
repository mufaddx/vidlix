<?php

namespace App\Policies;

use App\Models\ProjectFile;
use App\Models\User;

/**
 * Who may download a file.
 *
 * Checked before a signed URL is issued, never after. A signed URL handed to
 * the wrong person is a permanent leak for as long as it lives, because the
 * signature makes it work regardless of who follows it.
 */
class ProjectFilePolicy
{
    public function download(User $user, ProjectFile $file): bool
    {
        $project = $file->project;

        return $project !== null && $project->involves($user);
    }
}
