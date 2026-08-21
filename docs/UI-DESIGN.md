# UI and design

## Tokens

Defined in `public/css/app.css` as CSS custom properties, in three palettes:
light `:root`, `prefers-color-scheme: dark`, and an explicit `[data-theme]`
override so the toggle wins in both directions.

| Token | Light | Dark |
|---|---|---|
| `--accent` | `#5b5ce2` | `#5b5ce2` |
| `--bg` | `#f7f8fc` | `#0b0d12` |
| `--bg-elev` | `#ffffff` | `#12151c` |
| `--ink` | `#14161c` | `#f9fafb` |
| `--muted` | `#5a6070` | `#98a2b3` |
| `--line` | `#e2e5ef` | `#252a34` |
| `--ok` | `#0a7350` | `#4fd6a0` |
| `--warn` | `#b25e00` | `#f0a95a` |
| `--danger` | `#c22a2a` | `#f28b8b` |

Font: Inter.

Radii: `--radius-sm` 10px, `--radius` 14px, `--radius-lg` 20px.

Never write a raw colour. A hard-coded hex is invisible in the other theme, and
`ThemeAndButtonContrastTest` will fail the build.

## Buttons

Variants set `--btn-*` custom properties rather than real CSS properties. A
variant that sets `background` directly can win over `.btn:hover` on specificity
and leave the hover state half-applied — which is how button text once became
invisible. There is a test for exactly this.

## Page states

`resources/views/partials/state.blade.php` — one shape for every "not ready"
state, so a suspended account reads the same wherever you meet it.

```blade
@include('partials.state', [
    'state' => 'verification_pending',
    'detail' => __('Submitted on :date', ['date' => $date]),
    'action' => route('app.roles'),
    'actionLabel' => __('View application'),
])
```

States: `loading` `empty` `error` `denied` `suspended` `verification_pending`
`verification_rejected` `more_info` `provider_disconnected`
`provider_unconfigured` `rate_limited` `offline`.

Each says what happened, whether the reader can do anything about it, and what
happens next — in that order, because "is this my fault?" is the first question
anybody has. Suspension says outright that nothing was deleted, which is the
thing somebody in that state is actually afraid of.

The layout is identical across states; only the accent changes. A state that
rearranges the page makes the reader re-find everything.

## Error pages

Real 403, 404, 429, 500 and 503 pages in `resources/views/errors/`.

404 deliberately says nothing about whether the thing exists. 500 shows the
request id and no exception text.

## Theme

Applied before first paint by an inline script carrying the CSP nonce, so there
is no flash of the wrong theme. Follows the OS until the visitor chooses for
themselves, then remembers.

## Layout

`layouts/public`, `layouts/app`, `layouts/auth`, `layouts/admin`.

Mobile-first. The creator bottom navigation stays exactly: Creator, Editor,
Inbox, Projects, Profile.

## Avoid

Arbitrary colours, extra fonts, heavy gradients, glassmorphism, fake dashboard
cards, hardcoded counters, buttons that do nothing, desktop-only workflows.

Every number shown is counted from real rows. A landing page that invents its
figures is the same lie as a dashboard that invents a balance.
