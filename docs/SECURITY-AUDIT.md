# Vidlix — Security Audit

**Date:** 2026-08-21. Static review. **No dynamic testing, no DAST, no live provider calls, and the test suite was not executed.** Findings are code-reading conclusions.

---

## 1. Overall posture

**Good, and better than the rest of the codebase's completeness would suggest.** The security work that is usually skipped — per-provider webhook signature verification, CSP nonces, replay windows, webhook idempotency at the schema level, a separate admin front door — is present and correct. There is no committed secret, no debug-on default, no scraping, and no password-based social access.

Three findings below are real. Two become severe only when the new architecture ships, which is precisely why they are worth fixing before Phase 3 rather than after.

---

## 2. Findings

### S-1 — Username uniqueness is per-profile-type, not global — **High**

`creator_profiles.username` and `editor_profiles.username` carry independent unique indexes. A creator and an editor can hold the same username today.

**Impact.** Harmless under `/u/{name}` and `/editors/{name}`, which are disambiguated by prefix. The moment `/{username}` becomes canonical it is an **impersonation vector**: whichever record the resolver happens to find first owns the identity, and the other party's audience lands on a stranger's profile and contact form. Inquiries intended for one person reach another.

**Fix.** Global `usernames` registry with a single unique index, plus a collision-detecting backfill and a documented rename policy. See `docs/DATABASE-AUDIT.md` §2. **Blocking for Phase 3.**

---

### S-2 — Default mail sender is `hello@example.com` — **Medium**

`config/mail.php:114` — `'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com')`. `.env.example:56` sets the same.

**Impact.** Directly violates the revised spec ("do not use example.com in production code, documentation, tests, email templates or environment files"). A production deploy that forgets `MAIL_FROM_ADDRESS` sends from a domain Vidlix does not control — mail is rejected or silently dropped, and inquiry replies are lost.

**Fix.** Default to `no-reply@vidlix.in`. Add the seven role identities (`MAIL_FROM_CREATOR`, `MAIL_FROM_EDITOR`, `MAIL_FROM_BRAND`, `MAIL_FROM_NOTIFICATIONS`, `MAIL_FROM_NOREPLY`, `MAIL_FROM_HELP`, `MAIL_FROM_BILLING`) to `config/vidlix.php` and `.env.example`, and drive `OutboundIdentity` from them. **Phase 5.**

---

### S-3 — Form schema validation ignores field types — **Medium**

`PublicInquiryService::validateAgainstSchema()` treats every field as a trimmed string, enforces only `required`, and special-cases only `email`.

**Impact.** Today the builder cannot create non-text fields, so nothing is exploitable. As soon as Phase 4 adds dropdowns, checkboxes, file attachments and conditional fields, **every one of them is unvalidated server-side** — a submitter can send arbitrary values for a dropdown, bypass conditional requirements, or supply values for fields that are not in the published version.

**Fix.** Validate against the typed schema contract: enforce the option list for choice fields, enforce conditional requirements, reject keys not present in the published version, and validate attachments by MIME, extension and size. Build this **with** the field builder, not after it. **Blocking for Phase 4.**

---

## 3. Controls verified present

| Control | Status | Evidence |
|---|---|---|
| Password hashing | W | Laravel default |
| OTP flow | W | Hashed, expiring, attempt-limited, throttled |
| Rate limiting | W | Named limiters: `login`, `otp`, `register`, `api`, `public-form`, `webhooks`, `scheduler` |
| CSRF | W | Global, with a deliberate `webhooks/*` exemption (correct — those are signature-verified) |
| Security headers | W | `SecurityHeaders` middleware; CSP with per-request nonce, HSTS on TLS, DENY framing |
| Admin separation | W | Separate login route, guest-redirect steering, `EnsureAdmin` |
| Admin 2FA | W | TOTP with recovery codes |
| Webhook signatures | W | HMAC-hex, Meta hub, SendGrid ECDSA, Svix, Basic. Unverifiable deliveries logged and dropped |
| Webhook replay protection | W | Svix 300s tolerance window |
| Email webhook idempotency | W | `inbound_email_events.provider_event_id` unique |
| OAuth state validation | W | `MetaInstagramProvider::stateFor()` / `creatorProfileIdFromState()` |
| Server-side token exchange | W | `completeAuthorization()` |
| Public form anti-spam | W | Honeypot + Turnstile + `throttle:public-form` |
| Server-controlled payment amounts | W | Razorpay orders created server-side |
| Secret scanning | W | Gitleaks over full history in CI |
| SAST | W | Semgrep: p/php, p/security-audit, p/secrets, p/owasp-top-ten |
| Dependency scanning | W | Dependabot |
| Audit logging | W | `AuditLogger`, `audit_logs` |
| Secrets not committed | W | `.env` and SQLite files untracked |

---

## 4. Controls to verify (cannot confirm statically)

- **IDOR.** Routes use implicit model binding (`/projects/{project}`, `/inbox/{uuid}`, `/project-files/{file}`). Ownership checks appear to live in controllers rather than policies — there is **no `app/Policies` directory**. Needs a systematic pass: every bound model must be scoped to the actor. This is the single largest untested risk area.
- **File authorization.** `ProjectFileController@download` must check authorization *before* issuing a signed URL.
- **Mass assignment.** Models use `$fillable`; spot-check that no status, role, or amount column is fillable.
- **Session security.** Confirm `SESSION_SECURE_COOKIE`, `SameSite`, and session fixation on login.
- **Payment idempotency.** Verified signature is confirmed; duplicate-event suppression is not.

---

## 5. New attack surface introduced by the revised architecture

These do not exist yet, so they are design requirements rather than findings.

### Custom domains (Phase 6) — the highest-risk new surface

| Risk | Required control |
|---|---|
| Host-header injection | Resolve tenant **only** from a verified, active `custom_domains` row. Never from a request header alone. Set `trustedhosts`. |
| SSRF via DNS validation | Reject `localhost`, RFC1918, link-local, IPv6 ULA, and any resolved private IP before validation |
| Domain takeover | Re-verify periodically; deactivate on DNS change |
| Duplicate ownership | Unique index on normalised hostname |
| Cross-tenant leakage | Custom hostname routes to the public form **only** — never the app, admin or API |
| Premature activation | `Active` requires ownership verified **and** SSL provisioned. No exceptions |
| Open redirect | No user-supplied redirect targets on custom hostnames |

### `/{username}` catch-all (Phase 3)

Registered last, after every fixed route. Reserved-path guard checked before registry lookup. Normalise before comparison so unicode homoglyphs and case variants cannot bypass the reserved list. Rate-limit unknown-username lookups to prevent enumeration; return an indistinguishable 404 for "does not exist" and "disabled".

### AutoDM (Phase 8)

Meta webhook signature is already implemented — reuse it. Add: comment-event idempotency keyed on the provider event ID, media-ownership revalidation at activation time, provider-capability revalidation before every send, and a hard rule that an unsupported action is **never** recorded as sent.

### Email identity (Phase 5)

Users must not be able to set an arbitrary `From`. Sender identity is derived server-side from the conversation's owner scope, never from client input. Custom sender domains only after verification plus SPF, DKIM and DMARC.

---

## 6. Priority order

1. **S-1** before Phase 3 ships (blocking)
2. **S-3** alongside Phase 4 (blocking)
3. **IDOR audit** — start now, it is the largest unknown
4. **S-2** during Phase 5
5. Custom domain controls designed in before Phase 6 code
6. Staging DAST in Phase 9
