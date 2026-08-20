# Vidlix — Manager Removal Plan

**Date:** 2026-08-21. Dependency analysis completed before any deletion, per spec §4.

---

## 1. Scope of the problem

**390 references across 45 files.** The Manager system is not a bolt-on feature that can be deleted; it is woven into the core authorization context.

### The critical coupling

`app/Services/Workspace/WorkspaceContext.php` — the class consulted on **every authenticated request** to decide whose data the signed-in person is acting on — is written entirely around `ManagerAssignment`:

- `hydrate()` re-authorises the acting-for session against `manager_assignments` on every request
- `canActFor()` queries `manager_assignments` directly
- `actAs()` sets `active_role` to the literal string `'manager'`
- `switchableAccounts()` enumerates `ManagerAssignment` rows
- `effectiveUser()` returns the managed user

Delete the model and the application stops booting. **`WorkspaceContext` must be rewritten before any Manager table is dropped.**

The design itself is sound and worth preserving in the rewrite: the session stores only IDs, never permissions, and authorization is re-read from the database on every request. Carry that property forward.

---

## 2. Full dependency map

### Models (4)
`ManagerProfile`, `ManagerAssignment`, `ManagerInvitation`, `ManagerActivityLog`

### Controllers (7)
| File | Coupling |
|---|---|
| `Managers/ManagerInvitationController` | Entirely Manager — delete |
| `Admin/AdminManagerController` | Entirely Manager — delete |
| `App/WorkspaceController` | 6 Manager routes — excise |
| `Api/V1/WorkspaceApiController` | `managers` endpoint — excise |
| `Admin/AdminMemberController` | Manager tab in User 360 — excise |
| `App/RoleApplicationController` | Manager role option — excise |
| `App/InstagramController` | Acting-for check — rework |

### Services (5)
| File | Coupling | Action |
|---|---|---|
| `Workspace/WorkspaceContext` | **Core authz** | **Rewrite** |
| `Managers/ManagerDirectory` | Entirely Manager | Delete |
| `Identity/AccountProvisioner` | Provisions manager accounts | Excise |
| `Marketplace/MarketplaceEngine` | Manager-scoped queries | Excise |
| `Privacy/PersonalDataService` | Exports Manager data (GDPR) | Excise, keep archive export path |

### Support (3)
`Support/Ability` (manager abilities), `Support/AdminNavigation` (nav entry), `Http/Requests/Auth/RegisterRequest` (Manager signup option)

### Migrations (4)
`2026_08_18_100100`, `2026_08_18_100300`, `2026_08_18_100400` (create Manager tables), `2026_08_19_140000_generalise_manager_system` (extends them). **Do not edit historical migrations.** Add new archive/drop migrations.

### Views (8)
`admin/managers`, `admin/member` (tab), `app/managers`, `app/roles` (option), `managers/invitation`, `managers/invitation-invalid`, `layouts/public`, `partials/app-sidebar`, `partials/public-footer`, `public/home`, `partials/account-switcher`

### Routes (10)
Listed in `docs/ROUTE-INVENTORY.md` §3.

### Tests (7)
`ManagerSystemTest` (delete, replace with denial tests), `AdminPanelTest`, `AppSignupContractTest`, `PlatformSwitchesTest`, `RolesAndCategoriesTest`, `WorkspaceApiTest`, `WorkspacePagesSmokeTest` (excise Manager cases)

### Seeder
`DatabaseSeeder` seeds Manager roles, plans and assignments — excise.

---

## 3. Does any surviving feature depend on Manager records?

Checked explicitly, as the spec requires:

| Feature | Depends on Manager? | Resolution |
|---|---|---|
| Creator workspace | No — only via `WorkspaceContext.effectiveUser()` | Returns the user directly after rewrite |
| Editor workspace | No | Same |
| Brand campaigns | No | — |
| Payments / ledger / withdrawals | **Check required** — `management_subscriptions` is a billing table | Query for live subscriptions before dropping. If any exist, refund or expire them and notify, do not silently delete |
| Instagram | Acting-for guard only | Simplifies to an ownership check |
| Inbox | `messages.acting_for_creator_id` column | Column stays (historical provenance); stops being written |
| Admin panel | Manager pages only | Delete those pages |
| GDPR export | Exports Manager data | Excise from live export; keep for archived records |

**Conclusion:** no surviving user-facing feature is functionally blocked by removing Manager. The only genuine risk is live `management_subscriptions` — a financial obligation that must be settled, not dropped.

---

## 4. Phased execution

### Phase 2a — Deny access (hours, ships immediately)

Closes the surface without touching data or the authz core.

1. Delete the 10 Manager routes from `routes/web.php` and `routes/api.php`
2. Remove the Manager option from `RegisterRequest` and `app/roles`
3. Remove Manager navigation from the sidebar, footer, public layout and admin navigation
4. Remove the Manager tab from admin User 360
5. Revoke Manager abilities in `Support/Ability`
6. **Add regression tests**: signup rejects `manager`; every deleted route returns 404; the API endpoint returns 404; no user can escalate into the role

Models, tables and `WorkspaceContext` are untouched. Reversible.

### Phase 2b — Rewrite `WorkspaceContext` (the real work)

New contract — role and profile switching only, no acting-for:

```
hydrate(User)              — resolve active_role from approved profiles only
isRole(string)             — unchanged
switchRole(User, string)   — unchanged, minus actAsSelf()
switchableProfiles(User)   — the person's own approved profiles (creator, editor)
effectiveUser(User)        — returns $user, always
```

Removed: `actAs`, `actAsSelf`, `canActFor`, `actingForUserId`, `actingScope`, `isActingForSomeoneElse`, `switchableAccounts`, the `ACTING_USER` / `ACTING_SCOPE` session keys.

**Preserve** the property that made the original correct: the session holds IDs only, and authorization is re-read from the database on every request.

Then update the five dependent services, `partials/account-switcher`, and `POST /workspace/manage` (delete).

Tests: a session carrying a forged `acting_for_user_id` grants nothing; role switching to an unapproved profile fails; a suspended profile stops being switchable immediately.

### Phase 2c — Archive and drop (last)

Only once nothing reads the tables.

1. Create `archived_manager_assignments` and `archived_manager_activity_logs`; copy rows with a `archived_at` stamp. **Retain `manager_activity_logs` permanently** — it is an audit record.
2. Query `management_subscriptions` for live rows. If any exist, **stop and escalate** — settle before proceeding.
3. Drop foreign keys, then tables: `manager_invitations`, `management_subscriptions`, `management_plans`, `manager_assignments`, `manager_profiles`.
4. Delete the 4 models, 2 controllers, `ManagerDirectory`, 2 views.
5. Remove the Manager role and permission rows.
6. Update `docs/ARCHITECTURE.md`, `BRAIN.md`, `README.md`.

**Rollback:** archive tables retain the data; the drop migration's `down()` recreates schema and restores from archive. Take a full database backup before running 2c and verify the restore on staging first.

---

## 5. Acceptance criteria (spec §4)

- [ ] No Manager option in signup
- [ ] No Manager option in profile menus
- [ ] No Manager dashboard route authorizes access
- [ ] No Manager API authorizes access
- [ ] No user can escalate into Manager
- [ ] No fake Manager data in frontend code
- [ ] `WorkspaceContext` contains no `ManagerAssignment` reference
- [ ] A forged acting-for session grants nothing
- [ ] Historical audit records retained
- [ ] Live subscriptions settled, not dropped
- [ ] Regression tests assert denial for every removed surface
- [ ] Documentation updated
