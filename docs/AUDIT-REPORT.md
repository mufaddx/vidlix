# Vidlix — Audit Report

**Date:** 2026-08-21
**Scope:** Full repository inspection prior to any implementation, per the revised Vidlix architecture (Manager system removed, four-domain model, `vidlix.in/{username}` public profiles, AutoDM product).
**Method:** Static inspection of routes, controllers, models, migrations, services, middleware, views, tests and CI. The PHP test suite was **not executed** (the run was declined). Every statement below is derived from reading code, not from a green test run. Re-run `php artisan test` before acting on any "Working" classification.

---

## 1. Headline finding

The repository is **substantially more complete than the "≈30%" assumption** — but it is built for the *previous* architecture. The gap is not mostly "missing features"; it is **architectural divergence** between what exists and what the revised Vidlix spec requires.

| Dimension | Count |
|---|---|
| Models | 68 |
| Migrations | 21 |
| Web routes | ~160 |
| API routes | ~29 |
| Blade views | 89 |
| Services | 49 |
| Feature test files | 30 |
| CI jobs | 3 (quality, browser, security) |

Engineering quality of what exists is **high**: Pint, PHPStan, Pest, Playwright, Gitleaks and Semgrep all gate merges; webhook signature verification is per-provider and genuinely correct; CSP uses a per-request nonce; secrets are not committed.

The work ahead is therefore **restructure and extend**, not rebuild.

---

## 2. Classification summary

| Class | Feature areas | Notes |
|---|---|---|
| Working | 11 | Auth/OTP/2FA, security headers, webhooks, email threading core, invoices, ledger, admin panel, CI |
| Partial | 9 | Public profiles, contact form, inbox, Instagram, campaigns, projects, payments, storage, notifications |
| Missing | 6 | AutoDM (whole product), custom domains, global username registry, form field builder, domain routing, WhatsApp state |
| Duplicate | 3 | Inquiry services, editor public routes, inbox vs chat |
| Fake / UI-only | 1 | Form "customization" — title and description only |
| Security risk | 3 | Username collision, `example.com` mail default, untyped form validation |
| Provider blocked | 3 | Meta app review, Cloudflare for SaaS, WhatsApp BSP |
| Architecture blocked | 1 | Manager removal gates the workspace-context rewrite |

---

## 3. Working (verify, do not rewrite)

- **Authentication.** Three-step signup that creates no user until OTP is verified; throttled login/register/OTP; OTP password reset; email verification; TOTP 2FA with recovery codes (`pragmarx/google2fa`).
- **Security middleware.** `SecurityHeaders` sets CSP with a fresh per-request nonce, HSTS on TLS, `X-Frame-Options: DENY`, `nosniff`, Referrer-Policy and Permissions-Policy. Admin has a **separate front door** (`/admin/login`) with guest-redirect steering, so a member session is never a way into the panel.
- **Webhook verification.** `SignatureVerifier` implements HMAC-hex (Razorpay), Meta `X-Hub-Signature-256`, SendGrid ECDSA P-256, Svix/Resend with a 300-second replay window, and Postmark Basic. A delivery that cannot be proven authentic is logged and dropped. This is production-grade.
- **Inbound email idempotency.** `inbound_email_events.provider_event_id` is `unique`, so a duplicate delivery cannot double-post.
- **Email threading data model.** `messages` carries `email_message_id`, `in_reply_to`, `email_references`, `provider_message_id` and `delivery_status`; `conversations.routing_token` is unique and drives deterministic Reply-To routing.
- **Ledger and invoices.** Double-entry `ledger_accounts` / `ledger_entries`; invoice PDF via dompdf.
- **Admin panel.** 22 views, ability-gated per route (`can:verification.decide`, `can:finance.approve_payouts`, …), employee abilities, feature flags, maintenance mode, health page, audit logs.
- **CI/CD.** Pint, PHPStan with baseline, Pest, Playwright, Gitleaks over full history, Semgrep (`p/php`, `p/security-audit`, `p/secrets`, `p/owasp-top-ten`). Dependabot configured.
- **Secret hygiene.** `.env` untracked, SQLite databases untracked, `dist/` ignored, `.gitleaks.toml` present.

---

## 4. The five structural blockers

These must be resolved before the phased feature work is meaningful.

### 4.1 The Manager system is load-bearing (blocker for Phase 2)

390 mentions across 45 files. It is not a bolt-on. Critically, **`WorkspaceContext` — the class that decides who the signed-in person is acting as on every request — is written entirely around `ManagerAssignment`**. Its `canActFor()`, `actAs()`, `switchableAccounts()` and the re-authorisation loop in `hydrate()` all query that table. Removing Manager therefore means **rewriting the core authorization context**, not deleting files.

Also entangled: `AccountProvisioner`, `MarketplaceEngine`, `PersonalDataService` (the GDPR export), `Ability`, `AdminNavigation`, `RegisterRequest`, 4 models, 3 migrations, the seeder, 8 views, 7 test files, and both route files.

Detail and sequencing in `docs/MANAGER-REMOVAL-PLAN.md`.

### 4.2 The public profile URL is wrong, and usernames can collide (blocker for Phase 3)

Required: `https://vidlix.in/{username}`, with no role prefix.

Current:
- `/u/{username}` resolves creators
- `/editors/{username}` resolves editors, plus a `/editor/{username}` redirect — a duplicate the revised spec explicitly forbids

Worse: **there is no global username registry.** `creator_profiles.username` and `editor_profiles.username` are each independently `unique`. A creator `asif` and an editor `asif` can both exist today. The moment `/{username}` becomes the canonical route, that is an **unresolvable ambiguity and an impersonation vector**.

Resolving it needs a `usernames` registry table, a reserved-path list, a collision migration with a documented remediation policy for any existing duplicates, and a catch-all route ordered after every fixed route.

### 4.3 The form builder does not build forms (fake / UI-only, Phase 4)

`PublicPageStudioController::saveForm()` edits exactly two things: `form_title` and `form_description`. It versions the schema correctly — `contact_form_versions` with an immutable `schema_json` bound to each submission is the right design, and those bones should be kept — but there is **no add field, edit field, delete field, reorder, field type, options list, or conditional "Other → please specify"**.

`PublicInquiryService::validateAgainstSchema()` matches that limitation: it treats every field as a trimmed string, honours only `required`, and special-cases only `email`. Dropdowns, checkboxes, file attachments and conditional fields have **no server-side validation path at all**.

The spec's flagship end-to-end test — add College and Other fields, publish, submit — **cannot pass today**.

### 4.4 Two products do not exist (Phases 6 and 8)

- **Custom domains:** zero references anywhere. No `custom_domains` table, no hostname resolution, no DNS/SSL state machine, no tenant isolation by hostname.
- **AutoDM:** zero references. No `autodm.vidlix.in`, no landing page, no dashboard, no media refresh, no automation builder, no comment-webhook execution engine. The existing `Automation` and `AutomationRun` models are *marketplace* automations and must not be conflated with AutoDM.

### 4.5 The domain architecture is not configured (Phase 1)

There is no `APP_APP_URL`, `AUTODM_APP_URL` or `ADMIN_APP_URL`. Everything is served from one host with path prefixes (`/admin`, `/u/...`). `config/mail.php` still defaults `MAIL_FROM_ADDRESS` to `hello@example.com`, which the spec forbids, and none of the seven `MAIL_FROM_*` role identities are configured.

---

## 5. Partial features

| Feature | What works | What is missing |
|---|---|---|
| Public profiles | Creator and editor pages render; `visibility` and `accepts_inquiries` flags; published state | Wrong URL; no global username; no Open Graph image; thin SEO metadata |
| Contact form | Versioned schema, honeypot, Turnstile, rate limit, transactional submission, audit event | No field builder (§4.3); no attachments; no per-field types |
| Inbox | Conversation owner and scope; per-participant `last_read_at`; `marketplace_role` supports the All/Creator/Editor/Brand filters | No role-priority ordering; no archive, mute, report or block; no message search |
| Email | Outbound identity object; Reply-To routing; delivery and bounce events; inbound idempotency | Identities not env-driven per role; unknown-sender to admin-review path unverified |
| Instagram | Real Meta OAuth, state validation, server-side code exchange, permitted sync | Creator-only (typed to `CreatorProfile`); no webhooks wired to automation; no media store |
| Campaigns and projects | Full CRUD, statuses, transitions, files, revisions, disputes | Negotiations are not first-class records; the brand workflow predates the revised architecture |
| Payments | Razorpay provider and RazorpayX payouts; server-side orders; settlement service | Reconciliation and refund paths unverified |
| Storage | S3/R2 driver configured with correct path-style and checksum handling | `FILESYSTEM_DISK` defaults to `local`; signed-URL and MIME enforcement need verification |
| Notifications | Database notifications, preferences table, FCM push provider | Deep links and duplicate suppression unverified |

---

## 6. Duplicates to consolidate

1. `PublicInquiryService` (creator, versioned form) and `PublicEnquiryService` (editor, fixed form). Once editors get the same form builder these collapse into one service — the existing docblock already anticipates this.
2. `/editors/{username}` plus the `/editor/{username}` redirect. Both disappear under `/{username}`.
3. `/inbox` and `/chat` are two conversation UIs. Internal chat and the unified inbox should share one substrate.

---

## 7. Security assessment

The posture is **good**. Three real issues, two of which become critical under the new architecture.

| # | Issue | Severity | Trigger |
|---|---|---|---|
| S-1 | Username uniqueness is per-profile-type, not global | **High** | Becomes an impersonation vector the instant `/{username}` ships |
| S-2 | `MAIL_FROM_ADDRESS` defaults to `hello@example.com` | Medium | Spec violation; a misconfigured production sends from a domain Vidlix does not control |
| S-3 | The form validator ignores field types and option lists | Medium | Any non-text field added to a schema is unvalidated server-side |

Not found, and worth stating: no committed secrets, no `APP_DEBUG=true` in `.env.example`, no unsigned webhook path, no raw video in MySQL, no scraping, no password-based Instagram access.

Full detail in `docs/SECURITY-AUDIT.md`.

---

## 8. Recommended sequencing change

The spec's Phase 2 should be split, because `WorkspaceContext` sits on the critical path for everything after it:

- **Phase 2a — deny access.** Remove Manager routes, navigation, API and the signup option; add regression tests asserting 403/404. Data and models stay. This ships in hours and closes the surface immediately.
- **Phase 2b — rewrite `WorkspaceContext`** around role and profile switching only, with no acting-for-someone-else concept. This is what unblocks Phases 3 through 9.
- **Phase 2c — archive and drop.** Migrate Manager tables to archive, drop relations, remove models. Last, once nothing reads them.

---

## 9. What I did not verify

Stated plainly so nothing here is over-claimed:

- The test suite was not run. "Working" means the code is present and reads correctly, not that it is proven green.
- No database was inspected at runtime; every schema conclusion comes from migrations.
- No staging environment was exercised. No DAST, no live provider calls.
- Provider capability — Meta app review status, Razorpay account state, email provider account — is unknown and must be confirmed before Phases 5 and 8.
