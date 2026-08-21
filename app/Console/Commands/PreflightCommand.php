<?php

namespace App\Console\Commands;

use App\Services\Domains\Hostname;
use App\Services\Platform\HealthCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Is this installation actually ready to face the public?
 *
 * Every check either passes or explains itself. The point is to fail here, on
 * a server, before a real person meets the same problem — a launch checklist
 * somebody reads is a launch checklist somebody skips a line of.
 *
 * Split into two kinds. **Blockers** are things that make the site unsafe or
 * broken: debug on in production, uploads landing on the web server's disk, a
 * missing key. **Notes** are features that are simply off, which is a
 * legitimate way to launch — the interface says so rather than pretending.
 */
class PreflightCommand extends Command
{
    protected $signature = 'vidlix:preflight';

    protected $description = 'Check whether this installation is ready to serve the public';

    /** @var list<string> */
    private array $blockers = [];

    /** @var list<string> */
    private array $notes = [];

    public function handle(HealthCheck $health): int
    {
        $this->components->info('Vidlix preflight');

        $this->checkApplication();
        $this->checkDatabase();
        $this->checkStorage();
        $this->checkDomains();
        $this->checkEmail();
        $this->checkOptional();
        $this->reportHealth($health);

        $this->newLine();

        foreach ($this->notes as $note) {
            $this->components->warn($note);
        }

        if ($this->blockers === []) {
            $this->newLine();
            $this->components->info('No blockers. Anything above is a feature that is switched off, not a fault.');

            return self::SUCCESS;
        }

        $this->newLine();

        foreach ($this->blockers as $blocker) {
            $this->components->error($blocker);
        }

        return self::FAILURE;
    }

    private function checkApplication(): void
    {
        $this->line('<comment>Application</comment>');

        $this->assert(
            'APP_KEY is set',
            filled(config('app.key')),
            'APP_KEY is empty. Sessions and encrypted tokens cannot work. Run: php artisan key:generate',
        );

        $production = config('app.env') === 'production';

        $this->assert('APP_ENV is production', $production, null, ! $production
            ? 'APP_ENV is "'.config('app.env').'". Set it to production before launch.'
            : null);

        $this->assert(
            'APP_DEBUG is off',
            ! config('app.debug'),
            'APP_DEBUG is on. Every error page would show stack traces, queries and environment values to the public.',
        );
    }

    private function checkDatabase(): void
    {
        $this->line('<comment>Database</comment>');

        try {
            DB::connection()->getPdo();
            $this->pass('connects');
        } catch (Throwable $e) {
            $this->refuse('connects', 'The database is unreachable: '.$e->getMessage());

            return;
        }

        try {
            $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))
                ->keys()
                ->diff(app('migrator')->getRepository()->getRan())
                ->count();

            $this->assert(
                'migrations are up to date',
                $pending === 0,
                $pending.' migration(s) have not run. Run: php artisan migrate --force',
            );
        } catch (Throwable) {
            $this->note('Could not read the migration state.');
        }
    }

    private function checkStorage(): void
    {
        $this->line('<comment>Storage</comment>');

        $disk = (string) config('vidlix.media.disk');

        $this->assert(
            'media is on object storage',
            $disk !== 'local',
            'MEDIA_DISK/FILESYSTEM_DISK is "local". Uploaded video would be written to the web server\'s own disk, '
            .'which does not survive a redeploy and is not what signed URLs are for. Set it to s3.',
        );

        if ($disk === 'local') {
            return;
        }

        try {
            // A write and a delete, because "configured" and "works" are not
            // the same thing and only one of them matters at 3am.
            $key = 'preflight/'.uniqid().'.txt';
            Storage::disk($disk)->put($key, 'preflight');
            $exists = Storage::disk($disk)->exists($key);
            Storage::disk($disk)->delete($key);

            $this->assert('the bucket accepts a write', $exists, 'The bucket did not accept a test write.');
        } catch (Throwable $e) {
            $this->refuse('the bucket accepts a write', 'Object storage rejected a test write: '.$e->getMessage());
        }
    }

    private function checkDomains(): void
    {
        $this->line('<comment>Domains</comment>');

        $hosts = [];

        foreach (['site', 'app', 'autodm', 'admin'] as $key) {
            $url = (string) config('vidlix.domains.'.$key);
            $host = parse_url($url, PHP_URL_HOST);

            $this->assert(
                $key.' is configured',
                is_string($host) && $host !== '',
                'The '.$key.' domain is not set.',
            );

            if (is_string($host)) {
                $hosts[] = $host;
            }
        }

        // Four identical hosts almost always means only APP_URL was filled in,
        // which works locally and breaks the moment somebody shares a link.
        $this->assert(
            'the four hosts are distinct',
            count(array_unique($hosts)) === count($hosts),
            null,
            count(array_unique($hosts)) !== count($hosts)
                ? 'Two or more of the four domains are the same host. Public links will point at the wrong face of the product.'
                : null,
        );

        foreach ($hosts as $host) {
            if (str_contains($host, 'example.com') || str_contains($host, 'localhost')) {
                $this->blockers[] = 'A domain still points at '.$host.'.';
            }
        }
    }

    private function checkEmail(): void
    {
        $this->line('<comment>Email</comment>');

        $configured = config('vidlix.providers.email') !== 'unconfigured'
            && filled(config('vidlix.email.api_key'));

        if (! $configured) {
            $this->note('No email provider. Sign-in codes, inquiry acknowledgements and replies will be stored but not sent.');

            return;
        }

        $this->pass('provider configured');

        $this->assert(
            'inbound domain is set',
            filled(config('vidlix.email.inbound_domain')),
            'EMAIL_INBOUND_DOMAIN is empty, so replies have no routing address to come back to and every '
            .'visitor reply would arrive as a new thread.',
        );

        $this->assert(
            'the webhook secret is set',
            filled(config('vidlix.webhooks.email_secret'))
                || filled(config('vidlix.email.webhook_password')),
            'No email webhook secret. Inbound deliveries cannot be verified and will all be rejected.',
        );

        foreach (['creator', 'editor'] as $scope) {
            $address = (string) config('vidlix.email.identities.'.$scope);

            if (str_contains($address, 'example.com')) {
                $this->blockers[] = 'MAIL_FROM_'.strtoupper($scope).' still points at example.com.';
            }
        }
    }

    private function checkOptional(): void
    {
        $this->line('<comment>Optional</comment>');

        if (blank(config('services.turnstile.secret_key'))) {
            $this->note('Turnstile is not configured. Public forms keep the honeypot and the rate limit, but nothing else.');
        } else {
            $this->pass('Turnstile');
        }

        foreach ([
            'payment' => 'Payments are off. Nothing can be charged or paid out.',
            'instagram' => 'Instagram is off. AutoDM and reach figures are unavailable.',
            'push' => 'Push is off. Notifications are still stored and shown in the app.',
        ] as $provider => $consequence) {
            if (config('vidlix.providers.'.$provider) === 'unconfigured') {
                $this->note($consequence);
            } else {
                $this->pass($provider);
            }
        }

        if (config('vidlix.providers.custom_domains') === 'unconfigured') {
            $this->note('Custom domains are off. The settings page says so rather than accepting one.');
        }

        $_ = Hostname::ourOwnHostnames();
    }

    private function reportHealth(HealthCheck $health): void
    {
        $this->line('<comment>Live checks</comment>');

        foreach ($health->all() as $check) {
            match ($check['state']) {
                'ok' => $this->pass($check['name'].' — '.$check['detail']),

                /*
                 | Only 'down' blocks. A provider with no credentials is a
                 | feature switched off — a legitimate way to launch, and one
                 | the interface already reports honestly. Treating it as a
                 | failure would make preflight cry wolf about every stage of a
                 | phased rollout, which is how a check stops being read.
                 */
                'unconfigured' => $this->note($check['name'].': '.$check['detail']),
                'down' => $this->refuse($check['name'], $check['name'].' is down. '.$check['detail']),
                default => $this->note($check['name'].': '.$check['detail']),
            };
        }
    }

    private function assert(string $label, bool $ok, ?string $blocker = null, ?string $note = null): void
    {
        if ($ok) {
            $this->pass($label);

            return;
        }

        $this->refuse($label, $blocker ?? $note ?? $label, $blocker === null);
    }

    private function pass(string $label): void
    {
        $this->line('  <fg=green>✓</> '.$label);
    }

    private function refuse(string $label, string $detail, bool $asNote = false): void
    {
        $this->line('  <fg=red>✗</> '.$label);

        if ($asNote) {
            $this->notes[] = $detail;
        } else {
            $this->blockers[] = $detail;
        }
    }

    private function note(string $detail): void
    {
        $this->line('  <fg=yellow>○</> '.$detail);
        $this->notes[] = $detail;
    }
}
