<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Password reset by emailed code.
 *
 * The first step always answers the same way whether or not the account
 * exists. Telling a stranger "no account with that email" turns this form into
 * a way to enumerate who has signed up.
 */
class PasswordResetFlowController extends Controller
{
    private const SESSION_KEY = 'password_reset.pending';

    public function create(Request $request): View
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        return view('auth.forgot', [
            'step' => $pending ? (($pending['verified'] ?? false) ? 3 : 2) : 1,
            'pending' => $pending,
            'resendCooldown' => OtpService::RESEND_COOLDOWN,
        ]);
    }

    public function start(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:190']]);
        $login = trim($data['login']);

        $user = User::query()->where('email', $login)->orWhere('mobile', $login)->first();

        // Same reply either way, so this cannot be used to discover accounts.
        $neutral = [
            'ok' => true,
            'step' => 2,
            'message' => __('If that matches an account, a six-digit code is on its way.'),
            'expires_in' => OtpService::TTL_SECONDS,
        ];

        if (! $user || ! filled($user->email)) {
            return response()->json($neutral);
        }

        $result = $otp->issue($user->email, 'password_reset', $request->ip());

        if ($result['status'] === 'provider_not_configured') {
            return response()->json([
                'ok' => false,
                'message' => __('Password reset by email is unavailable: no email provider is configured.'),
            ], 503);
        }

        $request->session()->put(self::SESSION_KEY, [
            'email' => strtolower($user->email),
            'masked' => $this->mask($user->email),
            'verified' => false,
        ]);

        return response()->json($neutral + ['masked' => $this->mask($user->email)]);
    }

    public function verify(Request $request, OtpService $otp): JsonResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! $pending) {
            return response()->json(['ok' => false, 'message' => __('Start again — that request has expired.'), 'restart' => true], 419);
        }

        $code = (string) $request->validate(['code' => ['required', 'digits:6']])['code'];
        $result = $otp->verify($pending['email'], 'password_reset', $code);

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
            return response()->json(['ok' => false, 'message' => __('Start again — that request has expired.'), 'restart' => true], 419);
        }

        $result = $otp->issue($pending['email'], 'password_reset', $request->ip());

        return response()->json([
            'ok' => $result['status'] === 'sent',
            'message' => $result['status'] === 'sent'
                ? __('A new code is on its way.')
                : $result['detail'],
        ], $result['status'] === 'sent' ? 200 : 502);
    }

    public function complete(Request $request, AuditLogger $audit, OtpService $otp): RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! $pending || ! ($pending['verified'] ?? false) || ! $otp->wasRecentlyVerified($pending['email'], 'password_reset')) {
            return redirect()->route('password.request')->withErrors([
                'login' => __('That reset expired before it finished. Please start again.'),
            ]);
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        $user = User::query()->where('email', $pending['email'])->firstOrFail();

        DB::transaction(function () use ($user, $request) {
            $user->forceFill(['password' => $request->string('password')->toString()])->save();
            // Every existing session and API token dies with the old password,
            // which is the point of resetting it.
            $user->tokens()->delete();
        });

        $audit->record('auth.password_reset', $user, [], $user->id);
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('login')->with('status', __('Password updated. Sign in with your new password.'));
    }

    /** m•••••x@gmail.com — enough to recognise, not enough to harvest. */
    private function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        if (mb_strlen($local) <= 2) {
            return str_repeat('•', mb_strlen($local)).'@'.$domain;
        }

        return mb_substr($local, 0, 1).str_repeat('•', max(1, mb_strlen($local) - 2)).mb_substr($local, -1).'@'.$domain;
    }
}
