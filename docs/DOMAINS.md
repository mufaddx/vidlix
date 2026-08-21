# Domains

Vidlix has four faces. They are separate hosts rather than path prefixes because
they are separate products with separate audiences: somebody reading the landing
page and a staff member opening the admin panel should never be one redirect
apart.

| Host | What it serves | Who it is for |
|---|---|---|
| `vidlix.in` | Landing site, public profiles, contact forms, AutoDM product page | Everybody, signed in or not |
| `app.vidlix.in` | Creator and editor workspace | Signed-in members |
| `autodm.vidlix.in` | AutoDM dashboard | Members with Instagram connected |
| `admin.vidlix.in` | Super admin panel | Staff only |

## Configuration

```
APP_URL=https://vidlix.in
APP_APP_URL=https://app.vidlix.in
AUTODM_APP_URL=https://autodm.vidlix.in
ADMIN_APP_URL=https://admin.vidlix.in
```

Read through `config('vidlix.domains.*')`. The defaults are the production hosts
on purpose: a deploy that forgets one should point at the real site rather than
at a placeholder.

**Never use `example.com`** in code, tests, email templates or environment
files. It is checked in review; `vidlix.test` is the local convention.

## One application, four hosts

All four are served by one Laravel application. There is no second codebase and
no separate deployment — the host decides which routes make sense, not which
code is loaded. This keeps the session, the database and the authorization rules
identical across all of them, which is what makes "signed in on app, click
through to AutoDM" work at all.

## Public profile URLs

```
https://vidlix.in/{username}            the profile
https://vidlix.in/{username}/contact    the contact form
```

No role appears in the URL. A creator who also edits should not have to explain
which of two links is theirs, and a link that names a role outlives the role it
names.

Retired addresses that still redirect:

- `/u/{username}` → `/{username}`
- `/editors/{username}` → `/{username}`
- `/editor/{username}` → `/{username}`

The `/{username}` route is registered **last** in `routes/web.php`. That
position is the real defence against a handle shadowing a page the application
owns; the reserved-word list covers the paths that do not exist yet. See
`docs/DATABASE.md` for the registry.

## Custom domains

Members may point their own hostname at their contact form. Such a hostname
serves **two paths only** — `/` and `/contact` — and nothing else. See
`docs/CUSTOM-DOMAINS.md`.

## Email

Sender identities are per role and configured, never derived from a request:

```
MAIL_FROM_CREATOR=creator@vidlix.in
MAIL_FROM_EDITOR=editor@vidlix.in
MAIL_FROM_BRAND=brand@vidlix.in
MAIL_FROM_NOTIFICATIONS=notifications@vidlix.in
MAIL_FROM_NOREPLY=no-reply@vidlix.in
MAIL_FROM_HELP=help@vidlix.in
MAIL_FROM_BILLING=billing@vidlix.in
```

See `docs/EMAIL-THREADING.md`.

## DNS and TLS

Each host needs its own record and certificate. `admin.vidlix.in` should sit
behind whatever additional network restriction the deployment allows — it is a
separate host precisely so that it can.

Tenant hostnames need a wildcard or per-hostname certificate from the custom
domain provider, not from the Vidlix certificate.
