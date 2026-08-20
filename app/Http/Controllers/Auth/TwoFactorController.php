<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Setting up a second factor, and being asked for it at sign-in.
 */
class TwoFactorController extends Controller
{
    /** The session key holding the half-authenticated user between steps. */
    public const PENDING_KEY = 'auth.two_factor_pending';

    public function settings(Request $request, TwoFactorService $twoFactor): View
    {
        $user = $request->user();

        return view('auth.two-factor', [
            'enabled' => $twoFactor->isEnabled($user),
            'remaining' => $twoFactor->unusedRecoveryCodeCount($user),
            'secret' => $request->session()->get('two_factor.secret'),
            'otpauth' => $request->session()->get('two_factor.otpauth'),
            'codes' => $request->session()->get('two_factor.codes', []),
        ]);
    }

    public function begin(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $secret = $twoFactor->beginEnrolment($request->user());

        return back()->with([
            'two_factor.secret' => $secret,
            'two_factor.otpauth' => $twoFactor->otpauthUri($request->user(), $secret),
        ]);
    }

    public function confirm(Request $request, TwoFactorService $twoFactor, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        $codes = $twoFactor->confirm($request->user(), $data['code']);

        if ($codes === []) {
            throw ValidationException::withMessages([
                'code' => __('That code did not match. Check your authenticator app and try the current code.'),
            ]);
        }

        $audit->record('auth.two_factor_enabled', $request->user());

        return back()->with([
            'status' => __('Two-factor authentication is on. Save these recovery codes now — they are shown once.'),
            'two_factor.codes' => $codes,
        ]);
    }

    public function disable(Request $request, TwoFactorService $twoFactor, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);

        // The password is re-checked because turning the second factor off is
        // exactly what somebody with a borrowed session would want to do.
        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => __('That password is not correct.')]);
        }

        $twoFactor->disable($request->user());
        $audit->record('auth.two_factor_disabled', $request->user());

        return back()->with('status', __('Two-factor authentication is off.'));
    }

    public function regenerate(Request $request, TwoFactorService $twoFactor, AuditLogger $audit): RedirectResponse
    {
        abort_unless($twoFactor->isEnabled($request->user()), 403);

        $codes = $twoFactor->regenerateRecoveryCodes($request->user());
        $audit->record('auth.two_factor_codes_regenerated', $request->user());

        return back()->with([
            'status' => __('New recovery codes. The old ones no longer work.'),
            'two_factor.codes' => $codes,
        ]);
    }

    /** The challenge shown between password and session. */
    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::PENDING_KEY)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request, TwoFactorService $twoFactor, AuditLogger $audit): RedirectResponse
    {
        $userId = $request->session()->get(self::PENDING_KEY);

        if ($userId === null) {
            return redirect()->route('login');
        }

        $data = $request->validate(['code' => ['required', 'string']]);
        $user = User::query()->find($userId);

        if ($user === null || ! $twoFactor->verify($user, $data['code'])) {
            $audit->record('auth.two_factor_failed', $user, [], $userId);

            throw ValidationException::withMessages([
                'code' => __('That code did not match. Try the current code, or one of your recovery codes.'),
            ]);
        }

        $request->session()->forget(self::PENDING_KEY);
        Auth::login($user, (bool) $request->session()->pull('auth.two_factor_remember', false));
        $request->session()->regenerate();

        $audit->record('auth.two_factor_passed', $user);

        return redirect()->intended(route('dashboard'));
    }
}
