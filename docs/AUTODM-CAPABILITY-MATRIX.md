# Vidlix — AutoDM Capability Matrix

**Date:** 2026-08-21.

**Status in repository: does not exist.** Zero references to AutoDM, `autodm.vidlix.in`, comment-to-DM automation, or Instagram media storage. The existing `Automation` / `AutomationRun` models are *marketplace* automations and must not be conflated with this product.

This document records what Meta's platform actually permits, so the build does not promise capability the API will not deliver. **Every row marked "verify" must be confirmed against current Meta documentation and the app's own review status before it is built** — platform policy changes, and this matrix is a planning aid, not an authority.

---

## 1. What already exists and is reusable

Genuinely valuable groundwork, all production-quality:

| Asset | Location | Reuse |
|---|---|---|
| Meta OAuth | `MetaInstagramProvider` | Authorization URL, state generation and validation, server-side code exchange |
| Token encryption | Same | Extend to AutoDM accounts |
| Meta webhook signature | `SignatureVerifier::verifyHubSignature()` | Reuse directly for comment events — `X-Hub-Signature-256` |
| Webhook endpoint | `webhooks/meta` | Extend with comment-event handling |
| Meta event handler | `Instagram/MetaEventHandler` | Extend |
| Provider fallback | `UnconfiguredInstagramProvider` | The right pattern for the not-configured state |
| Job infrastructure | `SyncInstagramProfile` | Model for media refresh |
| Feature flags | `EnsureFeature` middleware | Gate AutoDM behind a flag |

The hard, easy-to-get-wrong parts — OAuth state, signature verification, encrypted tokens — are done.

---

## 2. Capability matrix

Legend: **Permitted** — officially supported · **Conditional** — supported with approval or constraints · **Not permitted** — do not build · **Verify** — confirm current policy before building

| Capability | Status | Constraint | Build? |
|---|---|---|---|
| Official OAuth connection | Permitted | Professional (Business/Creator) account required | Yes |
| Read profile metadata | Permitted | Approved scopes | Yes |
| Read own media (posts, reels) | Permitted | Approved scopes | Yes |
| Read own stories | **Verify** | More restricted than feed media | Gate |
| Read comments on own media | Permitted | Webhook subscription | Yes |
| **Public reply to a comment** | Permitted | On own media | Yes |
| **Private reply to a comment (DM)** | **Conditional** | Requires messaging permissions **and Meta app review**. Bounded reply window after the comment | **Gate behind review** |
| Send unsolicited DM | **Not permitted** | — | **No** |
| DM outside the messaging window | **Not permitted** | Standard messaging window applies | **No** |
| Follow-up DM sequences | **Not permitted** | Outside the window | **No** |
| Include a link in a private reply | **Verify** | Policy-sensitive | Gate |
| Read/send Instagram DMs generally | Conditional | Messaging permissions + review | Gate |
| Auto-follow, auto-like, auto-comment on others | **Not permitted** | Platform policy violation | **No** |
| Scraping / browser automation | **Not permitted** | Explicitly forbidden by spec and Meta | **No** |
| Password-based access | **Not permitted** | Never ask for an Instagram password | **No** |

### The product-defining constraint

**Comment-to-DM works. Everything after it mostly does not.**

Private replies to comments are the one durable automated-DM path, and they are bounded: one reply, tied to a specific comment, inside a limited window, only after app review. There is no compliant way to build drip sequences, unsolicited outreach, or follow-ups.

The AutoDM landing page (spec §18) must state these limitations plainly. Marketing that implies unlimited automated DMs would be both a policy violation and a promise the code cannot keep.

---

## 3. Required UI honesty (spec §22)

When an action is unsupported or unapproved, it must:
- **not** be sent
- show a clear, specific reason
- be logged safely
- **never** be recorded as success

This means the execution state machine needs `Skipped` as a first-class outcome distinct from `Failed`, carrying a reason code — for example `provider_capability_missing`, `outside_messaging_window`, `app_review_pending`, `rate_limited`.

Concretely: if app review has not been granted, the builder must show the private-reply action as unavailable with the reason, not offer it and fail at runtime.

---

## 4. Execution states

Per spec §22: `Received → Validated → Matched → Queued → Processing → Sent`, with `Skipped`, `Failed`, `Retry Scheduled`, `Permanently Failed` as terminal or branching outcomes.

Idempotency: key on the Meta event ID. `autodm_events.provider_event_id` must be `unique`, following the pattern already proven by `inbound_email_events`.

Retry only genuinely retryable failures — transient network errors and rate limits. Never retry a capability or policy rejection.

---

## 5. Blockers before Phase 8

| # | Blocker | Owner | Blocks |
|---|---|---|---|
| B-1 | Meta app review status for messaging permissions unknown | Product | Private replies — the core feature |
| B-2 | Professional account requirement not yet surfaced in onboarding | Eng | Connection flow |
| B-3 | Webhook subscription for comment events not configured | DevOps | Execution engine |
| B-4 | `autodm.vidlix.in` DNS and TLS not provisioned | DevOps | Whole product |
| B-5 | Current Meta rate limits and messaging-window rules not confirmed | Eng | Quota handling |

**B-1 is the critical path.** Without messaging-permission approval, AutoDM ships as comment-reply automation only. That is still a real product — but it is a different one, and the decision should be made deliberately rather than discovered at launch.

---

## 6. Recommended build order

1. Landing page stating capabilities and limitations honestly (no dashboard yet)
2. Connection flow, reusing the existing OAuth — with clear professional-account and permission requirements
3. Media refresh via job, storing metadata only
4. Automation builder with **public comment reply only** — fully functional without app review
5. Comment webhook and execution engine, including `Skipped` handling
6. Private reply, gated behind a capability check that reads actual granted permissions — dark until B-1 clears
7. Execution history, failed runs, usage
8. Billing and subscriptions

Steps 1 through 5 deliver a working, compliant product with no dependency on B-1.
