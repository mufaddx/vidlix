# Custom domains

A member may point a hostname they own at their contact form, so people reach
them at `contact.theirbrand.com` rather than at ours.

This is the one place where somebody outside the platform controls part of the
routing input. Almost everything below is about refusing things.

## Status is not a boolean

```
not_connected → pending_verification → dns_required
  → ownership_pending → ssl_provisioning → active
```

with `failed`, `suspended` and `disconnected` as exits.

Only `active` is ever served. Three separate facts have to be true — DNS points
at us, ownership is proved, a certificate exists — and they are three columns
because "DNS is right but there is no certificate" is not nearly-active, it is a
browser warning. That is exactly the state people want to call done.

## Hostname rules

`App\Services\Domains\Hostname` refuses rather than repairs. A hostname that
needs fixing before it is safe is one somebody chose carefully, and quietly
correcting it is how an internal name ends up pointed at a public tenant.

Refused:

- `localhost`, `metadata.google.internal`, `instance-data`, and similar
- Anything ending `.local`, `.internal`, `.intranet`, `.private`, `.corp`,
  `.home`, `.lan`, `.test`, `.example`, `.invalid`, `.onion`
- Bare IP addresses — the shortest path to pointing us at a private address
- Our own hosts, and anything under them: a tenant claiming `admin.vidlix.in`
  would be a custom domain that resolves to the panel

## Normalisation

Lowercased, trailing dot stripped, port and path removed, IDN converted to
punycode. Punycode matters for more than tidiness — two visually identical
unicode hostnames are different strings, and comparing the displayed form would
let a lookalike past the uniqueness check.

The **unique index on the normalised hostname** is what actually prevents two
tenants holding one name. The check in the service is for the error message; two
requests can pass it in the same instant.

## SSRF

`resolvesPublicly()` requires **every** resolved address to be public. One
public answer alongside a private one is still a private answer.

It runs at every status refresh, not only at connect: a name that resolves
publicly today can be repointed at `127.0.0.1` tomorrow, and that would make us
a proxy into somebody's network.

## Routing

`App\Http\Middleware\ResolveCustomDomain` runs before the maintenance gate and
before routing.

- Host is one of ours → nothing happens, the ordinary router takes over
- Host resolves to an **active** custom domain → rewritten to that owner's
  `/{username}/contact`, and **only** `/` and `/contact` are allowed
- Anything else → **404**

The whitelist is two paths, not a blocklist. The app, the panel, the API and
other people's profiles are all refused on a tenant hostname.

An unknown hostname gets a 404 rather than the main site, so a stale DNS record
belonging to somebody else cannot quietly become a page that looks like ours.

The username is read from the owner's profile at request time, not stored on the
domain row, so a rename cannot leave the hostname pointing at an address that no
longer belongs to them.

## Verification token

Regenerated on every reconnect. Reusing one would let whoever holds the domain
next replay a proof of ownership that was never theirs.

## Provider

`App\Contracts\CustomHostnameProviderInterface`. Cloudflare for SaaS is the
intended implementation; the contract is generic because what the platform needs
to know is whether a hostname is accepted, whether DNS points at it and whether
a certificate exists — not how a particular vendor words those answers.

**No live driver is configured.** `UnconfiguredHostnameProvider` refuses
clearly, and the settings page says so, rather than accepting a domain that
would never be served. Somebody who changes their DNS and then discovers nothing
was listening has done real work for nothing.

To enable: implement the contract, register it in `AppServiceProvider::DRIVERS`,
set `CUSTOM_DOMAIN_PROVIDER`.

## What the member sees

```
Type:   CNAME
Host:   contact
Target: <provider-supplied>
```

Plus the three-step progress list, so it is obvious which of DNS, ownership and
certificate is outstanding.

## Events

Every state change is written to `custom_domain_events`. A domain that stopped
working is almost always a story about what changed and when, and without this
the only record is a status column that has already moved on.

## Testing

`tests/Feature/CustomDomainTest.php` — private hostnames, IP addresses, our own
hosts, punycode, duplicate ownership, the path whitelist, unknown hostnames, and
that a domain mid-provisioning is not served.
