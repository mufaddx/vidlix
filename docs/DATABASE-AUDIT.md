# Vidlix — Database Audit

**Date:** 2026-08-21. 21 migrations, 68 models. Conclusions are drawn from migration files; no runtime database was inspected.

---

## 1. Existing tables, mapped to the required concept list

| Required (spec §24) | Exists | Table | Status |
|---|---|---|---|
| users | Yes | `users` | W |
| roles / user_roles | Yes | `roles`, `permissions` + pivots | W |
| workspaces / workspace_members | Partial | modelled as profiles + `ManagerAssignment` | **P — rework** |
| creator_profiles | Yes | `creator_profiles` | W |
| editor_profiles | Yes | `editor_profiles` | W |
| public_profiles | Yes | `creator_public_pages` | P — creator-shaped |
| portfolios / portfolio_items | Yes | `portfolio_items` | P — no ordering column found |
| social_accounts | Yes | `creator_social_links`, `instagram_accounts`, `social_platforms` | W |
| verification_requests | Partial | status columns on profiles + `brand_documents` | P |
| public_forms | Yes | `contact_forms`, `contact_form_versions` | P |
| public_form_fields | **No** | — | **M** |
| public_form_submissions | Yes | `contact_form_submissions` | W |
| public_form_submission_values | Partial | `answers` JSON on submission | P — acceptable, but not queryable |
| custom_domains | **No** | — | **M** |
| custom_domain_events | **No** | — | **M** |
| campaigns | Yes | `campaigns` | W |
| campaign_applications | Yes | `campaign_applications` | W |
| favorites / shortlists | **No** | — | **M** |
| negotiations | Partial | `proposals`, `proposal_versions` | **P — not first-class** |
| deals | Partial | folded into `projects` | P |
| projects / milestones / files | Yes | `projects`, `project_files`, `project_revisions` | P — milestones table not found |
| conversations / participants / messages | Yes | all three | W |
| email_threads | Partial | folded into `conversations` + `messages` | W by design |
| email_events | Yes | `email_events`, `inbound_email_events` | W |
| payments / payment_events | Yes | `payments`, `webhook_logs` | W |
| wallet_ledgers | Yes | `ledger_accounts`, `ledger_entries` | W |
| withdrawals | Yes | `withdrawals`, `payout_accounts` | W |
| invoices / invoice_items | Yes | both | W |
| agreements | Yes | `agreements`, `agreement_acceptances` | W |
| notifications | Yes | `notifications`, `notification_preferences` | W |
| support_tickets | Yes | `support_tickets`, `support_threads` | W |
| automation_rules / runs | Yes | `automations`, `automation_runs` | P — marketplace, not AutoDM |
| automation_versions / events | **No** | — | **M** |
| media_assets | Partial | per-feature file tables | P |
| audit_logs | Yes | `audit_logs` | W |
| security_events | Yes | `login_attempts`, `otp_verifications` | P |
| subscriptions / events | Yes | `management_subscriptions`, `management_plans` | **R — Manager-scoped** |

---

## 2. Critical defect: username uniqueness is not global

```
creator_profiles.username  string  UNIQUE
editor_profiles.username   string  UNIQUE
```

Two independent unique indexes. **A creator and an editor may hold the same username today.** Under `/{username}` routing this is an unresolvable collision and an impersonation vector (S-1 in the security audit).

### Required remediation

1. Create a `usernames` registry:

   | Column | Type | Notes |
   |---|---|---|
   | `id` | pk | |
   | `username` | string | **UNIQUE**, normalised lowercase |
   | `user_id` | fk users | owner |
   | `profile_type` | enum(creator, editor) | which profile it resolves to |
   | `profile_id` | unsigned bigint | polymorphic target |
   | `status` | enum(active, reserved, retired) | retired supports redirects |
   | `released_at` | timestamp nullable | cooldown before reuse |
   | timestamps | | |

   Index: `(username, status)`, `(user_id)`.

2. **Backfill migration must detect collisions before inserting.** Do not blindly deduplicate. Write the collision set to a report table, notify affected users, and apply a documented rename policy (proposal: the older `created_at` keeps the username; the newer is suffixed and notified with a 30-day grace period to choose a new one).

3. Add a `reserved_usernames` seed covering at minimum: `login, signup, register, about, contact, pricing, terms, privacy, support, admin, app, autodm, api, settings, dashboard, assets, robots.txt, sitemap.xml`, plus every first path segment already registered in `routes/web.php` (`creators`, `editors`, `brands`, `campaigns`, `blog`, `p`, `u`, `inbox`, `chat`, `projects`, `applications`, `portfolio`, `proposals`, `invoices`, `earnings`, `notifications`, `automations`, `instagram`, `disputes`, `roles`, `editor`, `brand`, `discover`, `download`, `webhooks`, `verify-email`, `forgot-password`, `two-factor`, `logout`, `workspace`, `integrations`, `management`, `project-files`, `up`).

4. Keep the per-profile `username` columns during transition; make the registry authoritative for resolution, then drop the columns once nothing reads them.

---

## 3. Manager tables — removal plan

| Table | Rows depended on by | Action |
|---|---|---|
| `manager_profiles` | `AccountProvisioner`, admin views | Archive then drop |
| `manager_assignments` | **`WorkspaceContext` (core authz)**, `MarketplaceEngine`, `PersonalDataService` | Rewrite dependents first, then archive and drop |
| `manager_invitations` | `ManagerInvitationController` | Archive then drop |
| `manager_activity_logs` | Audit | **Archive and retain** — historical audit record |
| `management_subscriptions`, `management_plans` | Billing | Archive; check for live subscriptions before dropping |

Order matters: `manager_assignments` cannot be dropped until `WorkspaceContext` no longer queries it. See `docs/MANAGER-REMOVAL-PLAN.md`.

---

## 4. Tables to add

**Phase 3:** `usernames`, `reserved_usernames`, `username_history`
**Phase 4:** `public_form_fields` (or a typed schema contract inside `schema_json` — see below), `form_field_options`
**Phase 6:** `custom_domains`, `custom_domain_events`
**Phase 7:** `negotiations`, `negotiation_offers`, `favorites`, `shortlists`, `project_milestones`
**Phase 8:** `instagram_media`, `autodm_automations`, `autodm_automation_versions`, `autodm_runs`, `autodm_events`
**Phase 9:** `security_events` (consolidated), `media_assets` (unified)

### Note on form fields

`contact_form_versions.schema_json` is already an immutable versioned document bound to each submission — that is the correct design and should be kept. The recommendation is **not** to normalise fields into rows, but to define and enforce a typed schema contract (field key, type, label, placeholder, required, options, visibility rules, order) and validate `schema_json` against it on write. A separate `public_form_fields` table would duplicate the version history and reintroduce the mutability the current design deliberately avoids.

---

## 5. Data integrity observations

**Good:**
- Foreign keys used consistently with deliberate `cascadeOnDelete` / `nullOnDelete` / `restrictOnDelete` choices (`contact_form_version_id` is `restrictOnDelete`, which correctly protects submission provenance).
- `inbound_email_events.provider_event_id` unique — idempotency at the schema level.
- `conversations.routing_token` unique — deterministic reply routing.
- `conversation_participants` unique on `(conversation_id, user_id)`; `last_read_at` per participant, which is correct (read state is per person, not per thread).
- Ledger is append-only by design.

**Gaps:**
- No `milestones` table despite the spec requiring milestones.
- `portfolio_items` appears to lack an explicit ordering column; reorder is a spec requirement.
- `security_events` is spread across `login_attempts` and `otp_verifications` rather than consolidated.
- Soft deletes are not used broadly; confirm which tables need them for audit retention.

---

## 6. Storage

`config/filesystems.php` has a correctly configured S3/R2 disk — `region: auto`, custom `endpoint`, path-style, with explicit notes on AWS SDK checksum defaults that break R2. That is the hard part, already solved.

**But `FILESYSTEM_DISK` defaults to `local`.** Production must set it to `s3`. Verify before launch:
- Signed upload URLs
- Signed, expiring download URLs
- Authorization check *before* the signed URL is issued
- Server-side MIME and extension validation
- Per-tenant object key prefixes
- Multipart/resumable upload for video

No evidence of large binaries in MySQL — good.
