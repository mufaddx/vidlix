# Manager removal

The manager system is gone. This records what it was, how it was removed, and
what survives.

## What it was

A manager could act on somebody else's account — reading their inbox,
negotiating, handling payments — under a subscription, with granular permissions
and revocable assignments.

## Why removal was not a deletion

`WorkspaceContext` — consulted on **every authenticated request** to decide
whose data the signed-in person is reading — was written entirely around
`ManagerAssignment`. Its `canActFor()`, `actAs()`, `switchableAccounts()` and
the re-authorisation loop in `hydrate()` all queried that table.

Deleting the model would have stopped the application booting. 390 references
across 45 files, including `AccountProvisioner`, `MarketplaceEngine`,
`PersonalDataService`, `Ability`, `AdminNavigation`, 4 models, 3 migrations, the
seeder, 8 views and 7 test files.

## How it was done

**Deny access.** Every manager route, navigation entry, admin page and API
endpoint removed. The tests that covered them now assert they are *gone* rather
than that they work.

**Rewrite the context.** `WorkspaceContext` now answers one question — which of
your own approved profiles are you working in — and `effectiveUser()` always
returns the signed-in user. The property worth keeping was kept: the session
holds only a profile name, never a permission, and whether that profile is
usable is re-read from the database on every request.

**Archive the data.** Tables **renamed**, not dropped:

```
manager_profiles         → archived_manager_profiles
manager_assignments      → archived_manager_assignments
manager_invitations      → archived_manager_invitations
manager_activity_logs    → archived_manager_activity_logs
management_plans         → archived_management_plans
management_subscriptions → archived_management_subscriptions
```

Renaming keeps every row, column and foreign key exactly as it was, so the audit
record of who managed whom survives and reversing it is a rename back rather
than a restore from somewhere else.

The `manager` role and the `managers.view` / `managers.assign` abilities were
deleted, along with their memberships, so nobody is left holding a role that no
longer means anything.

## The subscription guard

The migration **refuses to run** if any `management_subscriptions` row is
`active`, `trialing` or `past_due`:

> Refusing to retire the manager system: N management subscription(s) are still
> live. Settle, refund or expire them first, then run this migration again.

A live subscription is a financial obligation, not a row to tidy away. It should
be settled deliberately rather than cancelled by a deploy.

## Rollback

`down()` renames the tables back. The role and permissions are deliberately
**not** recreated: bringing the tables back is data recovery, bringing the
feature back is a decision, and it should be made explicitly rather than by
rollback.

Take a full backup before running `up()` and rehearse the restore on staging.

## What replaced it

Nothing. One human keeps one account and switches between their own approved
profiles. Nobody can act on somebody else's behalf.

The pricing page, which used to sell management plans, now states what is
actually charged: free to join, one commission read from the same rule the
ledger uses.

## Verification

- [x] No manager option in signup or profile menus
- [x] No manager route or API authorizes access
- [x] No user can escalate into the role
- [x] `WorkspaceContext` contains no `ManagerAssignment` reference
- [x] A forged acting-for session grants nothing — there is nothing to forge
- [x] Historical audit records retained
- [x] Regression tests assert denial for every removed surface

`tests/Feature/WorkspacePagesSmokeTest.php` and `AdminPanelTest.php`.
