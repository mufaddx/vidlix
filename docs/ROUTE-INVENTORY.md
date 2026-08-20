# Vidlix — Route Inventory

**Date:** 2026-08-21. Derived from `routes/web.php` (~160 routes) and `routes/api.php` (~29 routes).

Status key: **W** working · **P** partial · **M** missing · **D** duplicate · **R** remove (Manager) · **X** wrong under revised architecture

---

## 1. Public site (target host: `vidlix.in`)

| Method | Path | Controller | Status | Required change |
|---|---|---|---|---|
| GET | `/` | `HomeController` | P | Landing sections per spec §2; AutoDM section missing |
| GET | `/creators` | `HomeController@creators` | W | — |
| GET | `/editors` | `HomeController@editors` | W | — |
| GET | `/editors/{username}` | `EditorPublicController@show` | X | Replace with `/{username}` |
| GET | `/editor/{username}` | redirect | D/X | Delete |
| POST | `/editors/{username}/enquire` | `EditorPublicController@enquire` | X | Move to `/{username}/contact` |
| GET | `/u/{username}` | `CreatorPublicController@show` | X | Replace with `/{username}` |
| POST | `/u/{username}/inquire` | `CreatorPublicController@inquire` | X | Move to `/{username}/contact` |
| GET | `/brands`, `/brands/{slug}` | `HomeController` | P | Confirm against revised brand architecture |
| GET | `/campaigns` | `HomeController@campaigns` | W | — |
| GET | `/blog`, `/blog/{slug}` | `HomeController` | W | — |
| GET | `/pricing` | `HomeController@pricing` | W | — |
| GET | `/p/{slug}` | `HomeController@page` | W | Terms and Privacy live here |
| GET | `/download/android` | `AppDownloadController` | W | — |
| GET | `/{username}` | — | **M** | Catch-all, after all fixed routes |
| GET | `/{username}/contact` | — | **M** | Direct form open |
| POST | `/{username}/contact` | — | **M** | Throttled, Turnstile, honeypot |

Note: `/{username}` as a catch-all requires the reserved-path guard in `docs/DATABASE-AUDIT.md` §3 and must be registered **last**.

---

## 2. Authentication (guest)

| Method | Path | Status |
|---|---|---|
| GET/POST | `/register`, `/register/start`, `/register/verify`, `/register/resend` | W |
| GET/POST | `/forgot-password` + `/start` `/verify` `/resend` | W |
| GET/POST | `/login` | W |
| GET/POST | `/two-factor` | W |
| POST | `/logout` | W |

All throttled (`throttle:login`, `throttle:otp`, `throttle:register`). No change required beyond removing the Manager option from `RegisterRequest`.

---

## 3. Manager (all **R** — remove)

| Method | Path | Action |
|---|---|---|
| GET/POST | `/manager/invite/{token}` | Delete route and controller |
| GET | `/management` | Delete |
| POST | `/management/invite` | Delete |
| POST | `/management/accept/{token}` | Delete |
| POST | `/management/subscribe` | Delete |
| POST | `/management/{assignment}/revoke` | Delete |
| POST | `/workspace/manage` | Delete (acting-for switch) |
| GET | `/admin/managers` | Delete |
| POST | `/admin/managers/assign` | Delete |
| GET | `/api/v1/managers` | Delete |

Each deletion needs a regression test asserting 404 (route gone) or 403 (ability revoked).

---

## 4. Application (target host: `app.vidlix.in`)

All under `auth` + `verified`.

| Method | Path | Status | Note |
|---|---|---|---|
| POST | `/workspace/switch` | P | Rewrite once `WorkspaceContext` drops acting-for |
| GET | `/dashboard` | P | Spec §7 dashboard cards |
| GET | `/inbox`, `/inbox/{uuid}`, POST `/inbox/{uuid}/reply` | P | No archive/mute/report/block/search |
| GET | `/chat`, `/chat/{uuid}` + POST | D | Consolidate with inbox |
| GET | `/creator/public-page` + draft/publish/social/form | P | `form` endpoint is title+description only |
| GET/POST | `/roles`, `/roles/apply` | W | — |
| GET/POST | `/editor`, `/editor/apply` | W | Manual approval enforced |
| GET/POST | `/brand`, `/brand/documents` | P | Confirm against revised architecture |
| GET/POST | `/app/campaigns` + submit/apply | P | Negotiations not first-class |
| GET/POST | `/applications`, `/applications/{id}` | P | — |
| GET/POST | `/projects` + show/transition/file/revision/pay | W | — |
| GET | `/earnings`, POST `/withdrawals` | P | — |
| GET/POST | `/automations` | P | Marketplace automations, **not** AutoDM |
| GET | `/instagram`, POST connect/sync | P | Creator-only |
| GET | `/project-files/{file}` | P | Verify signed URL and authorization |
| GET/POST | `/disputes`, `/support`, `/notifications`, `/portfolio`, `/proposals`, `/invoices` | W/P | — |
| GET/POST | `/settings` + sessions/two-factor/privacy | W | — |
| — | Form field builder endpoints | **M** | Phase 4 |
| — | Custom domain endpoints | **M** | Phase 6 |

---

## 5. Admin (target host: `admin.vidlix.in`)

Prefix `/admin`, middleware `auth` + `admin`, each route additionally ability-gated. 30+ routes across dashboard, CMS, verification, finance, disputes, tickets, members, influencers, brands, editors, categories, help desk, platform, employees, health.

Status: **W**, except `/admin/managers*` (**R**) and the need to bind the panel to its own host.

---

## 6. Webhooks

Prefix `/webhooks`, `throttle:webhooks`, CSRF-exempt, signature-verified.

| Method | Path | Status |
|---|---|---|
| GET/POST | `webhooks/meta` | P — verified, but not wired to AutoDM execution |
| POST | `webhooks/email/inbound` | W |
| POST | `webhooks/email/events` | W |
| POST | `webhooks/payment` | W |
| POST | `webhooks/payout` | W |
| POST | `webhooks/instagram/comments` | **M** — Phase 8 |
| POST | `webhooks/custom-domain` | **M** — Phase 6 |

---

## 7. API v1

See `docs/API-INVENTORY.md`.

---

## 8. Routes to add, by phase

- **Phase 3:** `GET /{username}`, `GET /{username}/contact`
- **Phase 4:** `POST /{username}/contact`; form field CRUD and reorder under `/creator/public-page/fields`
- **Phase 6:** custom domain connect, DNS instructions, verify, status, disconnect
- **Phase 8:** AutoDM landing, dashboard, OAuth callback, media refresh, automation CRUD and activation, execution log, comment webhook
