# Vidlix — Implementation Roadmap

**Date:** 2026-08-21. Follows the phase structure in the revised spec §33, with one recommended change (Phase 2 split) and explicit blocking dependencies.

---

## Phase 1 — Audit and foundation ✅ *complete*

Delivered: `AUDIT-REPORT.md`, `PAGE-INVENTORY.md`, `ROUTE-INVENTORY.md`, `API-INVENTORY.md`, `DATABASE-AUDIT.md`, `SECURITY-AUDIT.md`, `MANAGER-REMOVAL-PLAN.md`, `AUTODM-CAPABILITY-MATRIX.md`, this roadmap.

**Remaining Phase 1 work before Phase 2 starts:**
- Run the test suite and record the actual pass/fail baseline (not yet done)
- Add `APP_APP_URL`, `AUTODM_APP_URL`, `ADMIN_APP_URL` and the seven `MAIL_FROM_*` variables to `config/vidlix.php` and `.env.example`
- Replace the `hello@example.com` default (finding S-2)
- Write `docs/DOMAINS.md`

---

## Phase 2 — Manager removal (split — see `MANAGER-REMOVAL-PLAN.md`)

### 2a — Deny access · *hours* · no blockers
Routes, navigation, signup option, admin tab, abilities. Regression tests asserting denial. Data untouched, fully reversible.

### 2b — Rewrite `WorkspaceContext` · *the critical path*
**Blocks Phases 3–9.** Remove all acting-for logic; reduce to role and profile switching. Update 5 dependent services. Preserve the session-holds-IDs-only property.

**Do this alongside:** introduce `app/Policies` and route every bound model through a policy. This closes the IDOR risks in `API-INVENTORY.md` §3 and gives every later phase a consistent authorization seam. It is far cheaper now than retrofitted in Phase 9.

### 2c — Archive and drop · *last*
Only after nothing reads the tables. **Check `management_subscriptions` for live rows first** — settle, do not drop.

---

## Phase 3 — Username registry and public profile routing

**Blocked by:** 2b. **Blocks:** 4, 6.

1. `usernames` registry with a single global unique index (`DATABASE-AUDIT.md` §2)
2. Collision-detecting backfill + documented rename policy — **do not silently deduplicate**
3. `reserved_usernames` seed
4. `GET /{username}` catch-all, registered **last**, reserved-path guard first
5. `GET /{username}/contact`
6. Retire `/u/{username}`, `/editors/{username}`, `/editor/{username}` with 301s
7. SEO: title, description, Open Graph image, sharing metadata
8. Copy Public Link and Share on the app-side profile
9. Shared state components (loading, empty, error, permission denied, suspended, verification pending/rejected, rate limited) — built once here, applied as each page is touched

**Fixes S-1.** Acceptance: no role prefix in any public URL; collision impossible; reserved paths protected; disabled and non-existent profiles return indistinguishable 404s.

---

## Phase 4 — Form customization

**Blocked by:** 3.

1. Typed schema contract for `schema_json` — field key, type, label, placeholder, required, options, visibility rules, order. **Keep the existing immutable versioning; do not normalise fields into rows** (`DATABASE-AUDIT.md` §4)
2. Field CRUD and reorder, for **creators and editors alike** — this is when the two inquiry services merge
3. Field types: short text, long text, phone, company, website, campaign type, budget range, service required, deadline, Instagram username, dropdown, multiple choice, checkbox, file attachment
4. Conditional visibility — the "Other → please specify" case
5. **Rewrite `validateAgainstSchema()` to enforce types, option lists, conditional requirements, and reject unknown keys** — fixes S-3, blocking
6. Preview, publish, disable, copy link
7. Public form page with all states
8. `POST /{username}/contact` — throttled, Turnstile, honeypot, secure attachments

Acceptance: the spec's flagship journey (add College + Other, publish, submit) passes end to end.

---

## Phase 5 — Email identities and threading

**Blocked by:** 4. Much of this already works.

1. Drive the seven `MAIL_FROM_*` identities from config; `OutboundIdentity` resolves from the conversation's owner scope, **never from client input**
2. Verify creator replies leave from `creator@vidlix.in`, editor replies from `editor@vidlix.in`
3. Verify the unknown-sender → admin review path
4. Inbox: role-priority ordering, All/Creator/Editor/Brand filters, source badges, delivery state, search
5. Archive, mute, report, block
6. Consolidate `/chat` into `/inbox`

Acceptance: visitor reply returns to the same conversation; no duplicate thread; duplicate webhook is a no-op.

---

## Phase 6 — Custom domains

**Blocked by:** 3. **Provider-blocked:** Cloudflare for SaaS account.

`custom_domains` + `custom_domain_events`; the nine-state machine; DNS instructions; ownership verification; SSL provisioning; hostname → public-form-only routing.

**Security controls are not optional here** (`SECURITY-AUDIT.md` §5): host-header protection, SSRF rejection of private hostnames, unique normalised hostname, no activation before ownership **and** SSL, and a custom hostname that can reach nothing but the public form.

---

## Phase 7 — Campaigns, negotiations, projects

**Blocked by:** 2b.

Promote negotiations to first-class records (`negotiations`, `negotiation_offers`) rather than the current `proposals`; add `favorites`, `shortlists`, `project_milestones`; wire campaign statuses, interest tracking, shortlisting and deal conversion with real notifications and audit events.

---

## Phase 8 — AutoDM

**Blocked by:** 2b. **Provider-blocked:** B-1 Meta app review (`AUTODM-CAPABILITY-MATRIX.md` §5).

Build order in that document §6. **Steps 1–5 ship a compliant product without app review**; private replies stay dark behind a live capability check until B-1 clears.

---

## Phase 9 — Hardening and launch

Admin additions (AutoDM accounts, webhook health, failed runs, usage, backup status); payments reconciliation and refunds; storage verification (`FILESYSTEM_DISK=s3`, signed URLs, MIME enforcement); missing error pages; staging DAST; backup and rollback rehearsal; full end-to-end suite.

---

## Critical path

```
1 → 2a → 2b ─┬→ 3 → 4 → 5 → 6 ─┐
             ├→ 7 ──────────────┼→ 9
             └→ 8 ──────────────┘
                 2c (anytime after 2b)
```

**2b is the single most important task.** Phases 3, 7 and 8 all wait on it, and bundling the policy layer into it prevents an expensive Phase 9 retrofit.

---

## Risk register

| # | Risk | Impact | Mitigation |
|---|---|---|---|
| R-1 | Username collisions exist in production data | Users lose their handle | Detect and report before migrating; grace period and notification, never a silent rename |
| R-2 | Meta app review denied or slow | AutoDM ships reduced | Build steps 1–5 first; treat private reply as additive |
| R-3 | Live `management_subscriptions` at Phase 2c | Financial obligation dropped | Query and settle before dropping |
| R-4 | IDOR found late | Emergency fix under launch pressure | Policy layer in 2b, not Phase 9 |
| R-5 | Cloudflare for SaaS unavailable | Custom domains blocked | Phase 6 is independent of 7 and 8 — defer without stalling |
| R-6 | Test baseline unknown | Regressions invisible | Run the suite before Phase 2 |

---

## Definition of done, per feature

No feature is complete until: UI, backend, persistence, validation, authorization, all eleven required states, notifications where required, audit logging where required, responsive behaviour, and automated tests are all present. **A page that renders is not a feature.**
