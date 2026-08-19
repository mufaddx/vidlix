<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Identity\AccountProvisioner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(
        RegisterRequest $request,
        AccountProvisioner $onboarding,
        AuditLogger $audit,
    ): RedirectResponse {
        $user = User::query()->create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'mobile' => $request->string('mobile'),
            'password' => $request->string('password'),
            'status' => 'active',
        ]);

        // A role only exists once the person applies for it. Signup with a role
        // is still honoured so existing links and the API keep working.
        $requested = $request->string('role')->toString();
        if ($requested !== '') {
            $role = Role::query()->where('slug', $requested)->firstOrFail();
            $user->roles()->attach($role);
            $onboarding->provisionRole($user, $role->slug);
        }

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        session(['active_role' => $requested !== '' ? $requested : null]);
        $audit->record('auth.registered', $user, ['role' => $requested ?: 'none'], $user->id);

        return redirect()->route('verification.notice');
    }
}
