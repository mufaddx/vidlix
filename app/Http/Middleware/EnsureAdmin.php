<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $allowed = ['super_admin', 'operations', 'verification', 'finance', 'support', 'content'];
        if ($user === null || empty(array_intersect($user->roleSlugs(), $allowed))) {
            abort(403);
        }

        return $next($request);
    }
}
