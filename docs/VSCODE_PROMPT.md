You are continuing the Vidlix marketplace already built in this repo. Do not rebuild from scratch. Read `docs/ARCHITECTURE.md`, `docs/VSCODE_HANDOFF.md`, `docs/RUN.md`, and `docs/DEPLOYMENT_HOSTINGER.md` first.

Product: Creator × Brand × Editor × Manager collaboration marketplace.
Stack: Laravel 13 + MySQL (local SQLite OK) + Blade website + Sanctum `/api/v1` + Flutter in `mobile/`.
Host: Hostinger. Document root must be `public/`.

NON-NEGOTIABLES
- Never fake payment success, wallet balances, Instagram analytics, or outbound email “sent”.
- Missing credentials = explicit `PROVIDER_NOT_CONFIGURED`. UI must not show paid / insights / email-sent until a real provider + signed webhook (or authoritative API) confirms.
- Ledger is append-only source of truth. UI balances are derived only.
- Instagram = official Meta APIs only. No scraping.
- Manager actions store `actor_user_id` + `acting_for_creator_id`. Never trust client-supplied `creator_id`.
- Videos/files in object storage; MySQL stores keys/metadata only.
- Public form builder cannot inject HTML/JS/CSS.

ALREADY DONE (do not rewrite)
Website landing/footer/CMS, auth/RBAC/workspace, creator public URL + inquiry inbox, editor/brand verify, campaigns/applications/proposals/projects/chat/managers/invoices/disputes/support/ledger/withdrawals, admin CMS, webhook ingest (HMAC + idempotency), feature tests, Flutter shell (login, creators, campaigns, inbox).
Providers are stubs: `UnconfiguredPaymentProvider`, `UnconfiguredEmailProvider`, `UnconfiguredInstagramProvider` bound in `app/Providers/AppServiceProvider.php`.

YOUR JOB — connect real APIs and remaining product work

1) Payments + payouts
- Implement `PaymentProviderInterface` (Razorpay INR unless .env says otherwise).
- Env: `PAYMENT_PROVIDER`, `PAYMENT_KEY_ID`, `PAYMENT_KEY_SECRET`, `PAYMENT_WEBHOOK_SECRET`, `PAYOUT_WEBHOOK_SECRET`.
- Webhooks: `POST /webhooks/payment`, `POST /webhooks/payout`.
- Checkout returns provider URL. Settlement only after signed webhook. Browser redirect is not success.
- Swap bind in AppServiceProvider. Keep tests honest.

2) Email outbound + inbound
- Implement `EmailProviderInterface` (SendGrid / SES / Postmark).
- Env: `EMAIL_PROVIDER`, `EMAIL_API_KEY`, `EMAIL_WEBHOOK_SECRET`, Laravel `MAIL_*`.
- Webhook: `POST /webhooks/email/inbound`.
- Unmatched inbound → `inbound_email_events`, never guess into a private inbox.

3) Instagram (Meta)
- Implement `InstagramProviderInterface` with Instagram Graph + Facebook Login for Business.
- Env: `INSTAGRAM_PROVIDER`, `META_APP_ID`, `META_APP_SECRET`, `META_REDIRECT_URI`, `META_WEBHOOK_VERIFY_TOKEN`.
- Webhooks: `GET+POST /webhooks/meta`.
- Empty insights until a real sync. Token expiry asks reconnect.

4) Object storage
- S3-compatible (AWS / R2 / Spaces) via `FILESYSTEM_DISK` + `AWS_*`. No videos in MySQL.

5) Optional later: FCM/APNs push, realtime broadcast (currently log).

6) Flutter `mobile/`: extend beyond the thin shell to match web workspace (projects, apply, earnings, managers) using only `/api/v1`. Add API routes if needed. Android emulator base URL `http://10.0.2.2:8000`, iOS/web `http://127.0.0.1:8000`.

7) Hostinger production: MySQL, SSL, queue worker, cron `schedule:run`, change admin password, counsel-approved CMS legal pages.

Existing Vidlix API (already used by Flutter): `/api/v1/auth/register|login|logout`, `/me`, `/creators`, `/editors`, `/campaigns`, `/campaigns/{id}/apply`, `/payments/create`, `/projects`, `/inbox`, `/conversations/{uuid}/messages`.

Local: `php artisan migrate:fresh --seed` then `php artisan serve`.
Tests: `php artisan test`. Do not weaken tests to fake providers.

Work in small diffs. Do not commit secrets. Ask before destructive git. Keep cream/ink UI (not colorful). Mobile: single column except footer two-columns, List your profile + Browse campaigns two-columns, Creators/Editors/Brands/Managers two-columns; footer has no boxes.
