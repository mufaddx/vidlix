# Live provider integrations

How Vidlix talks to the outside world, and what each provider is and is not
allowed to make the UI say.

The rule every adapter follows: **an absent credential is reported, never
worked around.** A provider slot with no credentials resolves to its
`Unconfigured*` adapter, which answers `provider_not_configured`. Nothing
downstream invents a paid state, a balance, an Instagram number, or a sent
email to cover the gap.

## Wiring

| Slot | Contract | Drivers | Adapter |
| --- | --- | --- | --- |
| Payments | `PaymentProviderInterface` | `razorpay` | `Services/Integrations/Payments/RazorpayPaymentProvider` |
| Payouts | `PayoutProviderInterface` | `razorpay`, `razorpayx` | `Services/Integrations/Payments/RazorpayXPayoutProvider` |
| Email | `EmailProviderInterface` | `sendgrid`, `smtp`, `ses`, `postmark` | `Services/Integrations/Email/{SendGrid,Smtp}EmailProvider` |
| Instagram | `InstagramProviderInterface` | `meta`, `instagram_graph` | `Services/Integrations/Instagram/MetaInstagramProvider` |
| Push | `PushProviderInterface` | `fcm`, `firebase` | `Services/Integrations/Push/FcmPushProvider` |

Binding happens in `app/Providers/AppServiceProvider.php`. The driver name comes
from `.env`; if the named driver's credentials are missing, the binding falls
back to the `Unconfigured*` adapter rather than handing out a live-looking
object that cannot do anything. A typo in `.env` therefore degrades to
`PROVIDER_NOT_CONFIGURED`, not to a half-working integration.

## Webhook endpoints

| Endpoint | Provider | Secret | Signature scheme |
| --- | --- | --- | --- |
| `POST /webhooks/payment` | Razorpay | `PAYMENT_WEBHOOK_SECRET` | `hmac_hex` — `X-Razorpay-Signature` |
| `POST /webhooks/payout` | RazorpayX | `PAYOUT_WEBHOOK_SECRET` | `hmac_hex` — `X-Razorpay-Signature` |
| `POST /webhooks/email/inbound` | inbound mail | `EMAIL_WEBHOOK_SECRET` | `hmac_hex` (or `sendgrid_ecdsa` / `basic`) |
| `POST /webhooks/email/events` | delivery / bounce | `EMAIL_WEBHOOK_SECRET` | same as inbound |
| `GET /webhooks/meta` | Meta subscription check | `META_WEBHOOK_VERIFY_TOKEN` | `hub.verify_token` compared with `hash_equals` |
| `POST /webhooks/meta` | Meta events | `META_APP_SECRET` | `hub_signature` — `X-Hub-Signature-256` |

Schemes live in `App\Services\Webhooks\SignatureVerifier` and are selected per
provider by `config('vidlix.webhooks.schemes')`. `X-Webhook-Signature` is
accepted alongside the provider's own header for every `hmac_hex` endpoint, which
is what the test suite and any generic sender use.

Two properties the ingest layer guarantees:

- **Replays do nothing twice.** A verified event id already seen returns
  `DUPLICATE_IGNORED` and no side effect runs. The original audit row is left
  exactly as it was.
- **A forged event cannot suppress a real one.** Requests that fail signature
  verification are logged under a throwaway id, so they never occupy the unique
  `provider_event_id` slot that the genuine delivery needs.

## Payments

```
requestPayment()  -> provider creates a hosted Payment Link -> payment row: pending
payer opens link -> pays -> browser redirect              -> still pending
POST /webhooks/payment (signature verified)
      -> GET /payment_links/{id} on the Razorpay API
      -> status "paid" AND amount >= invoiced amount
      -> payment: captured, ledger: +reserved for the seller
project completes -> reserved reversed, +available appended
```

Two independent facts are required before money moves: the webhook verified
against our secret, **and** the provider's own API reporting the payment as paid
for at least the invoiced amount. A browser redirect is never authoritative; nor
is the webhook body on its own. If `PAYMENT_PROVIDER` credentials are absent
there is no authoritative API to ask, so a signed webhook settles nothing and
the payment is marked `awaiting_provider`.

## Payouts

```
requestWithdrawal()  -> withdrawal: requested   (ledger untouched)
admin approves       -> RazorpayX payout instructed, withdrawal: processing
POST /webhooks/payout (verified) -> GET /payouts/{id} -> "processed"
                     -> ledger: -available, +withdrawn; withdrawal: paid
```

The admin screen offers approve and reject only. There is deliberately no
control anywhere in the product that marks a withdrawal paid by hand — that
state belongs to the payout webhook alone. Payout creation carries an
idempotency key derived from the withdrawal id, so a retried approval cannot pay
twice.

## Email

Outbound replies are stored first, then queued. `delivery_status` moves
`queued -> accepted -> delivered | bounced`, and each step comes from something
the provider said:

- `accepted` — the provider returned 2xx and took the message. Not delivery.
- `delivered` / `bounced` / `complained` — only ever written by
  `POST /webhooks/email/events`.
- `provider_not_configured` — stored, nothing sent, and the UI says so.

Inbound routing is by explicit token. Outbound mail sets
`Reply-To: reply+<routing_token>@EMAIL_INBOUND_DOMAIN`; inbound mail is matched
by pulling that token back out of the recipient (or Postmark's `MailboxHash`).
If the token is missing or unknown the mail is stored in `inbound_email_events`
with `match_status = unmatched` and stops there. It is never attached to a
thread by guessing from the sender address or the subject line, because two
different brands can email the same creator and the wrong guess leaks one
person's negotiation into another's.

Set SPF, DKIM and DMARC on `EMAIL_INBOUND_DOMAIN` before sending anything real.

## Instagram

Official Meta Graph API only; there is no scraping path anywhere in the code.

```
POST /instagram/connect -> Facebook Login dialog (signed state)
GET  /integrations/instagram/callback
      -> code -> long-lived token -> /me/accounts
      -> the Page's linked Instagram professional account
      -> encrypted token stored on instagram_accounts
POST /instagram/sync    -> GET /{ig-user-id}?fields=... and /insights
```

Which creator a callback belongs to comes from the HMAC-signed `state`
parameter, never from a `creator_id` in the query string. Only fields Graph
actually returned are stored: a metric the API omitted stays absent, and the
Instagram page renders "None" rather than a zero. A token error (Graph code 190
and friends) sets the account to `reauth_required` and clears nothing into a
number — the creator is asked to reconnect.

`POST /webhooks/meta` never carries metrics into the app. A verified event only
queues `SyncInstagramProfile`, so every figure the UI shows still originates
from an authenticated Graph read.

## Object storage

`config('vidlix.media.disk')` (defaulting to `FILESYSTEM_DISK`) decides where
project media lives. In production point it at an S3-compatible bucket
(`AWS_*`, works for S3, Cloudflare R2 and DigitalOcean Spaces); locally it stays
on the private local disk.

MySQL stores the disk name, the object key, mime type, size and the original
filename — never the bytes. Downloads go through
`GET /project-files/{file}`, which authorises the request against the project
and then redirects to a short-lived signed URL. The local disk cannot sign, so
it streams through the same authorised route instead.

## Push (optional)

`FcmPushProvider` implements FCM HTTP v1 using a service-account JWT. It stays
unconfigured until `FCM_CREDENTIALS_PATH` points at a readable credentials file.
Devices register with `POST /api/v1/devices`, and the response tells the client
plainly whether push is actually configured on the server.

## Adding another provider

1. Implement the contract in `app/Services/Integrations/…`.
2. Add the driver name to the relevant map in `AppServiceProvider::DRIVERS`.
3. If the provider signs webhooks differently, add the scheme to
   `SignatureVerifier` and name it in `config/vidlix.php`.
4. Write the test that proves it does not settle, deliver, or report anything the
   provider has not confirmed.
