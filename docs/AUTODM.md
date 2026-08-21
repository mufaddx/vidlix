# AutoDM

Instagram comment-to-DM automation. Somebody comments, they hear back.

Served at `autodm.vidlix.in`; the public product page is `vidlix.in/autodm`.

## What Instagram actually permits

This shapes the whole product, so it comes first.

| Capability | Status |
|---|---|
| Public reply to a comment on your own post | **Permitted** |
| Private reply (DM) to somebody who commented | **Conditional** — messaging permissions, granted after Meta app review, within a bounded window |
| Unsolicited DM | **Not permitted** |
| Follow-up messages, drip sequences | **Not permitted** |
| Auto-follow, auto-like, auto-comment on others | **Not permitted** |
| Scraping, browser bots, password login | **Not permitted** |

**Comment-to-DM works. Almost everything after it does not.** There is no
compliant way to build sequences or outreach on Instagram, and the landing page
says so rather than selling around it.

## Architecture

| Piece | Where |
|---|---|
| Capability rules | `App\Services\AutoDm\Capabilities` |
| Keyword matching | `App\Services\AutoDm\KeywordMatcher` |
| Building and activation | `App\Services\AutoDm\AutomationBuilder` |
| Execution | `App\Services\AutoDm\AutomationEngine` |
| Sending | `App\Services\AutoDm\AutoDmSender` |
| Webhook entry | `App\Services\Instagram\MetaEventHandler` |

Deliberately separate from the existing `automations` tables, which are
marketplace automations. Folding them together would mean one execution log
covering two products with different failure modes and different provider
limits.

## Builder flow

```
Select → Trigger → Action → Review → Activate
```

**Select** — a post, or every post on the account. Whole-account is the broader
and more dangerous default, so it is chosen explicitly rather than assumed.

**Trigger** — any comment, or keywords. Matching ignores capitals and accents,
because people do not type carefully in comments. Whole-word matching is opt-in:
"art" should be able to avoid firing on "start", but not silently.

A keyword trigger with no keywords is refused. Treating it as "match everything"
would turn an unfinished automation into a reply to every comment on the account.

**Action** — public reply, private reply, or both. Anything the account cannot
do is **disabled with the reason shown**, not offered and failed later.

**Review** — the terms, the capabilities, and the platform limits, on the screen
where somebody is about to commit. Reading them afterwards from an empty log is
reading them too late.

**Activate** — everything revalidated at this moment: connection, media
ownership, provider capability. A permission can be revoked and a post deleted
between drafting and switching on.

## Versions

Activation freezes the terms into `autodm_automation_versions`. Editing writes a
new version and leaves the running one alone, so a run months later can still
say exactly which rules produced it.

Duplicating always produces a draft. Copying something must not switch it on.

## Execution

```
verify → record event → resolve account → match automations
  → check capability → check window → record run → send
```

### Idempotency

Instagram retries, and a retry must not send a second reply to the same person.

- `autodm_events.provider_event_id` unique
- `autodm_runs` unique on `(automation, event, action)`

Both are database constraints. Two deliveries can pass an application check in
the same instant; only the index settles which one sends.

The comment id is the natural event key: one comment can only ever be answered
once.

### Run states

```
received  validated  matched  queued  processing  sent
skipped   failed     retry_scheduled  permanently_failed
```

**`skipped` is not `failed`.** An action the provider does not permit did not go
wrong — it was never allowed, and calling it a failure invites a retry that can
never succeed. Calling it `sent` would be a lie in the log somebody reads to
find out what happened.

Reason codes: `provider_not_configured`, `account_disconnected`,
`token_expired`, `app_review_pending`, `missing_permission`,
`outside_messaging_window`, `capability_not_enabled`, `empty_message`,
`missing_comment`.

Only transient failures are retried. A policy or capability refusal will refuse
again just as firmly.

## Permissions

Read from `instagram_accounts.granted_scopes` — what Meta **returned**, not what
was requested. Asking for a scope and being given it are different things, and
treating the request as the answer is how an automation gets built on a
permission that was declined.

`instagram_manage_messages` gates private replies.

## Current limitation

`AutoDmSender` does not send. No live driver is approved to perform a reply, so
rather than invent a call that fails at runtime it reports
`capability_not_enabled` honestly — the same answer the builder already gives
before anyone activates an automation around it.

The rest of the engine is real: webhook, matching, run records, idempotency,
skip reasons.

**To enable sending:** add reply methods to the Instagram provider contract,
implement them against the Graph API, and replace the honest refusal in
`AutoDmSender::send()`.

## Blockers

| # | Blocker | Blocks |
|---|---|---|
| B-1 | Meta app review for messaging permissions | Private replies |
| B-2 | Professional account requirement in onboarding | Connection |
| B-3 | Comment webhook subscription configured with Meta | Execution |
| B-4 | `autodm.vidlix.in` DNS and TLS | The whole product |

**B-1 is the critical path.** Without it AutoDM ships as public comment-reply
automation — still a real product, but a different one, and a decision worth
making deliberately rather than discovering at launch.

## Testing

`tests/Feature/AutoDmTest.php` — matching, duplicate deliveries, media scoping,
draft automations not firing, capability gating, skip-not-fail, versioning,
cross-account authorization, and that the landing page states the limits.
