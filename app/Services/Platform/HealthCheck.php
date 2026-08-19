<?php

namespace App\Services\Platform;

use App\Contracts\EmailProviderInterface;
use App\Contracts\InstagramProviderInterface;
use App\Contracts\PaymentProviderInterface;
use App\Contracts\PayoutProviderInterface;
use App\Contracts\PushProviderInterface;
use App\Models\InboundEmailEvent;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * What is actually working right now.
 *
 * Every line here is measured, never assumed. A provider with no credentials
 * reports "not configured" rather than "ok", and a provider that has never
 * been heard from reports that too — an integration that has not spoken in
 * days looks identical to a healthy one until you ask when it last did.
 */
class HealthCheck
{
    /** @return array<int, array{name: string, state: string, detail: string}> */
    public function all(): array
    {
        return [
            $this->database(),
            $this->cache(),
            $this->storage(),
            $this->scheduler(),
            ...$this->providers(),
            $this->webhooks(),
            $this->inboundMail(),
        ];
    }

    /** ok | warn | down | unconfigured */
    private function row(string $name, string $state, string $detail): array
    {
        return ['name' => $name, 'state' => $state, 'detail' => $detail];
    }

    private function database(): array
    {
        try {
            $start = microtime(true);
            DB::select('select 1');
            $ms = round((microtime(true) - $start) * 1000, 1);

            return $this->row('Database', 'ok', "Responded in {$ms} ms.");
        } catch (Throwable $e) {
            return $this->row('Database', 'down', 'Query failed: '.class_basename($e));
        }
    }

    private function cache(): array
    {
        try {
            $token = (string) now()->getTimestampMs();
            Cache::put('health.probe', $token, 10);

            return Cache::get('health.probe') === $token
                ? $this->row('Cache', 'ok', 'Read back what it wrote.')
                : $this->row('Cache', 'warn', 'Wrote a value but read back something else.');
        } catch (Throwable $e) {
            return $this->row('Cache', 'down', 'Store unavailable: '.class_basename($e));
        }
    }

    private function storage(): array
    {
        $disk = config('filesystems.default');

        try {
            $probe = 'health/probe-'.now()->getTimestampMs().'.txt';
            Storage::disk($disk)->put($probe, 'ok');
            $exists = Storage::disk($disk)->exists($probe);
            Storage::disk($disk)->delete($probe);

            return $exists
                ? $this->row('Object storage', 'ok', "Wrote and deleted a probe on the {$disk} disk.")
                : $this->row('Object storage', 'down', "Wrote to {$disk} but the file was not there.");
        } catch (Throwable $e) {
            return $this->row('Object storage', 'down', "The {$disk} disk rejected a write: ".class_basename($e));
        }
    }

    private function scheduler(): array
    {
        $last = Cache::get('scheduler.last_run_at');

        if ($last === null) {
            // Worth stating plainly: this host has no cron, so the scheduler
            // only runs when something calls the HTTP trigger.
            return $this->row('Scheduler', 'warn', 'Has never run. Nothing is calling the HTTP trigger.');
        }

        $minutes = now()->diffInMinutes($last, true);

        return $minutes > 30
            ? $this->row('Scheduler', 'warn', "Last ran {$minutes} minutes ago.")
            : $this->row('Scheduler', 'ok', "Last ran {$minutes} minutes ago.");
    }

    /** @return array<int, array{name: string, state: string, detail: string}> */
    private function providers(): array
    {
        $rows = [];

        foreach ([
            'Payments' => PaymentProviderInterface::class,
            'Payouts' => PayoutProviderInterface::class,
            'Email' => EmailProviderInterface::class,
            'Instagram' => InstagramProviderInterface::class,
            'Push' => PushProviderInterface::class,
        ] as $label => $contract) {
            $provider = app($contract);

            $rows[] = $provider->isConfigured()
                ? $this->row($label, 'ok', 'Configured: '.$provider->name().'.')
                : $this->row($label, 'unconfigured', 'No credentials. Nothing is reported as confirmed.');
        }

        return $rows;
    }

    private function webhooks(): array
    {
        $last = WebhookLog::query()->latest('id')->first();

        if ($last === null) {
            return $this->row('Webhooks', 'warn', 'No provider has ever called us.');
        }

        return $this->row(
            'Webhooks',
            'ok',
            'Last received '.$last->created_at?->diffForHumans().' from '.$last->provider.'.',
        );
    }

    private function inboundMail(): array
    {
        $last = InboundEmailEvent::query()->latest('id')->first();

        return $last === null
            ? $this->row('Inbound mail', 'warn', 'No inbound mail has ever arrived.')
            : $this->row('Inbound mail', 'ok', 'Last message '.$last->created_at?->diffForHumans().'.');
    }
}
