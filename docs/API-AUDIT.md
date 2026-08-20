# API audit

Surface: `routes/api.php`. Prefix `/api` is Laravel default. Versioned public API lives under `/api/v1`. Scheduler is **outside** v1.

Global on v1: `throttle:api` — 60/min per user id or IP (`AppServiceProvider`).

JSON errors (`bootstrap/app.php`): `{ success, message, code, errors, request_id }` for `api/*`. 500 messages are generic.

There is **no** CORS config in-repo. Flutter uses `Authorization: Bearer` from a native/http client, not cookies. Fine for the app. A browser SPA would need CORS + Sanctum stateful domains.

---

## Internal (not public product)

### `POST /api/internal/scheduler/run`

- Header `X-Cron-Token` compared with `hash_equals` to `vidlix.cron.token`.
- Empty token → **404** (route pretends not to exist).
- Wrong token → 404 + warning log (no 401, to avoid confirming the route).
- Runs `schedule:run` (queue drain + Instagram hourly + reminders).
- `throttle:scheduler` 6/min/IP.
- Open during maintenance.

**Risk:** shared secret in a header is appropriate. Token in query string is forbidden by comments. Ensure production `CRON_TOKEN` is long and rotated. Anyone with the token can fire jobs (queue work). Treat like a deploy secret.

---

## Public v1 (no Sanctum)

| Method | Path | Controller | Contract |
| --- | --- | --- | --- |
| GET | `/api/v1/app/android` | `AppReleaseController` | Forced-update metadata from `config/vidlix.php` |
| GET | `/api/v1/auth/terms` | `AuthController@terms` | Same `TermsContent` as website |
| POST | `/api/v1/auth/register` | `AuthController@register` | `RegisterRequest`: name, email, mobile, password confirmed min 10 mixed+numbers, optional `role` in creator\|editor\|brand |
| POST | `/api/v1/auth/login` | `AuthController@login` | login + password → `{ token, token_type: Bearer }` |
| GET | `/api/v1/creators` | `MarketplaceController` | `visibility=public`, paginate 20 |
| GET | `/api/v1/editors` | | `application_status=approved`, paginate 20 |
| GET | `/api/v1/campaigns` | | `status=published`, paginate 20 |

### Register vs web

- Creates `users` row immediately; fires `Registered` (email verification notification).
- Does **not** run OTP flow.
- Does **not** honour `feature:public_signup`.
- Role attach is allowlisted. Cannot attach `super_admin` or `manager`.
- Returns 201 with user id only (no token) — phone must login after. Good.

### Login vs web

- No `LoginAttempt` row.
- No `status === 'active'`.
- No 2FA.
- Token: `$user->createToken('api')->plainTextToken` — ability `*`, no expiry (`config/sanctum.php`).

**P0.** Align with web or the phone is a bypass.

### Public lists

Eloquent pagination dumped as JSON. Includes internal ids and profile columns. Creators with `visibility=public` may still expose columns not meant for a directory (check `$hidden` on models). `CreatorProfile` should be reviewed before calling this a stable public contract.

No brand directory endpoint.

---

## Sanctum v1

Middleware: `auth:sanctum` only. **Not** `verified`. **Not** `workspace`.

| Method | Path | Authz | Notes |
| --- | --- | --- | --- |
| POST | `/auth/logout` | Current token delete | Does not revoke others |
| GET | `/me` | Self | Roles + three profiles (full models) |
| POST | `/payments/create` | `project.involves` | Requires `project_id`; 503 if provider off |
| GET | `/payments/{uuid}` | `payer_user_id` | Status only; notes webhook is source of truth |
| POST | `/campaigns/{campaign}/apply` | Engine | No feature flag |
| GET | `/applications` | Own creator profile else `[]` | Brand cannot list incoming apps |
| GET | `/projects` | owner or counterparty | Paginated |
| POST | `/projects` | Any authenticated | `counterparty_user_id` any existing user — **can open a project against a stranger** |
| GET | `/projects/{project}` | `involves` else 404 | Includes next_states, files, revisions, payments |
| POST | `/projects/{project}/transition` | `involves` | **No role** on who may set `completed` |
| GET | `/earnings` | Own ledger | Derived sums + last 100 entries |
| POST | `/withdrawals` | Own | No `feature:withdrawals`; engine should still check balance |
| GET | `/invoices` | seller or buyer | |
| GET | `/managers` | Own assignments | Read-only |
| GET | `/instagram` | Own creator | Insights as stored; `connect_url` |
| GET | `/inbox` | See below | |
| GET | `/conversations/{uuid}/messages` | Participant **or** creator profile owns conversation | 404 else |
| POST | `/conversations/{uuid}/messages` | Internal: engine participant check. External: **must be that creator** | |
| GET | `/profiles` | Self | `active` from **session** — Bearer requests usually have empty session |
| POST | `/roles/apply` | creator\|editor\|brand | Same service as web |
| POST | `/devices` | Self | FCM token upsert |

### Inbox filter (`MarketplaceController@inbox`)

If `creatorProfile` exists: `where('creator_profile_id', $profile->id)` **only**. Internal chats without that FK disappear. Non-creators: participant filter. Web inbox is unified. **Parity bug.**

### `session('acting_for_creator_id')` on API message create

`WorkspaceApiController@postMessage` writes `acting_for_creator_id` from session. Sanctum API typically has no session. Field stays null. Manager-on-phone cannot act-as (also no endpoints).

### Project create

No check that counterparty is a verified brand/editor/creator. Spam/harassment vector: authenticated user creates projects targeting arbitrary user ids.

### Withdrawals / campaign apply

Not wrapped in `EnsureFeature`. Website can “turn off withdrawals” while the app still posts.

### File, pay, chat start, disputes, support, 2FA, privacy

No API. Flutter cannot download project files or start checkout except via `payments/create`.

---

## Envelope

Success (workspace helper): `{ success: true, code: 'OK', data, request_id }`.

Payments unconfigured: 503, `PROVIDER_NOT_CONFIGURED`, `success: false`. Tests assert this.

Pagination: Laravel default (`data.data` for lists). Flutter `Api.listOf` unwraps both shapes.

---

## Throttling map

| Limiter | Rate | Used on |
| --- | --- | --- |
| `api` | 60/min | Entire `/api/v1` |
| `scheduler` | 6/min | Internal cron |
| `login` / `register` / `otp` / `public-form` / `webhooks` | web only | Not on API login/register |

API login is only limited by the coarse 60/min API bucket. Credential stuffing is easier against `/api/v1/auth/login` than `/login`.

---

## Flutter client map (`mobile/lib`)

| Dart | Paths |
| --- | --- |
| `api.dart` | GET/POST `/api/v1` + Bearer |
| `login_screen.dart` | `/auth/login` |
| `signup_screen.dart` | `/auth/register` |
| `terms_screen.dart` | `/auth/terms` |
| `directory_screen.dart` | `/creators`, `/editors` |
| `inbox_screen.dart` | `/inbox`, messages |
| `projects_screen.dart` | `/projects` |
| `campaigns_screen.dart` | `/campaigns`, apply |
| `earnings_screen.dart` | `/earnings`, `/withdrawals` |
| `account_screen.dart` | `/me`, `/instagram`, `/managers`, `/applications`, `/invoices`, `/roles/apply`, `/devices` |
| `update.dart` | `/app/android` |

Token stored in `SharedPreferences` as `vidlix_token`. No biometric lock, no expiry handling.

---

## Missing API that the website already has

Workspace switch, act-as, public page, brand profile/docs, discover, campaign write, application decision, project files, pay UX, chat list/start, automations, IG connect/sync POST, disputes, tickets, notifications, settings, 2FA, privacy, invoice PDF, proposals.

Adding these without fixing login/2FA/status would widen the weaker front door. **Harden auth first.**

---

## Tests covering API

- `WorkspaceApiTest` — unauthenticated 401, earnings from ledger, project IDOR 404
- `ProjectTransitionsApiTest` — next_states published; illegal transition refused
- `AppSignupContractTest` / `AppReleaseTest`
- `MarketplaceFoundationTest` — payment create 503 unconfigured; webhook unmatched email
- `SchedulerTriggerTest`

No test that API login rejects `status=suspended` or requires 2FA. That gap matches the P0 finding.
