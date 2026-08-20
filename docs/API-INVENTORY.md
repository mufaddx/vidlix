# Vidlix — API Inventory

**Date:** 2026-08-21. From `routes/api.php` (~29 routes) plus the webhook group in `routes/web.php`.

---

## 1. Conventions in place (working)

The API has a consistent, well-built envelope defined in `bootstrap/app.php`:

```json
{ "success": false, "message": "...", "code": "VALIDATION_ERROR",
  "errors": {}, "request_id": "..." }
```

Status and code mapping is explicit: 401 `UNAUTHENTICATED`, 403 `RESOURCE_FORBIDDEN`, 404 `NOT_FOUND`, 422 `VALIDATION_ERROR`, 500 `SERVER_ERROR`. A 500 never leaks the exception message. Every response carries a `request_id` for correlation.

Auth is Sanctum. Global `throttle:api`. This is solid and should not be reworked.

---

## 2. Public endpoints (`/api/v1`, no auth)

| Method | Path | Controller | Status | Notes |
|---|---|---|---|---|
| GET | `app/android` | `AppReleaseController@android` | W | Self-update manifest |
| GET | `auth/terms` | `AuthController@terms` | W | — |
| POST | `auth/register` | `AuthController@register` | P | Remove Manager role option |
| POST | `auth/login` | `AuthController@login` | W | — |
| GET | `creators` | `MarketplaceController@creators` | P | Confirm no private fields leak |
| GET | `editors` | `MarketplaceController@editors` | P | Same |
| GET | `campaigns` | `MarketplaceController@campaigns` | W | — |

**Concern:** public list endpoints must not expose internal IDs or private metadata (spec §29). Needs a field-level review — currently unverified.

---

## 3. Authenticated endpoints (`auth:sanctum`)

| Method | Path | Controller | Status | Notes |
|---|---|---|---|---|
| POST | `auth/logout` | `AuthController` | W | — |
| GET | `me` | `AuthController` | P | Strip Manager fields |
| POST | `payments/create` | `MarketplaceController` | W | Amount computed server-side |
| GET | `payments/{uuid}` | `WorkspaceApiController` | P | Verify ownership scoping |
| POST | `campaigns/{campaign}/apply` | `MarketplaceController` | P | Verify eligibility gate |
| GET | `applications` | `WorkspaceApiController` | W | — |
| GET | `projects` | `WorkspaceApiController` | W | — |
| POST | `projects` | `MarketplaceController@storeProject` | W | — |
| GET | `projects/{project}` | `WorkspaceApiController` | **P — IDOR risk** | Implicit binding; ownership check must be confirmed |
| POST | `projects/{project}/transition` | `WorkspaceApiController` | P | Same |
| GET | `earnings` | `WorkspaceApiController` | W | — |
| POST | `withdrawals` | `MarketplaceController` | P | Verify permission gate |
| GET | `invoices` | `WorkspaceApiController` | W | — |
| GET | `managers` | `WorkspaceApiController` | **R** | Delete |
| GET | `instagram` | `WorkspaceApiController` | P | Creator-only |
| GET | `inbox` | `MarketplaceController` | P | No filters or ordering |
| GET | `conversations/{uuid}/messages` | `MarketplaceController` | **P — IDOR risk** | Participation check must be confirmed |
| POST | `conversations/{uuid}/messages` | `WorkspaceApiController` | **P — IDOR risk** | Same |
| GET | `profiles` | `WorkspaceApiController` | P | Strip Manager |
| POST | `roles/apply` | `WorkspaceApiController` | P | Reject `manager` |
| POST | `devices` | `WorkspaceApiController` | W | Push token registration |

---

## 4. Internal

| Method | Path | Status | Notes |
|---|---|---|---|
| POST | `internal/scheduler/run` | W | `X-Cron-Token` header, `throttle:scheduler`, 404s unless `CRON_TOKEN` is set. Deliberately outside `/v1`. Correct design. |

---

## 5. Webhooks (`/webhooks`, CSRF-exempt, signature-verified)

| Method | Path | Provider | Status |
|---|---|---|---|
| GET/POST | `meta` | Meta | P — verified, not wired to AutoDM |
| POST | `email/inbound` | Resend / SendGrid / Postmark | W |
| POST | `email/events` | Same | W |
| POST | `payment` | Razorpay | W |
| POST | `payout` | RazorpayX | W |

---

## 6. Endpoints to add, by phase

### Phase 3 — public profile
- `GET /api/v1/profiles/{username}` — public projection only

### Phase 4 — form customization
- `GET|POST /api/v1/forms/{form}/fields`
- `PATCH|DELETE /api/v1/forms/{form}/fields/{field}`
- `POST /api/v1/forms/{form}/fields/reorder`
- `POST /api/v1/forms/{form}/publish`
- `POST /api/v1/forms/{form}/disable`
- `POST /{username}/contact` — public, throttled, Turnstile

### Phase 5 — conversations
- `POST /api/v1/conversations/{uuid}/archive` `/mute` `/report` `/block`
- `GET /api/v1/conversations?filter=all|creator|editor|brand`

### Phase 6 — custom domains
- `POST /api/v1/domains` — connect
- `GET /api/v1/domains/{domain}` — status and DNS instructions
- `POST /api/v1/domains/{domain}/verify`
- `DELETE /api/v1/domains/{domain}`
- `POST /webhooks/custom-domain` — provider status callback

### Phase 7 — negotiations
- `GET|POST /api/v1/negotiations`
- `POST /api/v1/negotiations/{id}/offer` `/counter` `/accept` `/reject`
- `POST /api/v1/campaigns/{id}/shortlist`
- `GET|POST /api/v1/favorites`

### Phase 8 — AutoDM
- `GET /api/v1/autodm/account`
- `POST /api/v1/autodm/media/refresh`
- `GET /api/v1/autodm/media`
- `GET|POST /api/v1/autodm/automations`
- `POST /api/v1/autodm/automations/{id}/activate` `/deactivate` `/duplicate`
- `GET /api/v1/autodm/automations/{id}/runs`
- `POST /webhooks/instagram/comments`

---

## 7. Cross-cutting requirements for every new endpoint

Per spec §29, each protected endpoint must enforce: authentication, role, workspace, permission, **ownership or participation**, validation, rate limiting where relevant, and audit logging where required.

**The ownership/participation layer is the weak point.** There is no `app/Policies` directory; checks live inline in controllers. Recommendation for Phase 2b: introduce policies for `Project`, `Conversation`, `Campaign`, `Invoice`, `ProjectFile`, `ContactForm` and `CustomDomain`, and route every bound model through them. This closes the IDOR risks flagged above and gives new endpoints a consistent place to enforce access.
