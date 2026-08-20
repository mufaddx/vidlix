<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\OtpService;
use App\Services\Identity\AccountProvisioner;
use App\Support\TermsContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Sign-up in three steps: details, emailed code, password.
 *
 * No user row exists until all three are done. Half-created accounts that can
 * never sign in are worse than none — they block the email address and look
 * like a working account to everyone reading the table. The pending details
 * live in the session instead.
 */
class SignupFlowController extends Controller
{
    private const SESSION_KEY = 'signup.pending';

    public function create(Request $request): View
    {
        return view('auth.signup', [
            'terms' => TermsContent::complete(),
            'pending' => $request->session()->get(self::SESSION_KEY),
            'step' => $this->currentStep($request),
            'resendCooldown' => OtpService::RESEND_COOLDOWN,
        ]);
    }

    /** Step 1 — details and role. Sends the code, creates nothing. */
    public function start(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'mobile' => ['required', 'string', 'max:20', 'unique:users,mobile'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(TermsContent::all()))],
            'accepted_terms' => ['accepted'],
        ], [
            'accepted_terms.accepted' => __('Please read and accept the terms for your role.'),
        ]);

        $result = $otp->issue($data['email'], 'signup', $request->ip());

        if ($result['status'] !== 'sent') {
            return response()->json([
                'ok' => false,
                'message' => $result['detail'],
            ], $result['status'] === 'provider_not_configured' ? 503 : 502);
        }

        $request->session()->put(self::SESSION_KEY, [
            'name' => $data['name'],
            'mobile' => $data['mobile'],
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'accepted_terms_at' => now()->toIso8601String(),
            'verified' => false,
        ]);

        return response()->json([
            'ok' => true,
            'step' => 2,
            'email' => $data['email'],
            'message' => $result['detail'],
            'expires_in' => $result['expires_in'],
        ]);
    }

    /** Step 2 — the emailed code. */
    public function verify(Request $request, OtpService $otp): JsonResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! $pending) {
            return response()->json(['ok' => false, 'message' => __('Start again — that sign-up has expired.'), 'restart' => true], 419);
        }

        $code = (string) $request->validate(['code' => ['required', 'digits:6']])['code'];
        $result = $otp->verify($pending['email'], 'signup', $code);

        if ($result['status'] !== 'verified') {
            return response()->json(['ok' => false, 'message' => $result['detail']], 422);
        }

        $pending['verified'] = true;
        $request->session()->put(self::SESSION_KEY, $pending);

        return response()->json(['ok' => true, 'step' => 3, 'message' => $result['detail']]);
    }

    public function resend(Request $request, OtpService $otp): JsonResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! $pending) {
            return response()->json(['ok' => false, 'message' => __('Start again — that sign-up has expired.'), 'restart' => true], 419);
        }

        $result = $otp->issue($pending['email'], 'signup', $request->ip());

        return response()->json([
            'ok' => $result['status'] === 'sent',
            'message' => $result['detail'],
        ], $result['status'] === 'sent' ? 200 : 502);
    }

    /** Step 3 — password, and only now does the account exist. */
    public function complete(Request $request, AccountProvisioner $provisioner, AuditLogger $audit, OtpService $otp): RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        // Trust the server's record of the verification, never a flag the
        // browser sends back.
        if (! $pending || ! ($pending['verified'] ?? false) || ! $otp->wasRecentlyVerified($pending['email'], 'signup')) {
            return redirect()->route('register')->withErrors([
                'email' => __('That sign-up expired before it finished. Please start again.'),
            ]);
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        if (User::query()->where('email', $pending['email'])->exists()) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('login')->withErrors([
                'login' => __('An account already exists for that email. Please sign in.'),
            ]);
        }

        $user = User::query()->create([
            'name' => $pending['name'],
            'email' => $pending['email'],
            'mobile' => $pending['mobile'],
            'password' => $request->string('password')->toString(),
            'status' => 'active',
        ]);
        // The code proved control of the inbox, so a second round trip would
        // only be theatre.
        $user->forceFill(['email_verified_at' => now()])->save();

        $role = Role::query()->where('slug', $pending['role'])->first();
        if ($role) {
            $user->roles()->attach($role);
            $provisioner->provisionRole($user, $role->slug);
        }

        $audit->record('auth.registered', $user, [
            'role' => $pending['role'],
            'terms_accepted_at' => $pending['accepted_terms_at'] ?? null,
        ], $user->id);

        $request->session()->forget(self::SESSION_KEY);
        Auth::login($user);
        $request->session()->regenerate();
        session(['active_role' => $role?->slug]);

        return redirect()->route('dashboard')->with('status', __('Welcome to Vidlix.'));
    }

    private function currentStep(Request $request): int
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! $pending) {
            return 1;
        }

        return ($pending['verified'] ?? false) ? 3 : 2;
    }
}
