# Database

MySQL in production, SQLite for tests. UTC throughout. Money in the smallest
unit (paise), always integers — floats lose money.

## Identity

| Table | Notes |
|---|---|
| `users` | One human, one account, however many roles |
| `roles`, `permissions` | Plus pivots |
| `creator_profiles`, `editor_profiles`, `brand_profiles` | One per role held |
| `usernames` | **The registry.** One unique index across creators and editors |
| `reserved_usernames` | Router paths and platform words |
| `username_history` | Retired handles, so old links redirect |
| `username_collisions` | Contested handles from the backfill, for a human to resolve |

### Why the registry exists

`creator_profiles.username` and `editor_profiles.username` each had their own
unique index, so a creator and an editor could both be `asif`. Harmless behind
`/u/` and `/editors/`; an impersonation vector the moment the address is
`vidlix.in/asif`.

The registry is the authority for resolution. The per-profile columns still
exist and are still written, kept in step by `RegistersUsername` on the model —
hooking the save is the only way to cover every path, including seeders and
factories.

The backfill **renames nobody**. A contested handle goes to
`username_collisions` with both claimants, oldest claim keeping the name, so a
person decides and the loser is told rather than finding out from a deploy.

## Communication

| Table | Notes |
|---|---|
| `conversations` | `owner_user_id` + `owner_scope`; `routing_token` unique |
| `conversation_participants` | Unique per pair. Read, archive, mute state per person |
| `messages` | `email_message_id`, `in_reply_to`, `email_references`, `delivery_status` |
| `external_contacts` | Unique on email |
| `inbound_email_events` | `provider_event_id` **unique** — the idempotency guarantee |
| `email_events` | Delivery and bounce |
| `conversation_reports` | Unique per person per thread |
| `user_blocks` | Unique per pair |

## Forms

| Table | Notes |
|---|---|
| `contact_forms` | `owner_user_id` + `owner_scope` — belongs to a person, not a page |
| `contact_form_versions` | Immutable `schema_json` |
| `contact_form_submissions` | `contact_form_version_id` is `restrictOnDelete` |

That restrict is deliberate: it protects the provenance of every answer.

## Marketplace

| Table | Notes |
|---|---|
| `campaigns` | Plus follower range, engagement, work mode, usage/revision/payment terms |
| `campaign_applications` | |
| `negotiations` | Both sides by user; `accepted_offer_id` denormalised |
| `negotiation_offers` | **Append-only.** Unique on `(negotiation, sequence)` |
| `projects`, `project_milestones`, `project_files`, `project_revisions` | |
| `favorites` | Personal, unique per subject |
| `shortlists` | Per campaign, unique per subject |

A counter-offer is a new row, never an edit, so accepted terms stay readable
exactly as accepted.

## Money

| Table | Notes |
|---|---|
| `ledger_accounts`, `ledger_entries` | **Append-only.** Every balance is summed, never stored |
| `payments`, `webhook_logs` | |
| `withdrawals`, `payout_accounts` | |
| `invoices`, `invoice_items` | |
| `commission_rules` | The pricing page and the ledger read the same row |

A stored balance is a number that can drift from the rows it claims to describe.

## Domains

| Table | Notes |
|---|---|
| `custom_domains` | Normalised hostname **unique** |
| `custom_domain_events` | Every state change |

## AutoDM

| Table | Notes |
|---|---|
| `instagram_media` | Metadata only — no bytes |
| `autodm_automations` | |
| `autodm_automation_versions` | Immutable, frozen at activation |
| `autodm_events` | `provider_event_id` **unique** |
| `autodm_runs` | Unique on `(automation, event, action)` |

## Archived

`archived_manager_*`, `archived_management_*` — renamed, not dropped, so the
record of who managed whom survives and reversing it is a rename back.

## Conventions

- Foreign keys with deliberate cascade choices. `restrictOnDelete` where
  deleting would orphan provenance
- Uniqueness that matters is a **unique index**, never only an application check
- Money integers in the smallest unit
- Timestamps UTC
- Tokens encrypted; `token_encrypted` in `$hidden`
- No large binaries in MySQL

## Migrations

```bash
php artisan migrate
php artisan migrate --pretend   # inspect first
```

Migrations that alter an existing table check `Schema::hasColumn` first. One
that assumes the shape of the table it alters fails on exactly the databases
that have been running longest.
