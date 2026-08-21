# Email threading

Somebody with no account writes to a creator through their public form. The
creator replies from Vidlix. The visitor replies by email. That reply must land
back on the same conversation — not in a new one, and never in somebody else's.

## Identities

Sender addresses are configured per role and resolved **server-side from the
conversation's own scope**, never from anything a client sends. A sender address
cannot be spoofed by asking for it.

| Scope | From |
|---|---|
| creator | `creator@vidlix.in` |
| editor | `editor@vidlix.in` |
| brand | `brand@vidlix.in` |
| support | `help@vidlix.in` |
| fallback | `no-reply@vidlix.in` |

`App\Services\Email\OutboundEmailService::identityFor()`. The display name is
the person plus their handle — "Mira (@mira) via Vidlix" — because a recipient
should know which enquiry this answers.

## Reply-To routing

```
<scope>+<routing_token>@<EMAIL_INBOUND_DOMAIN>
```

`conversations.routing_token` is unique. The scope prefix means a creator thread
still routes even if the plus-address token is stripped somewhere along the way,
which some mail systems do.

## Outbound

1. Verify the sender may reply to this conversation
2. Store the message row **first**, always
3. Resolve recipient and identity
4. Queue the send
5. Record the provider's answer in `email_events`
6. Update `messages.delivery_status`

`delivery_status` only ever reflects what the provider actually said. Nothing
reads "sent" because a form was submitted.

## Inbound

`webhooks/email/inbound`, signature-verified before anything is read.

1. Verify the provider signature — an unverified delivery is logged and dropped
2. Check `inbound_email_events.provider_event_id` (unique) — a retry is a no-op
3. Parse the Reply-To routing address for the token
4. Fall back to `In-Reply-To` / `References` against stored message ids
5. Resolve the conversation
6. **Verify the sender belongs to that thread**
7. Store the message, mark unread, notify

Step 6 is the one that matters. Without it an external sender who guesses a
routing token could inject a message into somebody else's conversation.

Unmatched mail is recorded with `match_status` for admin review rather than
silently dropped or turned into a new thread.

## Idempotency

Two indexes carry it:

- `inbound_email_events.provider_event_id` unique
- `conversations.routing_token` unique

Both are database constraints rather than application checks, because two
deliveries can pass a check in the same instant and only the index settles it.

## Signature schemes

`App\Services\Webhooks\SignatureVerifier`:

| Scheme | Provider |
|---|---|
| `svix` | Resend — with a 300-second replay window |
| `sendgrid_ecdsa` | SendGrid Event Webhook |
| `basic` | Postmark inbound |
| `hmac_hex` | Generic |

A delivery that cannot be proven authentic is never processed.

## Configuration

```
EMAIL_PROVIDER=resend
EMAIL_API_KEY=
EMAIL_INBOUND_DOMAIN=vidlix.in
EMAIL_REPLY_PREFIX=reply
EMAIL_WEBHOOK_SECRET=
EMAIL_WEBHOOK_SCHEME=svix
```

Without a provider, messages are stored with `provider_not_configured` and the
interface says so. Nothing is reported as sent.

## SPF, DKIM, DMARC

Required on the sending domain before any of this reaches an inbox reliably.
Custom sender domains are only accepted after verification plus all three — see
`docs/CUSTOM-DOMAINS.md` for the equivalent hostname rules.

## Testing

`tests/Feature/EmailIntegrationTest.php`, `ResendEmailTest.php`,
`UnifiedInboxTest.php`.
