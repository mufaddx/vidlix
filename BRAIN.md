# BRAIN.md — Vidlix persistent project memory

> Read this file first, before opening anything else. It exists so an assistant
> can work on Vidlix without re-reading the repository. Update it only when
> important project knowledge changes — not for routine edits.

---

## 1. What Vidlix is

A Creator × Brand × Editor × Manager collaboration marketplace.
Live at **https://vidlix.in** (Hostinger shared hosting). Repo:
`https://github.com/mufaddx/vidlix`, branch `main`.

Stack: **Laravel 13** + MySQL/MariaDB + Blade website + Sanctum `/api/v1` +
**Flutter** client in `mobile/`. Local dev uses SQLite.

---

## 2. Non-negotiable product rules

These are architectural constraints, not preferences. Tests enforce them; do not
weaken a test to make a provider look configured.

1. Never fake payment success, wallet balances, Instagram analytics, or
   outbound email "sent" state.
2. Missing credentials → explicit `PROVIDER_NOT_CONFIGURED`. The UI must say
   what is unavailable rather than showing a zero or a fake success.
3. The ledger is **append-only**. Corrections are reversing entries. Every
   balance shown is a `SUM` over entries; no balance column exists.
4. Payment settlement needs **both** a verified webhook signature **and** the
   provider API confirming it. A browser redirect is never authoritative.
5. Instagram uses **official Meta Graph API only**. Scraping is forbidden.
6. **Nobody applies to be a manager.** A manager exists only because an account
   holder appointed them, or because Vidlix provided one. Managers are checked
   against `manager_assignments` on every request; the session holds ids only,
   never a permission, so a tampered session grants nothing.
7. Media lives in object storage; MySQL stores keys and metadata only.
8. The public form builder cannot inject HTML/JS/CSS.

---

## 3. Architecture map

```
app/
  Contracts/            Provider interfaces (Payment, Payout, Email, Instagram, Push)
  Services/
    Integrations/       Live adapters + Unconfigured* fallbacks
      Payments/         RazorpayPaymentProvider, RazorpayXPayoutProvider
      Email/            SendGridEmailProvider, SmtpEmailProvider
      Instagram/        MetaInstagramProvider
      Push/             FcmPushProvider
    Payments/           PaymentSettlementService  ← the only place money settles
    Ledger/             LedgerService             ← append-only, idempotent
    Email/              Inbound normalizer/ingestor, Outbound, DeliveryEventHandler
    Instagram/          MetaEventHandler
    Media/              MediaStorage              ← object storage keys
    Webhooks/           WebhookProcessor, SignatureVerifier, WebhookDispatcher
    Managers/           ManagerDirectory          ← appointing / activating managers
    Workspace/          WorkspaceContext          ← who you are acting as
    Marketplace/        MarketplaceEngine         ← the big domain service
  Http/Controllers/
    Api/V1/             AuthController, MarketplaceController, WorkspaceApiController
    App/                WorkspaceController (large), InboxController, InstagramController
    Admin/              AdminDashboardController, AdminOpsController
    Webhooks/           WebhookController
```

**Provider binding**: `app/Providers/AppServiceProvider.php`. Driver name comes
from `.env`; if credentials are missing it falls back to the `Unconfigured*`
adapter, so a typo degrades to `PROVIDER_NOT_CONFIGURED` rather than a
half-working integration.

---

## 4. Key files

| File | Why it matters |
| --- | --- |
| `app/Services/Marketplace/MarketplaceEngine.php` | Most domain logic. Large — grep, don't read whole. |
| `app/Services/Payments/PaymentSettlementService.php` | Only path that captures money |
| `app/Services/Ledger/LedgerService.php` | Append-only ledger, uuid5 idempotency |
| `app/Services/Webhooks/SignatureVerifier.php` | Per-provider signature schemes |
| `app/Services/Workspace/WorkspaceContext.php` | Account switcher + manager authorisation |
| `app/Services/Managers/ManagerDirectory.php` | Manager invite / activate / revoke |
| `config/vidlix.php` | Every provider/token setting |
| `routes/web.php`, `routes/api.php` | Full route surface |
| `public/css/app.css` | Entire design system, tokenised, light+dark |
| `bootstrap/app.php` | Middleware, CSRF exceptions, JSON exception shaping |

---

## 5. Providers and their state

| Slot | Driver | Live keys? | Webhook |
| --- | --- | --- | --- |
| Payments | `razorpay` | **test keys live on server**; webhook secret still missing | `POST /webhooks/payment` |
| Payouts | `razorpayx` | not set up | `POST /webhooks/payout` |
| Email | **`resend`** (chosen) /`sendgrid`/`smtp`/`ses`/`postmark` | key issued | `/webhooks/email/inbound`, `/webhooks/email/events` |
| Instagram | `meta` | none | `GET+POST /webhooks/meta` |
| Storage | **Cloudflare R2** | live, verified | — |
| Push | `fcm` | none | — |

Verify storage with `php artisan vidlix:storage-check` — it writes, reads,
signs, fetches and deletes a probe object.

All default to `unconfigured`. Detail: `docs/INTEGRATIONS.md`.

---

## 6. Current progress

**Done**: marketplace engines, live provider adapters, `/api/v1`, Flutter client
(6 tabs), CMS website, admin panel, light/dark theming, deployed and live on
Hostinger with MySQL + SSL.

**Outstanding (operational, not code)**:
- Razorpay webhook secret, RazorpayX activation, Meta App Review.
- Legal CMS pages (`/p/terms`, `/p/privacy`, …) still contain placeholder text.
- Creator payout bank-account onboarding UI does not exist; `payout_accounts`
  is admin/manual.
- Editor/brand **category taxonomy** not built yet (admin list + custom, agreed).
  `editor_profiles.specializations` and `brand_profiles.industry` are still free
  text and cannot be filtered.
- Signup still asks for a role. Agreed target: create the account first, then
  apply as editor or brand afterwards.

---

## 7. Known issues

| Issue | Severity | Note |
| --- | --- | --- |
| Coarse admin gate | High | `EnsureAdmin` lets any of 6 roles reach every admin route, including payout approval. `permissions` table exists but is never checked. |
| Sanctum tokens never expire, no abilities | Medium | `config/sanctum.php` `expiration => null`; `createToken('api')` grants `*`. |
| `SESSION_SECURE_COOKIE` unset in production | Medium | Session cookie lacks the `Secure` flag on an HTTPS site. |
| No throttle on authenticated POST routes | Low | Only login/register/public-form/api/webhooks are throttled. |
| Upload extension not allowlisted | Low | MIME is content-sniffed (safe), but the stored extension is client-controlled. |
| `resources/views/app/simple-list.blade.php` | Low | Dead view containing `{!! $slot !!}`. Unreferenced; delete it. |
| `gradle-wrapper.jar` gitignored | Info | Fresh clones may need `flutter create --platforms=android .` |

`database/seeders/DatabaseSeeder.php` and `tests/Feature/MarketplaceFoundationTest.php`
fail `pint --test` (pre-existing, untouched).

---

## 8. Important technical decisions

- **Hostinger runs PHP 8.3 by default but the vendor tree needs 8.4.1+**
  (Symfony 8.1). The panel exposes no PHP selector, so `public/.htaccess` pins
  the interpreter via `AddHandler application/x-httpd-alt-php84___lsphp .php`.
  *Symptom if this breaks: bare 500 with an empty `storage/logs`* — Composer's
  platform check fires before Laravel can log. **CLI and web PHP differ**;
  artisan can work while the site 500s.
- On the server, always invoke `/opt/alt/php84/usr/bin/php`, not bare `php`.
- `storage:link` fails on Hostinger (`symlink` is in `disable_functions`). Use
  `ln -s` from the shell instead.
- **Button variants may only reassign `--btn-*` custom properties.** Setting a
  real property on `.btn.secondary` ties with `.btn:hover` on specificity and
  wins by source order, which previously left white text on a transparent
  background — invisible. Enforced by `ThemeAndButtonContrastTest`.
- The dark palette is written twice (media query + `[data-theme="dark"]`) because
  CSS cannot share a block between them. A test asserts the two stay in sync.
- Resend signs webhooks with **Svix**, not a plain HMAC: the signed content is
  `{svix-id}.{svix-timestamp}.{raw body}`, HMAC-SHA256 with the base64-decoded
  `whsec_` secret, base64 encoded, and the header may hold several
  space-separated `v1,<sig>` values. A 5-minute timestamp window rejects replays.
- Cloudflare R2 rejects the `x-amz-checksum-*` headers that recent AWS SDK
  versions attach by default, so `config/filesystems.php` pins
  `request_checksum_calculation` / `response_checksum_validation` to
  `when_required`. Works for real S3 too.
- Manager scope is one of creator | brand | editor, held in
  `manager_assignments`. `source` is `owner` or `company` — the UI must say when
  Vidlix provided the manager. An invitation link can create a *new* account and
  set its password, but can never touch an existing account's password.
- Rejected webhooks are logged under a throwaway id so a forged event cannot
  occupy the unique `provider_event_id` slot and suppress the genuine delivery.

---

## 9. Commands

```bash
# Local
php artisan migrate:fresh --seed && php artisan serve   # http://127.0.0.1:8000
php artisan test                                        # 52 tests
php vendor/bin/pint                                     # format
cd mobile && flutter run                                # Android emu: 10.0.2.2:8000

# Server (SSH: u324559756@145.223.17.150 -p 65002, app at ~/vidlix)
cd ~/vidlix && git pull origin main
/opt/alt/php84/usr/bin/php artisan config:cache
/opt/alt/php84/usr/bin/php artisan view:cache

# This plan has NO cron (no crontab, no spool, no systemd, no at). The
# scheduler is driven over HTTP instead, every minute, by an external service:
#   POST https://vidlix.in/api/internal/scheduler/run
#   Header: X-Cron-Token: <CRON_TOKEN from .env>
# The queue drain lives in routes/console.php, so schedule:run covers both.
```

Local admin: `admin@vidlix.test` / `ChangeMe_Admin1`.
Live admin: `mufaddx@gmail.com` (password was rotated; not recorded here).

---

## 10. Security rules for anyone working here

- **Never** print, commit, or upload `.env` contents, API keys, tokens, private
  keys, or database passwords. `.env` is gitignored — keep it that way.
- Before pushing, scan staged content for secrets, not just filenames.
- Do not commit generated security reports, `strix_runs/`, or scan artefacts.
- Do not test or scan anything outside this project. Do not scan the production
  domain without explicit per-run authorisation.
- Webhook endpoints are CSRF-exempt **by design** — they authenticate with HMAC
  signatures instead. Do not add other paths to that exemption list.
- Any new admin capability needs a real permission check, not just the coarse
  `admin` middleware.

---

## 11. Ignore these paths

Do not read or search unless the task specifically requires them:

```
vendor/            node_modules/      storage/logs/     storage/framework/
bootstrap/cache/   public/build/      public/css/*.map
mobile/build/      mobile/.dart_tool/ mobile/android/.gradle/  mobile/ios/Pods/
composer.lock      package-lock.json  *.sqlite          strix_runs/
```

`composer.lock` is large; check `composer.json` instead unless resolving a
version conflict.

---

## 12. Working efficiently here

- Start from this file; open only what the task needs.
- Prefer `rg`/`grep` over reading whole files. `MarketplaceEngine.php` and
  `WorkspaceController.php` are both large — always grep them.
- Do not re-read a file you have already read this session unless it changed.
- Keep responses short; the user reads Hinglish comfortably and prefers direct
  answers over long preambles.
