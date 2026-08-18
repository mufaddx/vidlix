# Vidlix architecture freeze (Phase 0)

Product: Creator × Brand × Editor × Manager collaboration marketplace.  
Stack: Laravel 13 + MySQL (local SQLite allowed) + Hostinger. Clients talk only to HTTPS `/api/v1` or Blade web — never to MySQL.

## Non-negotiables

- No fake payment success, wallet balances, Instagram analytics, or outbound email “sent” state.
- External systems sit behind replaceable interfaces. Missing credentials = explicit `PROVIDER_NOT_CONFIGURED`.
- Money source of truth is an immutable ledger. UI balances are derived, never invented.
- Manager actions store `actor_user_id` + `acting_for_creator_id`. Client-supplied `creator_id` is never trusted.
- Large media lives in object storage; MySQL stores metadata and storage keys only.
- Instagram uses official Meta APIs only. Scraping is forbidden.
- Public form builder cannot inject HTML/JS/CSS. Themes are token presets.

## Identity and workspaces

- `users` is the permanent identity.
- Roles are many-to-many. One person may be creator + editor.
- Active workspace is a **server session context** (`active_role`, optional `acting_for_creator_id`), re-authorized on every request.
- Switching workspace does not change login identity.

## Conversation model

- Every thread has a stable `conversation_uuid`.
- Internal chat and external email are different `channel` values on the same conversation family, never mixed without routing records.
- Inbound email that cannot be resolved goes to `inbound_email_events.unmatched` — never guessed into a private inbox.

## Money

- Ledger entries are append-only. Corrections are reversing entries.
- Payment confirmation is webhook + provider API only. Browser redirects are not authoritative.
- Webhooks require signature verification and unique `provider_event_id`.

## Phased delivery

| Phase | This repo status |
| --- | --- |
| 0 Architecture | Documented here |
| 1 Foundation | Auth, RBAC, audit, adapters, admin skeleton |
| 2 Public website | CMS-driven landing + legal/info pages |
| 3 Creator public URL | Published profile, social links, form, inquiry inbox |
| 4–11 Marketplace engines | Editor/brand verification, campaigns, applications, proposals, projects, files, revisions, chat, manager invites, ledger/withdrawals, disputes, reviews, invoices/agreements, Instagram/automation adapters, inbound unmatched email |
| 12 Tests | Feature tests for inquiry, IDOR, webhooks, campaigns |
| 13 Hostinger | Documented; configure .env + public/ document root |

## Request correlation

Every HTTP request gets `X-Request-Id`. Audit logs, webhooks, and API errors reuse it.
