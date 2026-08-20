# Feature gap matrix

Status keys:

- **Full** — UI + server + tests (or clearly production-shaped)
- **Partial** — exists but incomplete, wrong gate, or client missing
- **Schema only** — table/model, little or no product path
- **Missing** — specified or implied, not built
- **N/A** — does not apply to that client

Clients: **Web** (Blade), **API** (`/api/v1`), **App** (Flutter).

---

## Identity and access

| Feature | Web | API | App | Notes |
| --- | --- | --- | --- | --- |
| Email + password login | Full | Partial | Partial | API skips 2FA and `status` |
| OTP signup (no user until verified) | Full | Missing | Missing | API creates user immediately |
| Password reset OTP | Full | Missing | Missing | |
| Email verification | Full | Missing | Missing | Sensitive web routes need `verified`; API does not |
| TOTP 2FA + recovery codes | Full | Missing | Missing | |
| Account status (suspend) | Full | Missing | Missing | Admin can suspend; API still issues tokens |
| Staff separate login | Full | N/A | N/A | Admin login skips 2FA |
| Session revoke | Full | Missing | Missing | |
| Privacy export / delete | Full | Missing | Missing | |
| Feature flag: public_signup | Full | Missing | Missing | |
| Sanctum token expiry / abilities | N/A | Missing | Missing | `expiration => null` |

---

## Profiles and public presence

| Feature | Web | API | App | Notes |
| --- | --- | --- | --- | --- |
| Apply creator / editor / brand | Full | Partial | Partial | API `POST /roles/apply`; no category save |
| Creator categories (max 3) | Full | Missing | Missing | |
| Creator public page studio | Full | Missing | Missing | |
| Public creator URL + form | Full | N/A | N/A | |
| Editor public URL + enquire | Full | N/A | N/A | |
| Brand public directory + page | Full | Missing | Missing | No `/api/v1/brands` |
| Brand verification documents | Full | Missing | Missing | |
| Portfolio | Partial | Missing | Missing | Web CRUD; no API |
| Editor apply + software fields | Partial | Missing | Missing | |
| Discover creators (brand search) | Partial | Missing | Missing | Web only; follower filter uses live IG count |

---

## Marketplace engines

| Feature | Web | API | App | Notes |
| --- | --- | --- | --- | --- |
| Campaigns list (published) | Full | Full | Partial | Profile nested screen |
| Publish campaign | Partial | Missing | Missing | Web + `feature:campaign_publishing`; no API write |
| Apply to campaign | Full | Full | Partial | API ignores feature flag |
| Brand decide application | Full | Missing | Missing | |
| Proposals + versions | Partial | Missing | Missing | Web list/create; no accept/version UI depth |
| Projects list / show | Full | Full | Partial | App list + limited actions |
| Project create | Full | Full | Missing | API allows any `counterparty_user_id` |
| Project transitions | Partial | Partial | Partial | No role-based who-may-advance |
| Project files + download | Full | Missing | Missing | MIME allowlist in engine |
| Revisions | Partial | Missing | Missing | |
| Pay (create checkout) | Full | Full | Missing | Honest unconfigured state |
| Payment status poll | Missing | Full | Missing | `GET /payments/{uuid}` |
| Internal chat | Full | Partial | Missing | API messages on conversation uuid (inbox-shaped) |
| Unified inbox (email + internal) | Full | Partial | Partial | API inbox prefers `creator_profile_id` |
| Invoices list + PDF | Full | Partial | Partial | API list, no PDF |
| Agreements + typed accept | Schema only | Missing | Missing | |
| Reviews / ratings | Schema only | Missing | Missing | |
| Commission rules | Schema only | Missing | Missing | Seeded 1000 bps; not applied in UI |

---

## Money

| Feature | Web | API | App | Notes |
| --- | --- | --- | --- | --- |
| Ledger projection | Full | Full | Partial | Earnings screen |
| Request withdrawal | Full | Partial | Partial | API ignores `feature:withdrawals` |
| Payout account onboarding | Missing | Missing | Missing | Rows are admin/manual (`BRAIN.md`) |
| Admin approve payout | Full | N/A | N/A | Ability `finance.approve_payouts` |
| Disputes open (member) | Partial | Missing | Missing | GET blocked by admin ability |
| Disputes resolve (admin) | Full | N/A | N/A | |
| Dispute evidence files | Partial | Missing | Missing | Placeholder chain in copy |

---

## Managers

| Feature | Web | API | App | Notes |
| --- | --- | --- | --- | --- |
| Owner invite manager | Full | Missing | Missing | |
| Accept invite (token page) | Full | Missing | Missing | |
| Revoke | Full | Missing | Missing | |
| Company-provided assign (admin) | Full | N/A | N/A | |
| List my managers / representing | Full | Full | Partial | Read-only on phone |
| **Act as** managed account | Partial | Missing | Missing | Session set; queries not re-scoped |
| Management subscription checkout | Partial | Missing | Missing | Plans on `/pricing`; subscribe POST exists |
| Per-assignment JSON permissions | Schema only | Missing | Missing | Column unused |

---

## Instagram, email, push, storage

| Feature | Web | API | App | Notes |
| --- | --- | --- | --- | --- |
| Meta OAuth connect | Full | Missing | Missing | API returns `connect_url` only |
| Sync insights | Partial | Missing | Missing | Hourly scheduler for connected |
| Unconfigured honesty | Full | Full | Partial | |
| Outbound thread email | Partial | Partial | Missing | Needs provider + inbound domain |
| Inbound unmatched queue | Full (data) | N/A | N/A | No member UI; admin via logs/help desk |
| Help desk (help@) | Full | Missing | Missing | |
| Member support tickets | Full | Missing | Missing | Dual-write with threads |
| FCM device register | N/A | Full | Partial | Flag `push_notifications` unused on route |
| Object storage media | Full | Missing | Missing | R2 in prod per BRAIN |

---

## Platform / CMS / admin

| Feature | Web | API | App | Notes |
| --- | --- | --- | --- | --- |
| CMS homepage + `/p/{slug}` | Full | Missing | Missing | Legal copy still placeholder |
| Blog | Full | Missing | Missing | |
| Feature flags + maintenance | Full | Partial | Partial | Maintenance 503 on `/api/v1`; flags not on API writes |
| Employee abilities | Full | N/A | N/A | |
| Member dossier | Full | N/A | N/A | |
| Category approve | Partial | Missing | Missing | POST exists; BRAIN: dedicated pending UI thin |
| System health | Full | N/A | `/up` | |
| Android in-app update metadata | N/A | Full | Full | `GET /app/android` |
| HTTP scheduler (no cron) | N/A | Full | N/A | |

---

## Architecture rules vs code

| Rule (`ARCHITECTURE.md` / `BRAIN.md`) | Honoured? |
| --- | --- |
| Never fake payment / balance / IG / sent email | Yes, with tests |
| Missing creds → `PROVIDER_NOT_CONFIGURED` | Yes |
| Ledger append-only, derived balances | Yes |
| Settlement = webhook + provider API | Yes (`PaymentSettlementService`) |
| Instagram = Graph only | Yes (adapters) |
| Manager: session ids, DB re-check every request | **Partial** — re-check exists; middleware not on routes; controllers ignore effective user |
| Media in object storage | Yes |
| Public form cannot inject HTML/JS/CSS | Yes for schema types; studio only edits title/description of default schema |
| Client `creator_id` never trusted | Mostly — web uses `request()->user()`. Acting-for id is not applied. API has no acting-for. |

---

## Flutter vs promised workspace

`docs/VSCODE_PROMPT.md` asked to extend Flutter beyond a thin shell (projects, apply, earnings, managers). Current shell: Creators, Editors, Inbox, Projects, Profile (+ nested campaigns/earnings). Still missing vs web: files, pay, chat, disputes, support, 2FA, public page, brand tools, notifications.

---

## Suggested product cuts vs builds

**Build next (after P0 security):** wire workspace middleware + effective user; fix disputes GET; align API login with web; payout account UI; legal CMS.

**Do not build yet:** reviews, agreements UI, commission display, merging tickets/threads — schema is enough until money and manager acting-as work.

**Delete / stop seeding later:** unused `permissions` tables; leftover staff role slugs if employees fully replaced them; dead `simple-list` view; hide or redirect `admin.tickets`.
