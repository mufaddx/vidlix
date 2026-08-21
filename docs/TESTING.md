# Testing

```bash
php artisan test                      # 343 tests, ~30s
php artisan test --filter=AutoDmTest  # one file
vendor/bin/pint --test                # formatting
vendor/bin/phpstan analyse            # static analysis
npx playwright test                   # browser
```

CI runs all of these on every pull request and on `main`. A red check blocks the
merge — the guarantee comes from the gate, not from remembering to run them.

## Suites

| File | What it defends |
|---|---|
| `AuthorizationTest` | IDOR. Every route with implicit model binding, from a stranger's session |
| `PublicProfileUrlTest` | Global username uniqueness, reserved paths, visibility, renames |
| `ContactFormBuilderTest` | Field types, option enforcement, conditional fields, versioning |
| `InboxControlsTest` | Archive, mute, report, block — and that each is per person |
| `CustomDomainTest` | Hostname refusals, SSRF, the path whitelist, state machine |
| `NegotiationTest` | Append-only offers, self-acceptance, milestones, expiry |
| `AutoDmTest` | Idempotency, capability gating, skipped-not-failed, versioning |
| `AuthFlowTest`, `TwoFactorTest` | Signup, OTP, reset, 2FA |
| `PaymentSettlementTest`, `PayoutAndStorageTest` | Signatures, ledger, payouts |
| `EmailIntegrationTest`, `ResendEmailTest`, `UnifiedInboxTest` | Threading |
| `AdminPanelTest` | Abilities, and that removed pages stay removed |

## How these tests are written

**Assert the refusal and the permission.** A test that only proves strangers are
refused would pass if everybody were refused. Each authorization test also
checks that the people who should get in do.

**Name the reason, not just the outcome.** `assertSame('app_review_pending',
$check['reason_code'])` catches a regression that turns a specific explanation
into a shrug.

**Prove the negative in the database.** After a refused action, assert the row
did not change — a 404 with a side effect is worse than a 200.

**Use real services, fake only the provider.** Tests bind a fake Instagram or
hostname provider and run everything else genuinely, so what is under test is
the platform's own logic.

## Critical journeys covered

1. Creator adds a College/Other dropdown → publishes → stranger submits → answer
   reaches the inbox bound to the version that asked for it
2. Same for an editor
3. Offer → counter → accept → project with milestones
4. Comment webhook → keyword match → run recorded → duplicate delivery ignored
5. Stranger attempts every bound route → 404 throughout, nothing mutated

## Not yet covered

- Payments reconciliation and refunds
- Signed-URL issuance against a real S3/R2 disk
- Live provider calls of any kind
- Staging DAST

These are listed in `docs/SECURITY.md` as outstanding rather than quietly
omitted.
