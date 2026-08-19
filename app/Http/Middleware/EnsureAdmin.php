<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /*
         | Getting through this door only means "you are staff". What you may
         | actually do is decided per route by a `can:` gate, so being able to
         | reach the admin panel grants nothing on its own.
         */
        $user = $request->user();

        if ($user === null) {
            // Never bounce to the member login: this is a different front door.
            return redirect()->guest(route('admin.login'));
        }

        if (! $user->isStaff()) {
            abort(403, __('This account has no admin access.'));
        }

        return $next($request);
    }
}
