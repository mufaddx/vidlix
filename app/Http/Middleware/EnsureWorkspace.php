<?php

namespace App\Http\Middleware;

use App\Services\Workspace\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspace
{
    public function __construct(private WorkspaceContext $workspace) {}

    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $this->workspace->hydrate($user);

        if ($role !== null && ! $this->workspace->isRole($role)) {
            abort(403, __('You do not have access to this workspace.'));
        }

        return $next($request);
    }
}
