# Run locally

1. PHP 8.4+ is required (Composer optional: `php C:\Users\themu\composer.phar`).
2. Copy `.env.example` to `.env` if needed, then `php artisan key:generate`.
3. Local default is SQLite (`database/database.sqlite`). For MySQL, set `DB_CONNECTION=mysql` and credentials.
4. `php artisan migrate:fresh --seed`
5. `php artisan serve`
6. Open http://127.0.0.1:8000

Seeded accounts (local only):

- Admin: `admin@vidlix.test` / `ChangeMe_Admin1`
- Demo creator: `creator@vidlix.test` / `Creator_Pass1`
- Public page: http://127.0.0.1:8000/u/mursalim

Queues: `php artisan queue:work` — outbound email, Instagram syncs and push all
run here. Without a worker a reply stays `queued` and is never sent.
Scheduler: `php artisan schedule:work`

Tests: `php artisan test`
Formatting: `php vendor/bin/pint`

## Providers in development

Every external provider is off by default and reports
`PROVIDER_NOT_CONFIGURED`. That is the correct local state: the UI will say what
is unavailable instead of showing an invented balance, insight, or "sent" flag.

To exercise one locally, put its keys in `.env` and set the driver name — see
`docs/INTEGRATIONS.md` for the full table. To deliver a webhook to a machine
behind NAT, tunnel it (`cloudflared`, `ngrok`) and register the public URL with
the provider; the signature must still verify against your local secret.

The adapters are covered by tests that fake the provider HTTP layer, so
`php artisan test` exercises the real settlement, routing and sync logic without
any credentials.

## Browser tests

`php artisan test` cannot see JavaScript, so the reveal button, the checkbox
sizing, the hover states and the page layout are covered by Playwright instead:

```
npm install
npx playwright install chromium
php artisan key:generate --env=e2e --force
touch database/e2e.sqlite && php artisan migrate --env=e2e --force
npm run e2e
```

Laravel Dusk is not used because it requires guzzle ^7.5 and this project runs
guzzle 8; downgrading a library the application depends on in order to install a
test tool is the wrong way round.

`.env.e2e` is committed and deliberately holds no provider credentials, so a
browser run cannot reach a real payment, email or Meta API. It also ships with
an empty `APP_KEY` — generate one locally with the command above. That write
makes the file look modified forever, so mark it ignored in your checkout:

```
git update-index --skip-worktree .env.e2e
```

These tests earn their keep: the first run found a cache bug that made every
page 500 under the file and database cache drivers, which no in-process test
could have caught.

## What is and is not finished

The marketplace engines, the live provider adapters, the `/api/v1` surface and
the Flutter client are all implemented, along with feature switches, a
maintenance gate, a measured health page, two-factor authentication, device
notifications with per-event preferences, Turnstile on the public forms and
daily reminders.

What remains is operational rather than code: provider accounts and KYC, the
Razorpay webhook secret (without it no payment can be confirmed), RazorpayX
activation, Meta app review, email domain DNS, and the Hostinger production
checklist in `docs/DEPLOYMENT_HOSTINGER.md`.
