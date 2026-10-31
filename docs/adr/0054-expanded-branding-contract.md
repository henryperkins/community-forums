# ADR 0054: Expanded branding contract

Date: 2026-10-31. Status: accepted by owner instruction; delivered in increments
(this record is amended as each one ships).

## Context

`/admin/branding` (P3-07, ADRs 0009 and 0053) already sets the community name,
primary/accent colours, base/light/dark logos, a favicon, the visitor mode and
the Classic/Retro preset. The owner approved expanding it so a community can
establish its identity without editing templates, then change its appearance
safely. Sequencing is the main decision: identity first, **recoverable
publishing before more customization**, then independent light/dark palettes,
then curated visual controls. Secondary surfaces follow demonstrated demand.
These are delivery increments, not P0–P3 priority tiers (DECISIONS §2).

## Decision

### Delivery order

1. **Complete community identity** — logos on every sign-in screen, System-aware
   logo variants, editable community text, appearance thumbnails that show the
   resolved theme. *Shipped 2026-10-31 (below).*
2. **Safe branding changes** — first a versioned branding document with revision
   history and restore (retaining every asset a revision needs), then private
   drafts, whole-site preview and explicit publishing with conflict detection.
   Package activation stays a separate action with its own rollback.
3. **Independent light/dark palettes** — one shared token definition across
   `BrandPalette`, `ThemeTokenPolicy`, preview, validation and public CSS, then
   per-mode primary/accent overrides with automatic suggestions and both
   palettes previewed with their interaction states.
4. **Curated visual controls** — complete surface/sidebar presets, approved
   self-hosted typography pairings and bounded shape options, each exposed only
   after its component coverage is verified.
5. **Secondary surfaces, by demonstrated use** — social imagery, touch/app
   icons, branded email wrappers, board icons/tints and reusable profiles with
   validated import into the draft workflow. Each is an independent decision.

Custom CSS stays deferred: `custom_css` remains console-blocked until theme safe
mode suppresses it (`FeatureFlags::CONSOLE_ENABLE_BLOCKED`), and the curated
controls must first show whether unrestricted CSS is needed. Responsive
structure, status meanings, focus behaviour and member accessibility preferences
remain application-controlled in every increment.

### Ownership and precedence

- **Administrators own branding** through `/admin/branding` (admin role, an
  active account under WriteGate, CSRF). Every save is one audited transaction.
- **An active package theme owns the base palette.** The stored preset applies
  only while no package is served (none active, packages off, or theme safe
  mode). Package activation, preview, rollback and safe mode never change
  branding settings, and branding saves never change package state.
- **Members own their appearance**: mode (System/Light/Dark), density, text size
  and reduced motion. The visitor mode applies to signed-out visitors only.
  Branding never overrides a member preference.
- **Colour precedence**, per mode and per role (primary, accent):
  `mode-specific override → shared override → active package → built-in preset`.
  Mode-specific overrides arrive in increment 3; until then the chain starts at
  the shared override. Derived state colours (soft fills, readable ink,
  hover/pressed shades) are recomputed from whichever value wins.
- **An empty stored value always means "use the default"**, so clearing a field
  restores its documented fallback. No setting needs a migration; absent rows
  read as empty.

### Settings

| Setting | Default when empty | Fallback and resolution | Surfaces | Reset |
|---|---|---|---|---|
| `site_name` | `app.name` | Required, 1–80 characters | Titles, `og:site_name`, logo accessible name, house-mark wordmark | Kept |
| `brand_tagline` | None | Plain text ≤80; nothing renders when empty | Under the identity on every auth screen; above the directory heading | Kept (community text) |
| `brand_description` | "{name} — a community forum." | A page's own description wins; plain text ≤300 | `meta description`, `og:description` | Kept |
| `brand_directory_heading` | "Every board in the valley" | Plain text ≤80 | Board directory `h1` | Kept |
| `brand_directory_intro` | "Browse the boards the council keeps and pick one to see its topics." | Plain text ≤600; each line is a paragraph; the application's inbox pointer always follows | Board directory introduction | Kept |
| `brand_logo_path` | None | Built-in house mark and name | Member topbar, console bar, auth stage, branding sample | Cleared; media retained |
| `brand_logo_light_path` | None | Base logo, then house mark | Light mode; System on a light device | Cleared; media retained |
| `brand_logo_dark_path` | None | Base logo, then house mark | Dark mode; System on a dark device | Cleared; media retained |
| `brand_favicon_path` | None | Non-fetching `data:,` icon | Browser tab | Cleared; media retained |
| `brand_color_primary` | None | Package, then preset roles | Actions, links, focus, selected navigation; derived state pairs | Cleared |
| `brand_color_accent` | None | Package, then preset roles | Highlights, unread counts, selection rules, accent ink | Cleared |
| `brand_theme_default` | `system` | Invalid values read as System | Signed-out visitors' mode | Cleared |
| `brand_theme_preset` | `classic` | Suspended while a package is served | Base palette | Cleared |
| `brand_custom_css_enabled`, `brand_custom_css` | Off | Console-blocked flag | None while blocked | Cleared |
| `brand_version` | — | Rotated on every save and reset | `/brand.css` cache identity | Rotated |

The shell treats a damaged row (wrong type, over its limit, control or
bidirectional-override characters, or an off-site logo path) as empty, so it
falls back instead of rendering unexpected content. Turning `branding` off
returns every surface to the built-in identity without changing stored values.

### Identity rules (increment 1)

- **One logo resolution** (`App\Support\BrandLogo`). Light resolves to the light
  variant, then the base; dark resolves to the dark variant, then the base. An
  explicit Light/Dark mode renders that logo. System renders both choices
  server-side and the device preference picks, with no script: a `<picture>`
  whose dark `<source>` uses `prefers-color-scheme` when both modes have images,
  or a split pair (image in one mode, house mark in the other) shown by a
  `prefers-color-scheme` rule when only one mode has an image. First paint is
  already correct and a device change follows immediately. The topbar, console
  bar, auth stage and branding sample all render `partials/brand_logo`.
- **Sign-in screens** (log in, registration and invitation landing, forgot and
  reset password, email verification, MFA) show the same logo resolution on a
  raised plate. The plate is the page's raised surface and flips with the mode
  the logo was chosen for, while the stage keeps its constant twilight ground
  (The Constant-Ground Rule). Without a logo the house mark and name remain.
- **Community text** (`App\Support\BrandIdentity`) is normalized plain text:
  line endings unified, horizontal whitespace collapsed, single-line fields fold
  line breaks, the introduction keeps one paragraph per line. Control and
  bidirectional-override characters and over-limit values are refused with the
  whole draft returned at 422; nothing is saved. Output is always escaped.
- **Appearance choices** are labelled Light, Dark and System. Each thumbnail
  is scoped to its mode and painted from that mode's resolved palette (package
  or preset, then operator colours) delivered by `/brand.css`, with built-in
  Classic defaults when branding is off or the stylesheet is unavailable. The
  context names the serving base (package name, Retro or Classic).

## Consequences

- Existing installations look the same until an operator changes a setting:
  every new setting is empty by default and empty reproduces today's output.
  The one intended visible change for configured logos is System mode, which now
  follows the device instead of always showing the base logo.
- Branding reset keeps the community name and community text with the package
  state; it clears colours, logos, favicon, appearance defaults and custom CSS.
  Group resets and restorable history arrive in increment 2.
- The resolver version in the `/brand.css` URL moved to 3 because the
  stylesheet now carries thumbnail colours.

## Increment 1 delivery (2026-10-31)

Runtime instructions are in `docs/runbooks/branding.md`. Regression tests are
`tests/Integration/Core/AppBrandIdentityTest.php` plus the branding and package
suites; rendered evidence is linked from
`docs/evidence/branding-identity-2026-10-31/README.md`.
