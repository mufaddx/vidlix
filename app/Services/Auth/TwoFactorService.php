<?php

namespace App\Services\Auth;

use App\Models\TwoFactorRecoveryCode;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Time-based second factor.
 *
 * The secret is encrypted rather than hashed, because verifying a TOTP needs
 * the secret itself. Recovery codes are hashed like passwords, since those
 * only ever need comparing.
 *
 * Enrolment is two steps on purpose: a secret is stored the moment it is
 * generated, but two_factor_confirmed_at stays null until the member proves
 * they can actually produce a code. Anyone locked out by a half-finished
 * enrolment would have had no way back in.
 */
class TwoFactorService
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private Google2FA $google2fa) {}

    public function isEnabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null;
    }

    /** Start enrolment: a fresh secret, not yet in force. */
    public function beginEnrolment(User $user): string
    {
        $secret = $this->google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => null,
        ])->save();

        return $secret;
    }

    public function otpauthUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            (string) config('app.name'),
            $user->email,
            $secret,
        );
    }

    /**
     * Finish enrolment. Returns the recovery codes in plain text once - this
     * is the only moment they exist unhashed.
     *
     * @return array<int, string>
     */
    public function confirm(User $user, string $code): array
    {
        if (! $this->verifyTotp($user, $code)) {
            return [];
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $this->regenerateRecoveryCodes($user);
    }

    /** @return array<int, string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        TwoFactorRecoveryCode::query()->where('user_id', $user->id)->delete();

        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $code = Str::lower(Str::random(5).'-'.Str::random(5));
            $codes[] = $code;

            TwoFactorRecoveryCode::query()->create([
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
            ]);
        }

        return $codes;
    }

    /** A six-digit app code, or an unused recovery code. */
    public function verify(User $user, string $code): bool
    {
        $code = trim($code);

        return $this->verifyTotp($user, $code) || $this->consumeRecoveryCode($user, $code);
    }

    public function disable(User $user): void
    {
        TwoFactorRecoveryCode::query()->where('user_id', $user->id)->delete();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function unusedRecoveryCodeCount(User $user): int
    {
        return TwoFactorRecoveryCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->count();
    }

    private function verifyTotp(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        try {
            $secret = Crypt::decryptString($user->two_factor_secret);
        } catch (\Throwable) {
            return false;
        }

        // One window either side, so a clock a few seconds out does not lock
        // somebody out of their own account.
        return (bool) $this->google2fa->verifyKey($secret, $code, 1);
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $candidates = TwoFactorRecoveryCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($code, $candidate->code_hash)) {
                // Marked rather than deleted, so the member can see that one
                // was spent instead of silently having fewer than they think.
                $candidate->update(['used_at' => now()]);

                return true;
            }
        }

        return false;
    }
}
