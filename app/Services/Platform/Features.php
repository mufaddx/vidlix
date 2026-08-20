<?php

namespace App\Services\Platform;

use App\Models\FeatureFlag;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime switches: what is turned on, and whether the site is open.
 *
 * Reads are cached because they happen on nearly every request; every write
 * clears the cache immediately, so an operator throwing a switch sees it take
 * effect on the next page rather than whenever a TTL happens to expire.
 */
class Features
{
    private const CACHE_KEY = 'platform.switches';

    private const CACHE_TTL = 300;

    public const MAINTENANCE_KEY = 'maintenance_mode';

    public const MAINTENANCE_MESSAGE_KEY = 'maintenance_message';

    /** Flags the product knows about, seeded so operators see them all. */
    public const KNOWN = [
        'public_signup' => ['Public sign-up', 'Turn off to stop new accounts being created.'],
        'campaign_publishing' => ['Campaign publishing', 'Brands can publish new campaigns.'],
        'withdrawals' => ['Withdrawals', 'Members can request a payout.'],
        'public_enquiries' => ['Public enquiry forms', 'Strangers can contact a creator or editor without an account.'],
        'instagram_sync' => ['Instagram sync', 'Fetch insights from the Meta Graph API.'],
        'push_notifications' => ['Push notifications', 'Send device notifications for new messages and project events.'],
    ];

    /**
     * Absence means on.
     *
     * These capabilities already work; the switch exists to turn one off in a
     * hurry. If a missing row meant "off", installing this feature would have
     * silently closed sign-up, withdrawals and every public form the moment it
     * shipped. Only an explicit row can disable something.
     */
    public function enabled(string $key, ?User $user = null): bool
    {
        $flag = $this->all()->get($key);

        if ($flag === null) {
            return true;
        }

        if (! $flag['is_enabled']) {
            return false;
        }

        if ($flag['audience'] === 'staff') {
            return $user?->employee()->exists() ?? false;
        }

        return true;
    }

    public function isUnderMaintenance(): bool
    {
        return $this->setting(self::MAINTENANCE_KEY) === '1';
    }

    public function maintenanceMessage(): string
    {
        return $this->setting(self::MAINTENANCE_MESSAGE_KEY)
            ?: 'Vidlix is briefly closed for maintenance. Nothing you have done is lost.';
    }

    public function setting(string $key): ?string
    {
        return $this->all()->get('setting:'.$key);
    }

    public function putSetting(string $key, ?string $value, ?int $byUserId = null): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by_user_id' => $byUserId],
        );

        $this->forget();
    }

    public function setFlag(string $key, bool $enabled, string $audience, ?int $byUserId = null): void
    {
        FeatureFlag::query()->updateOrCreate(
            ['key' => $key],
            [
                'name' => FeatureFlag::query()->where('key', $key)->value('name') ?: (self::KNOWN[$key][0] ?? $key),
                'description' => self::KNOWN[$key][1] ?? null,
                'is_enabled' => $enabled,
                'audience' => array_key_exists($audience, FeatureFlag::AUDIENCES) ? $audience : 'everyone',
                'updated_by_user_id' => $byUserId,
            ],
        );

        $this->forget();
    }

    /** Every known flag, whether or not a row exists for it yet. */
    public function flags(): Collection
    {
        $stored = FeatureFlag::query()->get()->keyBy('key');

        return collect(self::KNOWN)->map(fn (array $meta, string $key) => [
            'key' => $key,
            'name' => $meta[0],
            'description' => $meta[1],
            'is_enabled' => (bool) ($stored[$key]->is_enabled ?? true),
            'audience' => $stored[$key]->audience ?? 'everyone',
        ]);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * A plain array goes into the cache, never a Collection.
     *
     * The file and database cache stores serialize what they are given, and a
     * serialized Collection read back in a process that has not yet loaded the
     * class comes out as __PHP_Incomplete_Class - every page then 500s. The
     * array driver the test suite uses hands the object straight back, so this
     * only ever broke where it mattered. The browser suite caught it; no
     * in-process test can, because the class is already loaded there.
     *
     * @return Collection<string, mixed>
     */
    private function all(): Collection
    {
        $values = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $values = [];

            foreach (FeatureFlag::query()->get(['key', 'is_enabled', 'audience']) as $flag) {
                $values[$flag->key] = ['is_enabled' => (bool) $flag->is_enabled, 'audience' => $flag->audience];
            }

            foreach (SystemSetting::query()->get(['key', 'value']) as $setting) {
                $values['setting:'.$setting->key] = $setting->value;
            }

            return $values;
        });

        return collect($values);
    }
}
