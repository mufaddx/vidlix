# Vidlix

Laravel 13 · PHP 8.4 · MySQL (SQLite in tests) · Blade · hand-written CSS/JS.

Creator × Editor × Brand marketplace, plus Instagram AutoDM.

## Read first

| Doc | For |
|---|---|
| `docs/ARCHITECTURE.md` | Non-negotiables |
| `docs/DOMAINS.md` | The four hosts, public URL shape |
| `docs/SECURITY.md` | What is enforced and why |
| `docs/DATABASE.md` | Schema and its conventions |
| `docs/TESTING.md` | How to run and how tests are written here |

## Commands

```bash
php artisan test                # 408 tests
vendor/bin/pint                 # format
vendor/bin/phpstan analyse      # static analysis
npx playwright test             # browser
```

All three gate CI. Run them before committing.

## House rules

**No fake anything.** No invented balances, metrics, delivery states or
counters. A provider that is not configured says `PROVIDER_NOT_CONFIGURED`; it
does not pretend. A number that was never synced reads as "not synced", never as
zero.

**Refusals are 404, not 403.** A 403 confirms the thing exists.

**Uniqueness that matters is a database index.** Usernames, hostnames, webhook
event ids, automation runs. Two requests can pass an application check in the
same instant.

**Never trust client input for ownership.** Owner from the URL, sender identity
from the conversation scope, permission from what the provider returned.

**Money is integers in the smallest unit**, and balances are summed from an
append-only ledger, never stored.

**Large media never touches MySQL.** Object storage, with authorization checked
*before* a signed URL is issued.

**Design tokens only.** No raw hex — `ThemeAndButtonContrastTest` will fail the
build.

## Where things live

```
app/Policies/            ownership rules, one per resource
app/Services/AutoDm/     Instagram comment automation
app/Services/Deals/      negotiations, milestones, shortlists
app/Services/Domains/    custom hostnames
app/Services/Forms/      contact form builder and submissions
app/Services/Identity/   username registry
app/Services/Profiles/   role applications and their review
app/Support/Forms/       schema contract and answer validation
resources/views/partials/state.blade.php   every "not ready" state
```

## Comments

Explain **why**, not what. The interesting comment is the one that says what
would go wrong otherwise — a reader can see what the code does.

## Not implemented

- WhatsApp (`docs/WHATSAPP.md`)
- AutoDM sending (`docs/AUTODM.md` — blocked on Meta app review)
- Custom domain provider (`docs/CUSTOM-DOMAINS.md` — no driver configured)

Each refuses honestly rather than pretending.
