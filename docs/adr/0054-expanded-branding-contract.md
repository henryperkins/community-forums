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
   *Versioning, history, restore and group resets shipped 2026-10-31 (2a);
   private drafts, whole-site preview and explicit publishing followed the
   same day (2b, below).*
3. **Independent light/dark palettes** — one shared token definition across
   `BrandPalette`, `ThemeTokenPolicy`, preview, validation and public CSS, then
   per-mode primary/accent overrides with automatic suggestions and both
   palettes previewed with their interaction states. *Shipped 2026-10-31
   (below).*
4. **Curated visual controls** — complete surface/sidebar presets, approved
   self-hosted typography pairings and bounded shape options, each exposed only
   after its component coverage is verified. *Shipped 2026-10-31 (below).*
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
  Mode-specific overrides shipped in increment 3. Derived state colours (soft
  fills, readable ink, hover/pressed shades) are recomputed from whichever
  value wins.
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
| `brand_color_primary_light`, `brand_color_primary_dark` | None | The shared primary, then package, then preset roles | That mode's actions, links, focus, selected navigation; derived state pairs | Cleared (colours) |
| `brand_color_accent_light`, `brand_color_accent_dark` | None | The shared accent, then package, then preset roles | That mode's highlights, unread counts, selection rules, accent ink | Cleared (colours) |
| `brand_theme_default` | `system` | Invalid values read as System | Signed-out visitors' mode | Cleared |
| `brand_theme_preset` | `classic` | Style preset: `classic`, `retro`, `ledger` or `vellum`; suspended while a package is served | Base palette for both modes, plus the preset's sidebar, type and corners | Cleared (appearance) |
| `brand_sidebar_style` | None (the preset's) | `sunken`, `flush` or `raised`; applies over a package | Board rail ground and row hover | Cleared (appearance) |
| `brand_type_pairing` | None (the preset's) | `imladris`, `book` or `lapidary`; applies over a package | Display (heading) face; text stays EB Garamond | Cleared (appearance) |
| `brand_corners` | None (the preset's) | `crisp`, `imladris` or `soft`; applies over a package | The `--radius-sm/md/lg/xl` scale and compact cards | Cleared (appearance) |
| `brand_density_default` | `comfortable` | `compact`; a member's stored density always wins | Visitors and members who have not chosen | Cleared (appearance) |
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

## Increment 2a delivery: versioned branding, history and restore (2026-10-31)

- **One versioned document.** `App\Support\BrandingDocument` names every setting
  the branding console publishes (the table above, including stored custom CSS).
  `branding_revisions` (migration `0084`) is append-only: each row is the whole
  normalized document after one change, its changed keys, actor and action
  (`baseline`, `publish`, `restore`, `reset`). `settings.brand_revision` points
  at the live row. The first recorded change also records, as `baseline`, the
  configuration it replaced, so an existing installation can return to where
  it started. A save that changes nothing records no revision.
- **Every writer commits through `BrandingRevisionService`:** the branding form,
  the community name in Settings, restore, the full reset and group resets.
  One transaction locks the pointer, applies the change, records the revision
  and writes its audit entry (`branding_update`, `branding_reset`,
  `branding_reset_group`, `branding_restore`, with revision ids; contrast
  acknowledgements stay `branding_contrast_override`).
- **Stale editors are refused, not overwritten.** The form carries the revision
  it was opened at; a form without one counts as revision 0. A mismatch returns
  409 with the whole draft, names each newer revision (who, when, which
  settings) and nothing is written. Publishing over them requires confirming
  that exact newer revision; anything older is still a conflict. A restore
  whose review went stale is rebuilt against the new live branding.
- **Restore scope.** Restoring republishes every document setting — community
  name and text, logos and favicon, colours, visitor mode, preset and stored
  custom CSS — as a new revision, so it can be undone the same way. Package
  activation, theme safe mode, feature flags and member preferences are never
  part of it. The review page compares the revision with the live branding,
  shows logos and colours, names any image whose media no longer exists (that
  setting restores to its fallback), and repeats the contrast review against
  the active theme with the same audited, candidate-bound acknowledgement.
- **Assets are retained.** Brand media is finalized without a parent post, so
  the orphan sweep never reclaims it; removals and resets only disconnect a
  path. History is the reason to keep it.
- **Group resets** — colours, logos and favicon, appearance defaults — each
  return one group to its defaults as a recorded, restorable revision and leave
  every other group untouched. The full reset is recorded the same way.
- **Recovery surface.** The branding page and its history never take the saved
  global overrides themselves: they load only the scoped sample and thumbnail
  rules (`/brand.css?…&scope=sample`), so colours an operator acknowledged as
  hard to read cannot make the recovery controls unreadable. Package problems
  keep their own recovery, theme safe mode, linked from the reset card.
- Private drafts, whole-site preview and explicit publishing follow in 2b.

Evidence: `tests/Integration/Core/AppBrandingRevisionTest.php` and
`docs/evidence/branding-revisions-2026-10-31/README.md`.

## Increment 2b delivery: private drafts, preview and publishing (2026-10-31)

- **One private draft per administrator** (`branding_drafts`, migration
  `0085`): the whole candidate document plus the revision its form started
  from. **Save draft** stores it; **Publish branding** is the only action that
  changes public branding. The branding form edits the draft while one exists
  and says so; a form without a draft edits the live branding as before.
- **Private everywhere.** A draft is never rendered for anyone but its author.
  Its new uploads are finalized attachments with `visibility = 'private'`, so
  `/media/{id}` answers 404 to everyone else and `private, no-store` to the
  author; publishing makes exactly the published images public. A draft is
  never refused for contrast — the review and its audited acknowledgement
  belong to publishing.
- **Whole-site preview** is a per-session toggle (`rb_branding_draft_preview`)
  that the shell honours only for a signed-in administrator who owns a draft;
  for anyone else it is cleared. While on, every page the author opens renders
  the draft — name, text, logos, favicon, colours and preset — under a preview
  bar painted from the constant stage tokens, with links back to publishing,
  to a sign-in preview (`/admin/branding/preview/sign-in`) and out. Pages for
  signed-in members are `private, no-store`, and the draft stylesheet variant
  (`/brand.css?…&draft=1`) is `private, no-store` for every requester and
  resolves the draft only for its previewing author, so no shared cache can
  hold a draft. Visitors stay on the published configuration throughout.
- **Publishing a draft** commits the whole document through the same
  versioned transaction as any save: contrast review against the active theme,
  stale-revision conflict naming the newer work, revision and audit together
  (`from_draft` recorded), draft deleted and preview ended.
- **Discarding** (a confirmation step) deletes the draft and purges only the
  private images it alone used; published images are never touched.
- Visitor mode in a draft affects signed-out visitors only, so an author's own
  appearance preference still governs what they see while previewing.

Evidence: `tests/Integration/Core/AppBrandingDraftTest.php` and
`docs/evidence/branding-drafts-2026-10-31/README.md`.

## Increment 3 delivery: independent light and dark palettes (2026-10-31)

- **One shared definition.** `ThemeTokenPolicy`'s light and dark baselines now
  hold every colour role a package may set, including the Imladris semantic
  surfaces and rules the shell paints from (`--surface-page`,
  `--surface-raised`, `--surface-sunken`, `--border-hair`, `--border-soft`,
  `--border-strong`, `--rule-gold`), which packages may now set. Focus rings,
  field rules and the status families stay application-controlled and are not
  package roles. `BrandPalette`'s built-in Classic base is that baseline plus
  only the application-owned roles and the translucent dark washes the
  stylesheet actually declares, so the package build gate, the branding
  contrast review, the sample and `/brand.css` start from the same values.
  `ThemeBaselineFidelityTest` pins the baseline to the shipped stylesheet and
  `BrandPaletteTest` pins Classic to the baseline. The package contrast gate
  adds body text on the page and raised surfaces and faint text on the raised
  surface. Package tokens apply to both modes unless `dark_tokens` overrides
  them, so a package that sets a light surface must also set its dark one.
- **Per-mode overrides.** Four document keys,
  `brand_color_{primary,accent}_{light,dark}`, join the colours group. Each
  mode resolves through `BrandPalette::modeColours` — its own value, else the
  shared one — and that single resolution feeds the public stylesheet
  (`:root`, `[data-theme="dark"]` and System's dark-device block), the scoped
  sample, the member thumbnails and the contrast review. A blank field
  inherits; a form posted without the fields (opened before they existed)
  keeps the stored values.
- **Each mode is reviewed on its own surfaces.** A warning names the field to
  change — the mode's own field when it has a value, else the shared colour.
  The acknowledgement is bound to the per-mode colours it reviewed, and the
  `branding_contrast_override` audit entry records them as `mode_colours`.
- **Suggestions are offered, never applied.** When the colour a mode would use
  draws a warning there, the form names the nearest tone of it, moved toward
  that mode's readable ink, that passes the mode's review for that role. With
  JavaScript **Use suggestion** fills the mode's field (and the previews
  follow); without it the value is shown to type.
- **Both palettes are previewed with their states.** Light and Dark strips
  under the sample show action, hover, pressed, focus, selected, unread and
  link states, each painted from its mode's resolved roles — saved values
  without JavaScript, the candidate values with it. Browser evidence compares
  each strip with fresh public pages in that mode for Classic, Retro and an
  active package.
- **Compatibility.** Shared colours apply to both modes exactly as before, and
  with no per-mode value the public stylesheet's colour declarations are
  unchanged, so the resolver version stays 3. The colours reset, history and
  restore cover the per-mode keys.

Evidence: `tests/Integration/Core/AppBrandingPaletteTest.php`,
`tests/Unit/Support/BrandPaletteTest.php`, the package-theme suite and
`docs/evidence/branding-palettes-2026-10-31/README.md`.

## Increment 4 delivery: curated style presets (2026-10-31)

- **Presets are complete combinations.** `App\Support\BrandStyle::PRESETS`
  defines each preset's surfaces (a full light and dark palette in
  `BrandPalette::presets()`), sidebar treatment, heading/body pairing and corner
  scale. Classic and Retro keep exactly their previous palettes with the
  built-in sidebar, type and corners, so existing installations are unchanged.
  Ledger (cool mist and slate, a sidebar flush with the page, EB Garamond
  headings, crisp corners) and Vellum (bright vellum and warm charcoal, a raised
  sidebar, Marcellus headings, soft corners) are new.
- **Fixed vocabularies, not values.** The shell stamps the effective parts on
  `<html>` as `data-rail`, `data-type-pairing` and `data-corners`, and
  `app.css` maps each value to tokens; the branding sample carries the same
  attributes. No operator text reaches CSS. A non-Classic preset's palette is
  published whole by `/brand.css`, as Retro's always was.
- **Bounded by the design system.** Pairings use only the four self-hosted
  families and change the heading face (text stays EB Garamond, labels
  Marcellus, data JetBrains Mono); Marcellus ships one weight, so its pairing
  disables weight synthesis. Corner scales keep content containers at or under
  12px. Status meanings, focus rings, responsive structure and member
  accessibility preferences are untouched.
- **Overrides, then package, then preset.** Sidebar, Headings and text, and
  Corners can each be overridden. While a package is served it owns the base:
  only overridden parts are stamped over it (even one restating a built-in
  value), and the preset resumes with safe mode or without the package.
- **Coverage verified before exposure.** `BrandStyleContractTest` pins a token
  rule for every vocabulary value, the rail hooks and their layer fallbacks,
  the self-hosted families, the Twelve-Max Rule, and that components read type
  and radius tokens (no literal families; only hairline and pill literal
  radii). `BrandPaletteTest` checks every text role on every ground and sidebar
  treatment of every preset in both modes. Browser evidence walks navigation,
  reading, forms and messaging under each new preset and finds no surface still
  painted with Classic's grounds and no text left in the replaced face.
- **Density default.** `brand_density_default` applies to visitors and to
  members without a stored density; a member's own choice always wins. The
  member appearance page shows the effective choice.
- **Recovery and reset.** The branding pages and history keep the built-in
  style for their own controls, as they keep the built-in colours. The
  appearance reset clears the preset, its overrides and the density default.
  The full reset now clears every group — including the per-mode colours from
  increment 3, which it previously left behind.

Evidence: `tests/Integration/Core/AppBrandingStyleTest.php`,
`tests/Unit/Core/BrandStyleContractTest.php`, `tests/Unit/Support/BrandStyleTest.php`,
`tests/Unit/Support/BrandPaletteTest.php`, the package-theme suite and
`docs/evidence/branding-styles-2026-10-31/README.md`.

## Increment 5: secondary surfaces wait for demonstrated use (2026-10-31)

Nothing is built in this increment, by decision. Each candidate is an
independent addition that ships only when an operator need is identified, and
then through the same document, draft, preview, history and recovery model:

| Candidate | Becomes worthwhile when |
|---|---|
| Default social-sharing image and touch/app icons | Community discovery and recognition on other sites and home screens matter |
| Branded email header and footer | Transactional and digest email is actively used; transactional content and status meaning stay unchanged |
| Board icons or restrained tints | A community has many boards to tell apart |
| Named brand profiles with validated import/export | Operators need reusable configurations; imports enter the draft workflow and preview before publication |

Custom CSS stays deferred: `custom_css` remains console-blocked until theme safe
mode suppresses it, and the curated controls above are now the measure of
whether unrestricted CSS is needed at all.
