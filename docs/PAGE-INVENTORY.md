# Vidlix — Page Inventory

**Date:** 2026-08-21. 89 Blade views. Each entry records route, role, UI status, backend status, and the states the revised spec (§27) requires: loading, empty, success, error, permission denied, suspended, verification pending/rejected, provider disconnected, rate limited.

Status key: **W** working · **P** partial · **M** missing · **R** remove · **X** wrong under revised architecture

State audit is marked **unverified** where it was not possible to confirm from static reading alone; those need a browser pass in Phase 9.

---

## 1. Public site — `vidlix.in`

| Page | Route | View | UI | Backend | Missing states / changes |
|---|---|---|---|---|---|
| Landing | `/` | `public/home` | P | W | Needs AutoDM section, security/trust section, inquiry-form and inbox explainers per spec §2 |
| Creator directory | `/creators` | `public/creators` | W | W | Empty state unverified |
| Editor directory | `/editors` | `public/directory` | W | W | Empty state unverified |
| Creator public profile | `/u/{username}` | `public/creator` | X | P | **Move to `/{username}`.** Needs OG image, SEO description, copy-link, share, suspended state |
| Editor public profile | `/editors/{username}` | `public/editor` | X | P | Same as above |
| Public contact form | — | — | **M** | **M** | Entire page missing (`/{username}/contact`) |
| Brand directory / profile | `/brands`, `/brands/{slug}` | `public/brand` | P | P | Confirm against revised brand architecture |
| Campaigns | `/campaigns` | `public/campaigns` | W | W | — |
| Blog / post | `/blog`, `/blog/{slug}` | `public/blog`, `public/post` | W | W | — |
| Pricing | `/pricing` | `public/pricing` | W | W | — |
| CMS page (Terms, Privacy) | `/p/{slug}` | `public/page` | W | W | Cookie/consent notice missing |
| Placeholder | — | `public/placeholder` | P | — | Audit for fake content |

---

## 2. Authentication

| Page | Route | View | UI | Backend | Notes |
|---|---|---|---|---|---|
| Sign up | `/register` | `auth/signup` | W | W | Remove Manager role option |
| Log in | `/login` | `auth/login` | W | W | Show-password implemented |
| Forgot password | `/forgot-password` | `auth/forgot` | W | W | — |
| Verify email | `/verify-email` | `auth/verify-email` | W | W | — |
| 2FA challenge / settings | `/two-factor` | `auth/two-factor*` | W | W | — |
| Terms modal | — | `auth/partials/terms-modal` | W | W | Unskippable |

---

## 3. Application — `app.vidlix.in`

| Page | Route | View | UI | Backend | Missing states / changes |
|---|---|---|---|---|---|
| Dashboard | `/dashboard` | `app/dashboard` | P | P | Spec §7 cards: profile completion, verification, IG connection, reach metrics, recommendations, quick actions |
| Inbox list | `/inbox` | `app/inbox-index` | P | P | Role-priority ordering, source badges, delivery state, search |
| Inbox thread | `/inbox/{uuid}` | `app/inbox-show` | P | P | Archive, mute, report, block |
| Chat list / thread | `/chat`, `/chat/{uuid}` | `app/chat-*` | D | W | Consolidate into inbox |
| Public page studio | `/creator/public-page` | `app/public-page` | P | **Fake** | Form builder edits title + description only. Needs full field CRUD, types, reorder, conditional Other, preview, copy link, custom domain |
| Roles | `/roles` | `app/roles` | P | W | Remove Manager option |
| Editor home / apply | `/editor` | `app/editor-home`, `app/partials/editor-apply` | W | W | Manual approval correctly enforced |
| Brand profile | `/brand` | `app/partials/brand-form` | P | P | Confirm against revised architecture |
| Campaigns | `/app/campaigns` | `app/campaigns` | P | P | Negotiation records missing |
| Applications | `/applications` | `app/applications` | P | W | Shortlist state surfacing |
| Projects / project | `/projects` | `app/projects`, `app/project-show` | W | W | Verify file authorization states |
| Earnings | `/earnings` | `app/earnings` | P | P | — |
| Invoices | `/invoices` | `app/invoices` | W | W | — |
| Proposals | `/proposals` | `app/proposals` | P | P | Should become negotiations |
| Portfolio | `/portfolio` | `app/portfolio` | P | P | Reorder, crop, delete missing (spec §7) |
| Automations | `/automations` | `app/automations` | P | P | Marketplace automations — **not** AutoDM |
| Instagram | `/instagram` | `app/instagram` | P | P | Provider-disconnected and token-expiry states |
| Disputes | `/disputes` | `app/disputes` | W | W | — |
| Support | `/support` | `app/tickets` | W | W | — |
| Notifications | `/notifications` | `app/notifications` | W | W | — |
| Settings | `/settings` | `app/settings` | W | W | — |
| Privacy | `/settings/privacy` | `app/privacy` | W | W | Export references Manager data — update |
| **Managers** | `/management` | `app/managers` | **R** | **R** | Delete |
| Custom domain | — | — | **M** | **M** | Phase 6 |
| Form field builder | — | — | **M** | **M** | Phase 4 |

---

## 4. Admin — `admin.vidlix.in`

22 views. All **W** unless noted.

`admin/dashboard`, `admin/members`, `admin/member`, `admin/influencers`, `admin/editors`, `admin/brands`, `admin/brand-campaigns`, `admin/verification`, `admin/categories`, `admin/finance`, `admin/disputes`, `admin/tickets`, `admin/help-desk`, `admin/help-desk-thread`, `admin/employees`, `admin/platform`, `admin/health`, `admin/cms`, `admin/table`, `admin/auth/login`, `admin/auth/already`.

- `admin/managers` — **R**, delete.
- `admin/member` — **P**, remove the Manager tab from User 360.
- Missing (Phase 8/9): AutoDM accounts, Instagram connection health, webhook health, failed automation runs, usage, subscription plans, backup status.

---

## 5. AutoDM — `autodm.vidlix.in`

**Every page is missing.** Landing, dashboard overview, connected account, media grid with refresh, automation list, automation builder (Select → Trigger → Action → Review → Activate), execution history, failed runs, usage, billing, settings, security, support.

---

## 6. Layouts, partials, errors

| View | Status | Change |
|---|---|---|
| `layouts/public`, `layouts/app`, `layouts/auth`, `layouts/admin` | W | `public` and `app-sidebar` reference Manager — strip |
| `partials/account-switcher` | P | Rewrite for role switching only, no acting-for |
| `partials/app-sidebar` | P | Remove Manager nav |
| `partials/public-footer` | P | Remove Manager link |
| `partials/theme-head`, `theme-toggle` | W | Light/dark/system implemented |
| `partials/turnstile`, `reveal`, `nav-toggle` | W | — |
| `managers/invitation*` | **R** | Delete |
| `errors/maintenance`, `errors/feature-off` | W | — |
| Missing error pages | **M** | 403, 404, 429, 500, suspended, verification-pending, verification-rejected, provider-disconnected |
| `welcome.blade.php` | **R** | Laravel default — delete |
| `pdf/invoice` | W | — |

---

## 7. Cross-cutting state gap

The revised spec requires eleven states on every page. The codebase has good coverage of **success** and **error**, partial coverage of **empty** and **permission denied**, and near-zero explicit coverage of **loading**, **rate limited**, **suspended**, **verification pending/rejected**, **provider disconnected** and **offline**.

Recommendation: build one shared Blade state component set in Phase 3 and apply it as each page is touched, rather than a separate state pass at the end.
