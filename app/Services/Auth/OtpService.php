<?php

namespace App\Services\Auth;

use App\Models\OtpVerification;
use App\Services\Email\SystemMailer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Real one-time codes. Nothing here pretends.
 *
 * A code is only "sent" if the provider accepted it; with no email provider
 * configured the caller is told so plainly rather than being shown a
 * verification screen that can never succeed. The code is stored hashed, dies
 * after ten minutes, is single-use, and allows five wrong guesses before it is
 * burned — a six-digit code with unlimited attempts is not a secret.
 */
class OtpService
{
    public const TTL_SECONDS = 600;

    public const MAX_ATTEMPTS = 5;

    /** How long the UI should keep "Resend" disabled. */
    public const RESEND_COOLDOWN = 30;

    public function __construct(private SystemMailer $mailer) {}

    /**
     * @return array{status: string, detail: string, expires_in: int}
     */
    public function issue(string $identifier, string $purpose, ?string $ip = null): array
    {
        $identifier = strtolower(trim($identifier));

        // Two limits: one per destination so a single address cannot be
        // bombarded, one per IP so a script cannot walk a list of addresses.
        $this->throttle('otp-send:'.$purpose.':'.$identifier, 5, 600);
        if ($ip) {
            $this->throttle('otp-send-ip:'.$ip, 20, 600);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Supersede anything still outstanding for this destination and purpose,
        // so an older code cannot be used after a resend.
        OtpVerification::query()
            ->where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $verification = OtpVerification::query()->create([
            'identifier' => $identifier,
            'channel' => 'email',
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
            'request_ip' => $ip,
        ]);

        $result = $this->mailer->send(
            $identifier,
            $this->subjectFor($purpose),
            $this->bodyFor($purpose, $code),
        );

        if ($result['status'] !== 'accepted') {
            // Nothing was delivered, so do not leave a live code implying it was.
            $verification->update(['consumed_at' => now()]);

            return [
                'status' => $result['status'],
                'detail' => $result['status'] === 'provider_not_configured'
                    ? __('No email provider is configured, so a code cannot be sent. Nothing was created.')
                    : __('The code could not be sent. Please try again in a moment.'),
                'expires_in' => 0,
            ];
        }

        return [
            'status' => 'sent',
            'detail' => __('A six-digit code is on its way to :identifier.', ['identifier' => $identifier]),
            'expires_in' => self::TTL_SECONDS,
        ];
    }

    /**
     * Check a code. A correct code is consumed immediately so it cannot be
     * replayed, and a wrong one costs an attempt.
     *
     * @return array{status: string, detail: string}
     */
    public function verify(string $identifier, string $purpose, string $code): array
    {
        $identifier = strtolower(trim($identifier));
        $this->throttle('otp-check:'.$purpose.':'.$identifier, 10, 600);

        $verification = OtpVerification::query()
            ->where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $verification) {
            return ['status' => 'not_found', 'detail' => __('Ask for a new code — this one is no longer valid.')];
        }

        if ($verification->expires_at->isPast()) {
            $verification->update(['consumed_at' => now()]);

            return ['status' => 'expired', 'detail' => __('That code has expired. Ask for a new one.')];
        }

        if ($verification->attempts >= self::MAX_ATTEMPTS) {
            $verification->update(['consumed_at' => now()]);

            return ['status' => 'too_many_attempts', 'detail' => __('Too many wrong attempts. Ask for a new code.')];
        }

        if (! Hash::check($code, $verification->code_hash)) {
            $verification->increment('attempts');

            return [
                'status' => 'incorrect',
                'detail' => __('That code is not right. :left attempts left.', [
                    'left' => max(0, self::MAX_ATTEMPTS - $verification->attempts),
                ]),
            ];
        }

        $verification->update(['consumed_at' => now()]);

        return ['status' => 'verified', 'detail' => __('Verified.')];
    }

    /** True when a code for this destination was verified in the last 30 minutes. */
    public function wasRecentlyVerified(string $identifier, string $purpose): bool
    {
        return OtpVerification::query()
            ->where('identifier', strtolower(trim($identifier)))
            ->where('purpose', $purpose)
            ->whereNotNull('consumed_at')
            ->where('consumed_at', '>=', now()->subMinutes(30))
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->exists();
    }

    private function throttle(string $key, int $max, int $decay): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                'code' => __('Too many attempts. Try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        RateLimiter::hit($key, $decay);
    }

    private function subjectFor(string $purpose): string
    {
        return $purpose === 'password_reset'
            ? __('Your Vidlix password reset code')
            : __('Your Vidlix sign-up code');
    }

    private function bodyFor(string $purpose, string $code): string
    {
        $action = $purpose === 'password_reset'
            ? __('reset your Vidlix password')
            : __('finish creating your Vidlix account');

        return __("Your code is :code\n\nEnter it to :action. It expires in 10 minutes and can be used once.\n\nIf you did not request this, you can ignore this email — the code is useless without access to this inbox.\n\nThis message was sent by Vidlix. Please do not reply; replies to this address are not read.", [
            'code' => $code,
            'action' => $action,
        ]);
    }
}
