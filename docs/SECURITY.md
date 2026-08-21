# Security

What is enforced, where, and why it is done that way.

## Principles

**Refusals are 404, not 403.** A 403 confirms the thing exists, and knowing that
project 41 is real and that somebody is refusing you is information you did not
have. This holds for projects, conversations, invoices, files, negotiations and
automations.

**Database constraints over application checks.** Uniqueness that matters —
usernames, hostnames, webhook event ids, automation runs — is a unique index.
Two requests can pass an application check in the same instant; only the index
settles it.

**Never derive trust from client input.** Owners come from the URL, sender
identities from the conversation scope, permissions from what the provider
returned. Never from a request body.

**A feature that cannot work says so.** Unconfigured providers refuse clearly
rather than pretending. An action that would fail is disabled with a reason
rather than offered and failed later.

## Authentication

- Three-step signup; no user exists until the emailed code is verified
- Throttled: `throttle:login`, `throttle:otp`, `throttle:register`
- OTP hashed, expiring, attempt-limited
- Password reset by OTP, single use
- TOTP 2FA with recovery codes (`pragmarx/google2fa`)
- Admin has a **separate front door** at `/admin/login`; a member session is
  never a way in

## Authorization

Policies in `app/Policies`, one per resource: project, conversation, invoice,
project file, campaign, negotiation, custom domain, contact form.

Centralised so a new route inherits the rule rather than restating it from
memory. Asserted in `tests/Feature/AuthorizationTest.php` — including that the
people who *should* get in still do, since a refusal that refuses everybody
proves nothing.

Some surfaces take no id at all. The form builder and custom domain settings
resolve the record from the signed-in user and active role, so there is nothing
in the request to point elsewhere.

## Headers and CSP

`App\Http\Middleware\SecurityHeaders`:

- CSP with a **fresh nonce per request** — a nonce reused across requests is no
  better than none
- `X-Frame-Options: DENY`, `nosniff`, Referrer-Policy, Permissions-Policy
- HSTS on TLS

## Webhooks

`App\Services\Webhooks\SignatureVerifier`:

| Scheme | Provider |
|---|---|
| `hmac_hex` | Razorpay, generic |
| `hub_signature` | Meta — `X-Hub-Signature-256` |
| `sendgrid_ecdsa` | SendGrid |
| `svix` | Resend, 300s replay window |
| `basic` | Postmark inbound |

A delivery that cannot be proven authentic is logged as rejected and nothing
downstream runs. An unverified event never occupies the unique id slot, or a
forged call could suppress the genuine webhook that follows it.

Idempotency: `webhook_logs.provider_event_id`,
`inbound_email_events.provider_event_id`, `autodm_events.provider_event_id`.

## Usernames

One global registry, one unique index across creators and editors. Before it,
two profile types had independent indexes and a creator and an editor could both
be `asif` — harmless behind `/u/` and `/editors/`, an impersonation vector the
moment the address is `vidlix.in/asif`.

Normalised before every comparison. A reserved list containing `admin` is no
defence against `Admin`. Separators are folded so `john.doe` and `john_doe`
cannot be two people. Unicode is refused: homoglyphs are impersonation with
extra steps.

## Custom domains

The largest externally-controlled surface. See `docs/CUSTOM-DOMAINS.md`.

- Private, internal, reserved and our own hostnames refused
- Every resolved address must be public — re-checked at each refresh, because a
  name can be repointed later
- Served only when DNS, ownership **and** certificate are all confirmed
- Two paths whitelisted; unknown hosts 404 rather than serving our site

## Files

Authorised **before** a signed URL is issued. A signed URL in the wrong hands
works for as long as it lives, whoever follows it.

Large media never touches MySQL. `MEDIA_DISK` / `FILESYSTEM_DISK` should be `s3`
in production — the S3/R2 disk is configured correctly, but the **default is
`local`** and must be changed.

## Public forms

Honeypot, Turnstile, `throttle:public-form`. A honeypot trip fails with the same
generic message a real error gives — telling a bot which check it tripped is
telling it how to pass.

Field validation enforces types and option lists. Keys not in the published
version are discarded rather than stored.

## Secrets

Never logged or displayed: passwords, OTPs, OAuth tokens, API keys, bank
details, payment secrets, verification documents.

`.env` untracked. Gitleaks scans full history in CI. Semgrep runs `p/php`,
`p/security-audit`, `p/secrets`, `p/owasp-top-ten`.

OAuth tokens encrypted at rest; `token_encrypted` is in `$hidden`.

## Payments

Amounts computed server-side. Provider signature verified. A browser redirect is
never treated as settlement — only a signed webhook is. The ledger is
append-only and every balance is summed from it, never stored, so it cannot
drift from the rows it describes.

## Outstanding

| # | Item | Priority |
|---|---|---|
| 1 | `FILESYSTEM_DISK=s3` in production; verify signed URLs and MIME enforcement | High |
| 2 | Payments reconciliation and refund paths untested | High |
| 3 | Staging DAST not yet run | Medium |
| 4 | Backup and restore not yet rehearsed | Medium |
| 5 | `SESSION_SECURE_COOKIE` and SameSite unverified in production config | Medium |

## Reporting

Security issues to `help@vidlix.in`. Do not open a public issue.
