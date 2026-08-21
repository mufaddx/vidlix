# API

Base: `/api/v1`. Sanctum tokens. Throttled by `throttle:api`.

## Envelope

Every response, success or failure:

```json
{
  "success": false,
  "message": "...",
  "code": "VALIDATION_ERROR",
  "errors": {},
  "request_id": "REQ_..."
}
```

| Status | Code |
|---|---|
| 401 | `UNAUTHENTICATED` |
| 403 | `RESOURCE_FORBIDDEN` |
| 404 | `NOT_FOUND` |
| 422 | `VALIDATION_ERROR` |
| 500 | `SERVER_ERROR` |

A 500 never leaks the exception message. `request_id` correlates a report to the
log without exposing anything.

## Public

```
GET  app/android          current build manifest
GET  auth/terms
POST auth/register
POST auth/login
GET  creators
GET  editors
GET  campaigns
```

## Authenticated

```
POST auth/logout
GET  me

GET  applications
POST campaigns/{campaign}/apply

GET  projects
POST projects
GET  projects/{project}
POST projects/{project}/transition

GET  earnings
POST withdrawals
GET  invoices
POST payments/create
GET  payments/{uuid}

GET  inbox
GET  conversations/{uuid}/messages
POST conversations/{uuid}/messages

GET  profiles
POST roles/apply
POST devices
GET  instagram
```

## Internal

```
POST internal/scheduler/run
```

`X-Cron-Token` header, `throttle:scheduler`. 404s unless `CRON_TOKEN` is set.
Deliberately outside `/v1` — it is not part of the public surface.

## Webhooks

```
POST webhooks/email/inbound
POST webhooks/email/events
POST webhooks/payment
POST webhooks/payout
GET|POST webhooks/meta
```

CSRF-exempt because each is signature-verified instead. See
`docs/SECURITY.md`.

`webhooks/meta` carries both profile changes and AutoDM comment events — Meta
delivers everything to one URL, and that URL is the one whose signature is
verified.

## Rules for every protected endpoint

Authentication, role, workspace, permission, **ownership or participation**,
validation, rate limit where relevant, audit where required.

Ownership goes through `app/Policies`. Refusals are 404 rather than 403.

Public list endpoints must not expose internal ids or private metadata.

## Removed

`GET /api/v1/managers` — the manager system is gone. See
`docs/MANAGER-REMOVAL.md`.
