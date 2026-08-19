<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A separate front door for staff.
 *
 * Signing in as a member must never be a way into the admin panel, and a
 * member who wanders to /admin should see a staff sign-in, not their own
 * account. Credentials that are valid but hold no staff access are refused
 * here with the same message as a wrong password, so this page cannot be used
 * to discover which accounts are staff.
 */
class AdminAuthController extends Controller
{
    public function create(): View
    {
        if (Auth::check() && Auth::user()->isStaff()) {
            return view('admin.auth.already');
        }

        return view('admin.auth.login');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        $failed = ! $user || ! Hash::check($data['password'], $user->password);

        // Deliberately identical to a wrong password: a valid member signing in
        // here must not learn that the account exists but lacks admin access.
        if ($failed || ! $user->isStaff()) {
            $audit->record('admin.login_refused', null, ['email' => $data['email']]);

            throw ValidationException::withMessages([
                'email' => __('Those credentials do not match a staff account.'),
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $audit->record('admin.login', $user, [], $user->id);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', __('Signed out of the admin panel.'));
    }
}
