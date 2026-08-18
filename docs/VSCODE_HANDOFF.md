# Vidlix — handoff (kya ho chuka, kya bacha hai)

Project: `C:\Vidlix`
Stack: Laravel 13 website + `/api/v1` + Flutter (`mobile/`)
Rule: **fake payment / fake Instagram numbers / fake "email sent" mat banana.**
Missing keys = `PROVIDER_NOT_CONFIGURED`.

Local run: `php artisan migrate:fresh --seed` then `php artisan serve` → http://127.0.0.1:8000
Mobile: `cd mobile` then `flutter run` (API `http://10.0.2.2:8000` Android emulator, `http://127.0.0.1:8000` iOS/web)
Tests: `php artisan test` · Format: `php vendor/bin/pint`

Demo logins: `admin@vidlix.test` / `ChangeMe_Admin1` · `creator@vidlix.test` / `Creator_Pass1` · public `/u/mursalim`

---

## 1. Jo HO CHUKA hai

### Marketplace (pehle se tha)

- Public website (landing, footer, CMS pages, directories, campaigns, pricing, journal)
- Auth (email/mobile + password, verification, RBAC, workspace switch)
- Creator public page + inquiry → inbox
- Editor/brand apply + admin verify
- Campaigns, applications, proposals, projects/files/revisions
- Internal chat, manager invite/accept/revoke
- Invoices, agreements, disputes, reviews, support tickets
- Ledger + withdrawals (UI derived from ledger only)
- Admin CMS / verification / finance / disputes / tickets
- Flutter shell

### External APIs (ab live adapters hain)

| # | Kya | Driver | `.env` | Webhook |
|---|-----|--------|--------|---------|
| 1 | Payments | `PAYMENT_PROVIDER=razorpay` | `PAYMENT_KEY_ID/SECRET`, `PAYMENT_WEBHOOK_SECRET` | `POST /webhooks/payment` |
| 2 | Payouts | `PAYOUT_PROVIDER=razorpayx` | `PAYOUT_KEY_ID/SECRET`, `PAYOUT_ACCOUNT_NUMBER`, `PAYOUT_WEBHOOK_SECRET` | `POST /webhooks/payout` |
| 3 | Email | `EMAIL_PROVIDER=sendgrid\|smtp\|ses\|postmark` | `EMAIL_API_KEY`, `EMAIL_WEBHOOK_SECRET`, `EMAIL_INBOUND_DOMAIN`, `MAIL_*` | `POST /webhooks/email/inbound`, `POST /webhooks/email/events` |
| 4 | Instagram | `INSTAGRAM_PROVIDER=meta` | `META_APP_ID/SECRET`, `META_REDIRECT_URI`, `META_WEBHOOK_VERIFY_TOKEN` | `GET+POST /webhooks/meta` |
| 5 | Object storage | `FILESYSTEM_DISK=s3` | `AWS_*`, `MEDIA_DISK` | — |
| 6 | Push (optional) | `PUSH_PROVIDER=fcm` | `FCM_CREDENTIALS_PATH` | — |

Sab kuch `unconfigured` par default hai. Keys daalo, driver name daalo, adapter
apne aap bind ho jaayega — code change ki zarurat nahi.

Poori detail: **`docs/INTEGRATIONS.md`**.

Guarantees jo tests enforce karte hain:

- Checkout URL banne se payment paid nahi hota. Settlement = signed webhook **+**
  provider API dono confirm karein.
- Browser redirect kabhi settlement nahi.
- Payout webhook ke bina koi ledger debit nahi. Admin ke paas "mark paid" button hai hi nahi.
- Ledger append-only: reserved→available reversing entry se hota hai, update se nahi.
- Inbound email bina routing token ke `unmatched` rehta hai, guess nahi hota.
- Instagram sirf Meta Graph. Jo field API ne nahi bheji, woh dikhti hi nahi.
- Replay duplicate ignore hota hai; forged webhook genuine event ko block nahi kar sakta.
- Videos object storage mein, MySQL mein sirf key + metadata.

### Vidlix API (`/api/v1`)

Public: `POST /auth/register`, `POST /auth/login`, `GET /creators`, `GET /editors`, `GET /campaigns`

Sanctum:

| Method | Path |
|--------|------|
| POST | `/auth/logout` |
| GET | `/me` |
| POST | `/payments/create` · GET `/payments/{uuid}` |
| POST | `/campaigns/{id}/apply` · GET `/applications` |
| GET | `/projects` · POST `/projects` · GET `/projects/{id}` · POST `/projects/{id}/transition` |
| GET | `/earnings` · POST `/withdrawals` · GET `/invoices` |
| GET | `/managers` · GET `/instagram` |
| GET | `/inbox` · GET+POST `/conversations/{uuid}/messages` |
| POST | `/devices` (push token) |

### Flutter (`mobile/`)

Six tabs — Home, Campaigns, Projects, Earnings, Messages, Account — sab `/api/v1`
se. Phone kabhi MySQL se baat nahi karta. Cream/ink theme website jaisa hi.
Provider off ho to app saaf-saaf likhta hai kyun, khaali screen nahi dikhata.

---

## 2. Jo BACHA HUA hai

### Production (Hostinger)

`docs/DEPLOYMENT_HOSTINGER.md` ka checklist follow karo:

- MySQL + SSL + document root `public/`
- Queue worker (Supervisor) — iske bina email queue mein pada rahega
- Cron `schedule:run`
- Admin password change (`ChangeMe_Admin1` production par kabhi nahi)
- SPF / DKIM / DMARC before real email
- Legal CMS pages — abhi placeholder hain, lawyer-approved text daalna hai
- Backup + restore test

### Provider onboarding (business side, code nahi)

- Razorpay account + KYC, webhook secret set karo
- RazorpayX account number + verified fund accounts (`payout_accounts.provider_beneficiary_ref`)
- Meta app review: `instagram_basic`, `instagram_manage_insights`, `pages_show_list`
- Email domain verify + inbound parse MX

### Optional / baad mein

- Realtime chat: `BROADCAST_CONNECTION` abhi `log`. Reverb/Pusher baad mein.
- Comment-to-DM automation: Meta messaging permissions milne tak `unsupported` hi rahega.
- Payout beneficiary onboarding UI (abhi `payout_accounts` admin/manual hai).
- Flutter store listing. Alag native Swift/Kotlin **nahi** likhna — Flutter dono cover karta hai.

---

## 3. Naya provider connect karte time

1. Provider dashboard se keys → `.env` (commit mat karo).
2. Driver name `.env` mein set karo (`PAYMENT_PROVIDER=razorpay` waghairah).
3. Webhook URL + secret provider dashboard mein daalo.
4. Test event bhejo → `webhook_logs` mein `signature_status = valid` aana chahiye.
5. `php artisan test` — sab green rehna chahiye.
6. UI mein "paid / insights / email sent" tabhi dikhe jab provider ne confirm kiya ho.

---

## 4. Docs

- Architecture: `docs/ARCHITECTURE.md`
- Integrations: `docs/INTEGRATIONS.md`
- Local run: `docs/RUN.md`
- Hostinger: `docs/DEPLOYMENT_HOSTINGER.md`
