# Instagram

Official Meta OAuth and Graph API only. No scraping, no browser bots, no asking
for a password.

## Connecting

1. Member clicks Connect
2. Redirect to Meta OAuth with the configured scopes
3. **State validated** on return — `MetaInstagramProvider::stateFor()` and
   `creatorProfileIdFromState()`
4. Code exchanged **server-side** for a long-lived token
5. Token encrypted at rest; `token_encrypted` is in `$hidden`
6. Account metadata and granted scopes stored

## Granted, not requested

`instagram_accounts.granted_scopes` holds what Meta **returned**.

Asking for a scope and being given it are different things. Treating the request
as the answer is how a feature gets built on a permission that was declined —
see `App\Services\AutoDm\Capabilities::hasScope()`.

## Configuration

```
INSTAGRAM_PROVIDER=meta
META_APP_ID=
META_APP_SECRET=
META_REDIRECT_URI=https://app.vidlix.in/integrations/instagram/callback
META_GRAPH_VERSION=v21.0
META_SCOPES=instagram_basic,instagram_manage_insights,pages_show_list,pages_read_engagement,business_management
META_WEBHOOK_VERIFY_TOKEN=
```

Without these, `UnconfiguredInstagramProvider` is bound and the interface says
Instagram is not connected rather than failing at the first API call.

## Requirements the member must meet

- A **professional** account (Business or Creator). Instagram does not expose
  comments or messaging on personal accounts
- Correct permissions granted at OAuth
- Meta app review for anything beyond basic reads

These are surfaced in the interface, not discovered from an empty log.

## Webhooks

`GET|POST /webhooks/meta`.

- `GET` is the subscription handshake, checked against
  `META_WEBHOOK_VERIFY_TOKEN` with `hash_equals`
- `POST` is verified with `X-Hub-Signature-256` — HMAC-SHA256 of the raw body
  with the **app secret**, not the verify token

An unverified delivery is logged as rejected and nothing downstream runs.

Meta sends no event id, so an exact replay of the same signed body is what is
deduplicated on. For comments, the comment id is the natural key — one comment
can only ever be answered once.

## What a webhook is and is not

A webhook says something changed. It is **not** a source of metrics.

`MetaEventHandler` queues a Graph API sync for the affected account, so every
number the interface shows comes from an authoritative read rather than from a
webhook body. Comment events additionally go to the AutoDM engine.

## Insights

Only what Meta permits, only from a live API response. Empty insights are shown
as empty. A follower count that was never synced reads as "not synced" rather
than as zero — an invented number is the same lie as an invented balance.

## Token expiry

`token_expires_at` is checked before any action. An expired connection permits
nothing and the interface shows a reconnect state with the reason, rather than
failing silently.

## Not supported

- Instagram DM inbox as an inbox source
- Unsolicited messages of any kind
- Auto-follow, auto-like, auto-comment on other accounts
- Anything outside the official API

See `docs/AUTODM.md` for what comment automation can and cannot do.
