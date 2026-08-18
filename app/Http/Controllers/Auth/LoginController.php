<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuditLogger $audit): RedirectResponse
    {
        $login = $request->string('login')->toString();
        $user = User::query()
            ->where('email', $login)
            ->orWhere('mobile', $login)
            ->first();

        $ok = $user && Hash::check($request->string('password')->toString(), $user->password);

        LoginAttempt::query()->create([
            'identifier' => $login,
            'ip_address' => $request->ip(),
            'successful' => (bool) $ok,
        ]);

        if (! $ok || $user->status !== 'active') {
            $audit->record('auth.login_failed', $user, ['login' => $login]);
            throw ValidationException::withMessages([
                'login' => __('These credentials do not match our records.'),
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        session(['active_role' => $user->roleSlugs()[0] ?? null]);
        $audit->record('auth.login', $user, [], $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        $audit->record('auth.logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
