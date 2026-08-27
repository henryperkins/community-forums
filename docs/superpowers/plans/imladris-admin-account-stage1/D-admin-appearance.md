# D — admin-appearance: design vs production

**Design source:** `C:/Users/htper/community-forums/docs/design-system/imladris/templates/admin-appearance/AdminAppearance.dc.html`
(385 lines; markup ends at line 246, `<script type="text/x-dc">` runs 247–383)

**Production home (all read in full):**

| File | Route(s) | Flag |
|---|---|---|
| `templates/admin/branding.php` (127 ln) | `GET/POST /admin/branding` (`src/Core/App.php:2152-2153`) | `branding` — **default ON** (`src/Core/FeatureFlags.php:47`) |
| `templates/admin/themes.php` (111 ln) | `GET /admin/themes` (`App.php:2235`), `POST .../preview/clear` `:2238`, `.../rollback` `:2239`, `.../{id}/preview` `:2240`, `.../{id}/activate` `:2241` | `package_themes` — **default ON** (`FeatureFlags.php:84`) |
| `templates/admin/theme_safe_mode.php` (52 ln) | `GET/POST /admin/themes/safe-mode` (`App.php:2236-2237`) | none (deliberately ungated — it is the recovery surface) |
| `templates/admin/custom_emoji.php` (96 ln) | `GET/POST /admin/custom-emoji`, `.../{shortcode}/enable|disable` (`App.php:2324-2327`) | `custom_emoji` — **default ON** (`FeatureFlags.php:66`) |
| `src/Controller/BrandingController.php` (338 ln) | also `GET /brand.css` (`App.php:2148`) | `custom_css` sub-gate — **default OFF** (`FeatureFlags.php:48`, ADR 0009) |
| `src/Controller/AdminThemeController.php` (208 ln) | — | — |

> Note: the brief names `src/Controllers/…`; the real path is **`src/Controller/…`** (singular). Both controllers exist and were read in full.

---

## 1. Section-order comparison

The design is **one screen with two client-side tabs**. Production is **two routes** (`/admin/branding`, `/admin/themes`) plus a third recovery route (`/admin/themes/safe-mode`) and a fourth Appearance-group screen the design does not model (`/admin/custom-emoji`).

### Design order (verbatim headings / eyebrows)

| # | Element | Verbatim string | Line |
|---|---|---|---|
| D1 | Sticky topbar | *(elven-star SVG)* + `Imladris` + `Back to the council` | 22–28 |
| D2 | Page head eyebrow | `Operator desk · Appearance` | 34 |
| D3 | Page head h1 | `Branding & themes` | 35 |
| D4 | Head pill | `Admin mode` | 37 |
| D5 | Local tab nav | `aria-label="Appearance sections"` → `Branding` \| `Themes` | 40–45 |
| D6 | Branding intro | `The chrome the council wears. Colours are stored as hex and resolved into the theme; the preview beside the form updates as you type.` | 50 |
| D7 | Branding form — field grid | `Site name` / `Primary colour (hex)` / `Accent colour (hex)` / `Default theme for signed-out visitors` / `Theme preset` | 54–82 |
| D8 | h3 | `Marks` → `Logo` `(current set)`, `Favicon` `(current set)`, `Light theme logo`, `Dark theme logo` — each with a `Replace`/`Upload` button | 84–90 |
| D9 | h3 | `Custom CSS` → `Enable custom CSS` checkbox; then (conditional) textarea + `I understand this CSS applies site-wide and can affect usability.` | 92–99 |
| D10 | Save row | `Save branding` + status + alert | 101–105 |
| D11 | Aside eyebrow | `Live preview` → shell (`{{ siteName }}` / `{{ themeLabel }}` / `Sample link` / `Primary button` / `Accent marker`) + footnote `Light and dark logo variants are used when the resolved theme explicitly matches that variant; system theme falls back to the base logo.` | 108–123 |
| D12 | Aside h2 (rust left-rule card) | `Reset to defaults` → copy, `Type RESET to confirm`, `Reset to defaults` button | 125–134 |
| D13 | Themes intro | `Package themes are installed from the registry and built before they can serve. Safe mode drops the council back to the built-in chrome without uninstalling anything.` | 143 |
| D14 | h2 | `Safe mode` + toggle button `{{ safeModeAction }}` | 145–154 |
| D15 | h2 | `Active theme` (+ `Roll back to last-known-good`) | 156–168 |
| D16 | h2 | `Installed theme packages` (table: `Package` `Version` `State` `Latest build` `Actions`) | 170–211 |
| D17 | h2 (warning left-rule, armed) | `Activate {{ activateName }}?` + `Current password` + `Activate` / `Cancel` | 213–227 |
| D18 | h2 | `Preview` | 229–240 |

### Production order

**`templates/admin/branding.php`**

| # | Element | Verbatim | Line |
|---|---|---|---|
| P1 | `.admin-head` eyebrow | `Operator desk` | 11 |
| P2 | h1 | `Branding` | 12 |
| P3 | pill | `Admin mode` | 14 |
| P4 | `admin/_nav` partial (8-group rail) | — | 17 |
| P5 | `.pane-intro` | `Tune the public name, colour accents, assets, and preview before the council sees the updated hall.` | 20 |
| P6 | `section.card.brand-cols` > form | `Site name`, `Primary colour (hex, e.g. #2f6fed)`, `Accent colour (hex)`, `Default theme for signed-out visitors`, `Theme preset` | 25–58 |
| P7 | asset file inputs | `Logo`, `Light theme logo`, `Dark theme logo`, `Favicon` (each `+ (current set)` when stored) | 60–75 |
| P8 | custom-CSS block, **flag-gated** | `Enable custom CSS` / `Custom CSS` / ack — else `Custom CSS is saved behind the custom_css feature flag and is not available on this install.` | 77–93 |
| P9 | submit | `Save branding` | 95 |
| P10 | `section.brand-preview` | eyebrow `Live preview`, shell, footnote (identical to D11) | 98–112 |
| P11 | `form.stacked.card` (reset) | copy, `Type <code>RESET</code> to confirm`, `Reset to defaults` | 115–125 |

**`templates/admin/themes.php`**

| # | Element | Verbatim | Line |
|---|---|---|---|
| T1 | `.admin-head` | h1 `Themes` + pill `Admin mode` — **no eyebrow** | 7–10 |
| T2 | `admin/_nav` | — | 11 |
| T3 | page-level error strip | `foreach ($errors as $err) <p class="field-error">` — **no `.pane-intro`** | 14–16 |
| T4 | `section.card` | `Safe mode` + `Open recovery page` link | 18–26 |
| T5 | `section.card` | `Active theme` (5-row `table.audit`) + LKG line + password rollback form | 28–52 |
| T6 | `section.card` | `Installed theme packages` (`.table-scroll` + table) | 54–96 |
| T7 | `section.card` | `Preview` | 98–109 |

**`templates/admin/theme_safe_mode.php`** — `variant=plain` (line 5), `.container`, `admin-head` h1 `Theme safe mode` + pill `Recovery`, the full admin rail (line 13), error strip, then `Status` / `Enter safe mode` / `Exit safe mode` cards.

**`templates/admin/custom_emoji.php`** — eyebrow `Appearance`, h1 `Custom emoji`, `.pane-intro`, `Add or replace emoji` card, `Catalogue` card. **Not modelled by the design at all.**

### Order verdict

* D2/D3/D4 → P1/P2/P3 and T1: **order matches**; content differs (see #2, #3, #41).
* D6→P5, D7→P6, D8→P7, D9→P8, D10→P9, D11→P10, D12→P11: **branding order matches exactly**.
* D14→T4, D15→T5, D16→T6, D18→T7: **themes order matches exactly**.
* D17 (armed activate panel) has **no production position** — production folds it into the T6 row Actions cell.
* D5 (local tab strip) has **no production counterpart** and must not get one (ADR 0023 item 6).
* D1 (topbar) must not be ported.

---

## 2. Difference table

Risk key: **low** = skin/copy only · **medium** = structural rework or CSS surgery · **high** = touches security posture, routing, or a locked decision.

| # | Section | Classification | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 1 | Topbar | constraint | Sticky 58px bar: eight-point elven-star SVG (`:24`), `Imladris` wordmark (`:25`), `Back to the council` (`:27`) | `templates/layout.php:27` renders `$brand['name']`; `:37-40` the operator favicon/logo; admin pages have no back link | Do **not** port. Production chrome is the operator's, not the design's. | low |
| 2 | Page head eyebrow | copy | `Operator desk · Appearance` (`:34`) | `branding.php:11` `Operator desk`; `themes.php:7-10` **no eyebrow at all** | Add `· Appearance` on branding; add the whole eyebrow to themes and theme_safe_mode | low |
| 3 | Eyebrow skin | copy | `.68rem`, `var(--gold-ink)`, `.18em` tracking | `.eyebrow` `app.css:37-43` = `.72rem`, `var(--text-muted)`, `var(--tracking-caps)` (=.16em) | Size + colour are copy differences. `.eyebrow` is global — needs one cross-screen decision, not a per-screen override | medium |
| 4 | h1 | constraint | One h1 `Branding & themes` spanning both tabs (`:35`) | Two h1s: `Branding` (`branding.php:12`), `Themes` (`themes.php:8`) | Keep two h1s. PRODUCT_DESIGN §5.3: every view has a real shareable URL; the design's merge is a prototype elision | low |
| 5 | Head pill | copy | `Admin mode`, `--surface-review`/`--on-review`, `999px`, `.72rem/.08em` (`:37`) | `.pill.pill-admin` `app.css:106` = `var(--accent)`/`var(--accent-contrast)`; `.pill` `app.css:99,1490` | Repaint `.pill-admin` to the review pair; keep the class name | low |
| 6 | Local tab nav | constraint | `<nav aria-label="Appearance sections">` with `Branding`/`Themes` buttons (`:40-45`) | The locked 8-group rail (`templates/admin/_nav.php:28-32`) already lists Branding · Themes · Custom emoji under `Appearance` | Do **not** add a duplicate local tab strip. ADR 0023 shipped item 6 locks the grouped IA (F2 conflict C3). Adding one later needs its own ADR | low |
| 7 | Branding intro | copy | `The chrome the council wears. Colours are stored as hex and resolved into the theme; the preview beside the form updates as you type.` (`:50`), `68ch`, `--text-muted` | `branding.php:20` `Tune the public name, colour accents, assets, and preview before the council sees the updated hall.` | Replace with the de-fictionalised design sentence (fiction table F3). **Both sides currently contain fiction** | low |
| 8 | Themes intro | copy | `:143` | `themes.php` has **no `.pane-intro`** | Add the de-fictionalised intro | low |
| 9 | Field layout | copy | 2-col grid, `gap: 14px 18px`, Site name spans `1 / -1` (`:54`) | `.stacked` single column; `.field` `app.css:267` `display:block; margin-bottom:14px` | Author an admin field grid; keep `.field` for the label/control pair | low |
| 10 | Field label skin | copy | `var(--font-label)`, `.68rem`, `.1em`, `uppercase`, `var(--text-faint)` (`:56` et al.) | `.field > span` `app.css:268` = `.9rem`, `weight 600`, no caps. DS `.field-label` (`public/assets/imladris.css:544`) is `.82rem/.02em/--text-muted` — also not a match | New class needed; neither existing skin matches. Author it in `app.css` | low |
| 11 | Hex field labels + placeholders | copy | `Primary colour (hex)` ph `#2F5D46`; `Accent colour (hex)` ph `#C29A44` (`:60-65`) | `branding.php:32` `Primary colour (hex, e.g. #2f6fed)` ph `#2f6fed`; `:38` `Accent colour (hex)` ph `#7c3aed` | Adopt the design's short labels + placeholder-carries-the-example pattern. Use the **real** defaults `#2e4a3a` / `#c29a44` (`tokens/colors.css:37,55`) — the design's `#2F5D46` is stale | low |
| 12 | Hex input face | copy | `var(--font-mono)`, `.88rem`, `maxlength=7` (`:61,65`) | `.input`, `maxlength="7"` (`branding.php:33,39`) — no mono | Add a mono modifier for hex fields | low |
| 13 | Marks group heading | copy | h3 `Marks`, lapidary caps `.68rem/.16em/--text-faint`, weight 400 (`:84`) | No grouping heading for the four asset fields | Add the h3 | low |
| 14 | Mark row control | constraint | Dashed-border row, label left, right-aligned `Replace`/`Upload` button, **no file input anywhere** (`:86-89`) | Real `<input type="file" accept="image/*" class="input">` ×4 (`branding.php:62,66,70,74`) | PE: a JS-proxied button breaks JS-off upload. Reproduce the dashed row using the **real** input styled via `::file-selector-button` | medium |
| 15 | Mark row order | copy | Logo, **Favicon**, Light theme logo, Dark theme logo (`:86-89`) | Logo, Light theme logo, Dark theme logo, **Favicon** (`branding.php:60-75`) | Reorder to the design | low |
| 16 | `(current set)` affix | copy | Printed unconditionally on Logo + Favicon; `var(--font-label) .7rem/.04em/--text-faint` (`:86-87`) | Printed **conditionally** on all four when the path is stored (`branding.php:61,65,69,73`) | Adopt the design's typography; keep production's conditional render (the design's is static seed) | low |
| 17 | Custom CSS gating | **constraint** | h3 `Custom CSS` + `Enable custom CSS` render **unconditionally** (`:92-93`) | Whole block behind `$custom_css_available` (`branding.php:77`), fed by `FeatureFlags::enabled('custom_css')` (`BrandingController.php:216`), flag **default false** (`FeatureFlags.php:48`) | **Keep the gate.** ADR 0009 locks custom CSS behind a dark flag + advanced confirmation. F2 conflict C8 | low |
| 18 | Custom CSS disclosure | constraint | Textarea + ack hidden behind `cssEnabled` via `sc-if` (`:94-99`) | All three controls always render when the flag is on (`branding.php:78-89`) | Reproduce the disclosure with pure CSS `:has(input[name=custom_css_enabled]:checked)` — no JS, survives JS-off. Must keep the server-rendered `checked` state so the 422 round-trip (`BrandingController.php:166`) still reveals the textarea | medium |
| 19 | Custom CSS textarea | copy | `rows="7"`, `maxlength="12000"`, `spellcheck="false"`, `aria-label="Custom CSS"`, mono `.82rem`/`1.6` (`:96`) | `rows="8"`, same maxlength/spellcheck, label is a `.field > span`; `.code-area` `app.css:3565` = mono `.85rem` (`branding.php:84`) | Adopt rows/size/line-height. Keep the visible `<label>` (better than `aria-label`) | low |
| 20 | Custom CSS flag-off copy | feature-added | No flag-off state modelled | `branding.php:92` `Custom CSS is saved behind the custom_css feature flag and is not available on this install.` | Keep. Restyle as the gold status callout; align register with `_nav.php:5` `Disabled until the feature flag is enabled` | low |
| 21 | Save row | constraint | Inline `role="status"` `Saved. The chrome is live for everyone.` and `role="alert"` error beside the submit (`:103-104`) | POST-redirect-GET → `redirectWithFlash('/admin/branding', 'Branding updated.')` (`BrandingController.php:200`), flash rendered by `partials/flash` (`layout.php:61,72,78`); errors via `field_error` | Keep PRG (double-submit safety) + per-field errors. Adopt the design's **placement**: give the card a status slot the flash and a 422 summary render into | medium |
| 22 | Submit button | copy | `9px 20px`, `--radius-md`, `--accent`/`--accent-contrast`, `var(--font-label) .8rem/.04em` (`:102`) | `<button class="btn">` (`branding.php:95`) | Skin `.btn` inside the admin pane to the design metrics | low |
| 23 | Preview shell | copy | `--radius-lg`, `--shadow-sm`, `1px --border-hair` (`:111`) | **Declared twice**: `app.css:882-887` (`--radius`, `--border`, `--surface`, no shadow) and `app.css:3525-3530` (`--radius-lg`, `--border-hair`, `--shadow-sm`) | De-duplicate; keep the design values | medium |
| 24 | **Preview does not update as you type** | copy *(live defect)* | The design's whole premise: "the preview beside the form updates as you type" (`:50`); bar + accent repaint from `--bp-primary`/`--bp-accent` on every keystroke (`x-dc:277-286`) | `.brand-preview-bar { background: var(--brand) }` `app.css:3535` and `.brand-preview-accent { border-left: 3px solid var(--accent-2) }` `app.css:3562` **override** the `--preview-*`-driven rules at `app.css:892,900` (later, same specificity, both unlayered). `app.js:145-147` still sets `--preview-accent`/`--preview-accent-2`, so only `.brand-preview-body a` (`:897`) and `.brand-preview-body .btn` (`:898`) track typing | **Fix before reproducing the screen.** Delete/merge the `app.css:3521-3565` duplicate so the bar and accent marker paint from `--preview-*` | high |
| 25 | Accent marker anatomy | copy | Filled pill: `999px`, `background: var(--bp-accent)`, `color: var(--ink-900)` (`:119`) | `.brand-preview-accent` = 3px left border + `--gold-700` text (`app.css:3557-3564`) | Adopt the pill, painted from `--preview-accent-2`; use `var(--radius-pill)` for the design's literal `999px` | low |
| 26 | Preview bar ink | feature-changed | Hardcoded `color: var(--parchment-50)` (`:112`) | Computed `--preview-accent-contrast` (`app.js:146`), mirroring `BrandingController::contrastToken()` (`:283-291`) | Keep the computed contrast — ADR 0009 requires contrast checks; a fixed parchment ink fails on a light brand colour | low |
| 27 | Preview aside | copy | `380px` column, `position: sticky; top: 84px` (`:52,108`) | `.brand-cols` `300px` (`app.css:3517`); `.brand-preview` sticky `top: 84px` (`app.css:3521-3524`); collapses at `max-width: 760px` (`app.css:5582,5607-5612`) | Widen to 380px; keep the 760px collapse | low |
| 28 | Preview eyebrow + footnote | *match* | `Live preview` (`:110`); footnote `:122` | `branding.php:99` `Live preview`; footnote `:111` — **byte-identical** | No change. Preserve verbatim | low |
| 29 | Reset card placement | copy | Inside the aside, below the preview; `--surface-raised`, `--radius-lg`, `border-left: 3px solid var(--rust)` (`:125`) | Full-width `form.stacked.card` **below** `.brand-cols` (`branding.php:115`), no left rule | Move into the aside; add the rust left-rule | low |
| 30 | Reset heading | copy | h2 `Reset to defaults` (`:126`) | No heading (`branding.php:115-125`) | Add the h2 | low |
| 31 | Reset copy | copy | `Clears every stored colour, logo, favicon, theme preset, and custom CSS, restoring the built-in chrome. It cannot be undone.` (`:127`) | `Reset clears every stored colour…` (`branding.php:118`) — identical but for the leading `Reset ` | Trim the redundant prefix once the h2 exists | low |
| 32 | Reset confirm label | copy | `Type RESET to confirm` as a lapidary caps span (`:129`) | `Type <code>RESET</code> to confirm` (`branding.php:120`) | Adopt the caps span | low |
| 33 | Reset button gating | constraint | `disabled="{{ resetLocked }}"`, unlocked only when the field literally reads `RESET` (`:132`, `x-dc:330`) | Server-enforced: `trim($request->str('reset_confirm')) !== 'RESET'` → 422 + `Type RESET to confirm restoring the default branding.` (`BrandingController.php:92-96`) | Keep server enforcement. The `disabled` attribute may only be a JS decoration; JS-off must still reach the 422 | low |
| 34 | Reset success copy | copy | `Reset. The built-in chrome is back.` (`:133`) | Flash `Branding was reset to the safe defaults.` (`BrandingController.php:114`) | Adopt the design's register | low |
| 35 | Reset 422 loses the branding form | constraint | n/a — the design has one shared state bag | A failed reset re-renders `formData()` from **settings** (`BrandingController.php:93-96`), discarding anything typed in the sibling branding form | Moving reset into the aside makes the two forms read as one card — the anti-draft-loss round-trip must be extended to carry the branding form's `->old` through the reset 422 | medium |
| 36 | Contrast rejection | feature-added | No such state | `BrandingController.php:130-135`: `Choose a primary colour that supports readable button text.` / `Choose an accent colour with enough contrast for UI indicators.` — ADR 0009 "Accessibility checks must warn on color contrast regressions" | Keep; render via `field_error` in the design's alert skin | low |
| 37 | Custom CSS safety validation | feature-added | Only the ack check | `BrandingController.php:316-337`: 12 KB cap, `@import`, `javascript:`/`expression(`, remote/data URLs, destructive-ID selectors — all five verbatim in ADR 0009 | Keep all five messages | low |
| 38 | Asset-upload rejection | feature-added | No such state | `BrandingController.php:194-199` `Branding updated, but {Label} upload was rejected: {reason} The previous asset was kept.` | Keep; style as the gold status callout, not a success flash | low |
| 39 | `branding` flag gate | feature-added | No gate | `BrandingController::requireBrandingEnabled()` `:261-266` throws `NotFoundException`; `_nav.php:29` gates the link | Keep | low |
| 40 | a11y error wiring | feature-added | Plain `role="alert"` span, no field association | `field_attrs`/`field_error` on every field (`branding.php:27,29,33,35,39,41,84,90,121,123`) → `aria-invalid` + `aria-describedby` + autofocus-on-first-error (`src/Support/helpers.php:100-135`), ADR 0023 item 5 | Keep; the design idiom must absorb it | low |
| 41 | Themes head | copy | Eyebrow + intro present | `themes.php:7-10` has **no eyebrow** and **no `.pane-intro`** | Add both | low |
| 42 | Themes error strip | copy | Errors anchored where they occur (`:225`) | `themes.php:14-16` renders every error as a page-top `<p class="field-error">` strip | Anchor the activation/rollback error to its own form; drop the page-level strip | low |
| 43 | Policy error string | copy | n/a | `AdminThemeController::policyMessage()` `:206` returns `"{$e->code}: {$e->getMessage()}"` — a raw machine code in operator prose | Map codes to sentences; register violation | low |
| 44 | Safe mode card | feature-changed | One card, inline toggle button `Turn safe mode on` / `Turn safe mode off` (`:152`, `x-dc:343`), no password either way | `themes.php:18-26` card + `<a href="/admin/themes/safe-mode">Open recovery page</a>`; entering needs no password, **exiting requires the current password** (`ThemeStateService.php:59-64`); the recovery page is deliberately excluded from package-theme serving (`App.php:567`) so it survives a broken theme | Adopt the design's inline card. When off → inline POST `Enter safe mode`. When on → on-state copy + link to the password-gated recovery page. **Do not collapse the recovery page** | medium |
| 45 | Safe mode on-state copy | copy | `Safe mode is on. Every visitor sees the built-in chrome, whatever is installed.` in `var(--on-review)` (`:150`) | `themes.php:21` `Theme safe mode is on. The built-in system theme is being served.` rendered as `.field-error` (danger red) | Adopt the design's sentence and the review/gold register — safe mode is a deliberate posture, not an error | low |
| 46 | Safe mode off-state copy | *match* | `Safe mode is off. Active package themes are eligible to serve.` (`:149`) | `themes.php:23` — **byte-identical** | No change | low |
| 47 | Forced safe mode | feature-added | No such state | `theme_safe_mode.php:26-28` `The environment override is forcing safe mode. Remove THEME_SAFE_MODE=1 before exiting here can take effect.` + `:42`; fed by `AdminThemeController:138` (`theme.safe_mode` config) | Keep. Style as the rust status callout. (Copy note: "before exiting here can take effect" is ungrammatical — fix while restyling) | low |
| 48 | Recovery page chrome | constraint | Not modelled | `theme_safe_mode.php:5` `variant=plain` — the only admin page outside the app shell — yet still draws the full grouped rail at `:13` inside a bare `.container` | Keep `variant=plain` (it must render when a package theme has broken the console) but resolve the rail contradiction and give the page the eyebrow/intro treatment | medium |
| 49 | Active theme card | copy | Two prose lines: `Serving <strong>{name}</strong> <code>{version}</code>` and `Last-known-good: <code>{digest}</code> from {package}.` (`:160-161`) | `themes.php:33-41` a 5-row `<table class="audit">` (`Package`/`Version`/`CSS digest`/`Install state`/`Activated`) + LKG muted line `:45` | Adopt the design's prose lead; move the remaining facts into a `<dl>` spec list (a **new anatomy** — `components/doc.css` is outside the runtime closure, so author it in `app.css`) | low |
| 50 | Active theme extra facts | feature-added | Not modelled | `themes.php:37` CSS digest, `:38` Install state, `:39` `Activated {state.activated_at} UTC` | Keep all three in the spec list | low |
| 51 | Active theme under safe mode | feature-changed | Card blanks to `No package theme is active…` when safe mode is on (`x-dc:346-347`) | `AdminThemeController::themeData()` `:164` reads `$state['active_build_id']` **directly** (not `activeBuild()`, which returns null in safe mode — `ThemeStateService.php:79-81`), so production keeps showing the configured theme | Keep production's data (it is the honest reading) and add a "not serving while safe mode is on" note instead of blanking | low |
| 52 | Rollback affordance | feature-changed | Bare button `Roll back to last-known-good` inside the Active theme card, no password, shown whenever a theme is active (`:162`) | `themes.php:44-51`: rendered **only when `$lkg !== null`**, requires `Current password` (`ThemeStateService::rollback` → `ReauthGate::requirePassword`, `src/Security/ReauthGate.php:42-43` `Your current password is incorrect.`) | Adopt placement + label; keep the password field and the LKG gate | low |
| 53 | Design's `deactivate` handler | feature-removed | The button labelled "Roll back…" actually clears `activeId` — a **deactivate**, not a rollback (`x-dc:352`) | `ThemeStateService::deactivate()` exists (`:203`) but has **no route** in `buildRouter()` (`App.php:2235-2241`) — it is only reached from `onInstallIneligible()` and package uninstall | Do **not** build a deactivate button. Do not ship dead chrome | low |
| 54 | Install table columns | *match* | `Package` `Version` `State` `Latest build` `Actions` (`:175-179`) | `themes.php:61` — same five, same order | No change | low |
| 55 | Package cell | copy | `<strong>` (block, weight 500, `--text-strong`) + `<code>` uid `.74rem/--text-faint` (`:184`) | `<strong>…</strong><br><code>…</code>` (`themes.php:65`) | Replace the `<br>` with the block/`--text-faint` treatment | low |
| 56 | State pill | feature-changed | Exactly two states: `Enabled` (`--surface-done`/`--on-done`) and `Disabled` (`--surface-pending`/`--on-pending`) (`:187-188`) | Three states are reachable: `PackageThemeRepository::themeInstalls()` `:159` selects `ip.state IN ('installed','enabled','disabled')`; `themes.php:67` renders `ucfirst($state)` in a neutral `.pill` | Adopt the two-tone pill vocabulary but keep **three** states — map `installed` to the pending register. `.state-pending`/`.state-active` already exist (`app.css:3481-3497`) | low |
| 57 | `not built` | *match* | `:192` | `themes.php:72` | No change | low |
| 58 | Row action: Preview | constraint | `<button onClick="{{ p.preview }}">` (`:197`) | `<form method="post" action="/admin/themes/{id}/preview">` + `csrfField()` (`themes.php:77-80`) | Keep the CSRF form; style the submit as the design's ghost button | low |
| 59 | Row action: Activate | constraint | Arms a separate confirmation panel with a password field (`:213-227`); the armed state is client-only, no URL | Per-row inline `<form class="stacked">` with a required `current_password` sitting **in the Actions cell** (`themes.php:81-85`) | The armed panel has no URL and cannot exist under PE as drawn. Two options: **(a)** a real `GET /admin/themes/{id}/activate` confirmation page (matches ADMIN §4.5 and the `structure_confirm`/`tag_merge_confirm` precedent) — **needs an ADR**; **(b)** wrap the existing inline form in a `<details>` disclosure (no new route, JS-off safe). Recommend (b) for the first slice | high |
| 60 | Activate impact copy | copy | `Every visitor sees this theme on their next page. The current build is kept as last-known-good, so safe mode can always bring the council home.` (`:216`) | **No impact copy at all** | Add (de-fictionalised) — this is exactly the "impact copy" ADMIN §4.5 asks for | low |
| 61 | Disabled-row action | *match* | `Enable it from Packages first` with `href="#"` (`:201`) | `themes.php:87` — same string, real `href="/admin/packages/{package_id}"` | No change; the design's `#` is a placeholder (governing rule 4) | low |
| 62 | `.table-scroll` region | feature-added | Bare `<table>` | `themes.php:59` `<div class="table-scroll" tabindex="0" role="region" aria-label="Installed theme packages">` — ADR 0023 item 5 | Keep the wrapper; style it to the design's table metrics | low |
| 63 | Preview section | *match* | `Previewing <strong>{name}</strong> <code>{digest}</code> in this admin session only.` / `No session preview is active.` / `End preview` (`:233,238,234`) | `themes.php:103,101,106` — **byte-identical** | Skin only | low |
| 64 | Preview digest fallback | feature-changed | `previewDigest = preview.digest \|\| 'not built'` (`x-dc:378`) | `ThemeStateService::previewBuildFor()` `:92-99` only resolves a **serveable** build, so a digest always exists | Drop the `not built` branch in the Preview card | low |
| 65 | Custom emoji | feature-added | Not modelled anywhere in the screen | `templates/admin/custom_emoji.php` (96 ln), `_nav.php:31`, `App.php:2324-2327`, flag `custom_emoji` default **true** (`FeatureFlags.php:66`) — the third Appearance-group entry | Keep. Restyle in the design idiom in its own slice. ADR 0023 item 4 (422 re-render + honest "replaced" flash) is binding | low |
| 66 | `--bp-primary` / `--bp-accent` | constraint | Screen-local custom properties (`:111`), written imperatively by `paintPreview()` (`x-dc:280-281`) | `--preview-accent` / `--preview-accent-2` already exist (`app.css:878,880`) and are written by `app.js:145-147` | Keep production's names. `element.style.setProperty()` from an external script is **not** CSP-governed and is already the production idiom | low |
| 67 | Inline styles + `<helmet><style>` | constraint | ~230 `style="…"` attributes, 15 `style-hover=`/`style-focus=`, and `@keyframes aaRise` in a `<helmet><style>` (`:13-17`) | `SecurityHeaders` emits `style-src 'self'` with no `style-src-attr`; production templates carry **zero** inline style attributes | Every rule becomes a class in `public/assets/app.css`. Mechanism only — the rendered spacing, order and anatomy must still match | medium |
| 68 | dc-runtime behaviour | constraint | `sc-if`/`sc-for`, `onSubmit=`/`onClick=`/`onInput=`/`onChange=`, `ref=`, the whole `x-dc` state machine | Server-rendered PHP + real forms; `public/assets/app.js` decorates only | Every state must be a server render or a real URL. `./support.js` and `./ds-base.js` never ship (PRODUCT_DESIGN §6.14) | low |
| 69 | Literal `999px` radii | copy | `999px` on the head pill (`:37`), state pills (`:187-188`), accent marker (`:119`) | `--radius-pill` exists in the token set | Use `var(--radius-pill)`; transcribe every other literal px verbatim (the screen never uses `--space-N`) | low |
| 70 | Spacing scale | copy | Zero `var(--space-N)` references; every gap/padding is a literal px | app.css mixes both | Transcribe the design's literal px verbatim — spacing must match | low |

**Counts (64 classified rows):** copy **32** · feature-added **10** · feature-removed **1** · feature-changed **6** · constraint **15**.

Six rows are marked *match* — the design and production strings are byte-identical, so they carry no action and are excluded from the totals: **#28** (Live preview eyebrow + logo-variant footnote), **#46** (safe-mode off copy), **#54** (install table columns), **#57** (`not built`), **#61** (`Enable it from Packages first` — production additionally supplies the real href), **#63** (the whole Preview card).

---

## 3. Fiction strings (design → proposed production)

| # | Design string (line) | Proposed production string |
|---|---|---|
| F1 | `Imladris` — wordmark, topbar (`:25`); also `siteName: 'Imladris'` seed (`x-dc:260`) | Do not port the topbar. Production renders `$brand['name']` (`layout.php:27`). Seed value → `RetroBoards` (which `doReset` already uses at `x-dc:335`) |
| F2 | Eight-point elven-star SVG (`:24`) | Not a RetroBoards mark. Production uses `$brand['logo_path']` / the favicon (`layout.php:37-40`) |
| F3 | `Back to the council` (`:27`) | `Back to the forum` — **but do not add the link**; production admin pages have no back affordance |
| F4 | `The chrome the council wears.` (`:50`) | `The chrome this community wears.` |
| F5 | `Safe mode drops the council back to the built-in chrome without uninstalling anything.` (`:143`) | `Safe mode drops the site back to the built-in chrome without uninstalling anything.` |
| F6 | `No package theme is active. The council wears the built-in chrome.` (`:166`) | `No package theme is active. The site uses the built-in chrome.` |
| F7 | `…so safe mode can always bring the council home.` (`:216`) | `…so safe mode can always restore it.` |
| F8 | `The council needs a name.` (`x-dc:291`) | `Enter a site name (max 80 characters).` — production's existing, more precise message (`BrandingController.php:120`) |
| F9 | `Imladris Classic` / `rb.theme.imladris-classic` (`x-dc:251`, `:351`) | Neutral sample package name + namespace |
| F10 | `Twilight Hall` / `rb.theme.twilight-hall` (`x-dc:252`) | Neutral sample |
| F11 | `Mallorn` / `rb.theme.mallorn` (`x-dc:253`) | Neutral sample |
| F12 | `Greyhavens` / `rb.theme.greyhavens` (`x-dc:254`) | Neutral sample |
| F13 | `#2F5D46` primary placeholder (`:61`) | `#2e4a3a` — the real `--green-700` (`tokens/colors.css:37`). Not fiction, but stale |

### Fiction already shipped in these production files (must be fixed in the same pass)

* `templates/admin/branding.php:20` — `…and preview before **the council sees the updated hall**.` → replaced wholesale by the de-fictionalised design intro (#7).
* No other fiction found in `themes.php`, `theme_safe_mode.php` or `custom_emoji.php` (grepped).

---

## 4. State inventory

| Design state | Verbatim string(s) | Production equivalent | Verdict |
|---|---|---|---|
| `brandSaved` | `Saved. The chrome is live for everyone.` (`:103`) | Flash `Branding updated.` (`BrandingController.php:200`) | present — copy differs, placement differs (#21, #34) |
| `brandError` — empty name | `The council needs a name.` (`x-dc:291`) | `Enter a site name (max 80 characters).` (`:120`) | present — fiction (F8) |
| `brandError` — primary hex | `Primary colour must be a hex value, e.g. #2F5D46.` (`x-dc:292`) | `Use a 6-digit hex colour like #2f6fed.` (`:125`) | present — copy |
| `brandError` — accent hex | `Accent colour must be a hex value, e.g. #C29A44.` (`x-dc:293`) | `Use a 6-digit hex colour like #7c3aed.` (`:128`) | present — copy |
| `brandError` — unacknowledged CSS | `Acknowledge that custom CSS applies site-wide before saving it.` (`x-dc:294`) | `Confirm that custom CSS can affect the whole site.` (`:151`) | present — copy |
| — | — | `Choose a primary colour that supports readable button text.` / `Choose an accent colour with enough contrast for UI indicators.` (`:131,134`) | **feature-added** (#36) |
| — | — | 5 custom-CSS safety messages (`:321-335`) | **feature-added** (#37) |
| — | — | `Branding updated, but {Label} upload was rejected: {reason} The previous asset was kept.` (`:197`) | **feature-added** (#38) |
| `resetLocked` | button `disabled` until the field reads `RESET` (`x-dc:330`) | Server-side 422 + `Type RESET to confirm restoring the default branding.` (`:92-96`) | present — constraint (#33) |
| `resetDone` | `Reset. The built-in chrome is back.` (`:133`) | Flash `Branding was reset to the safe defaults.` (`:114`) | present — copy (#34) |
| `safeModeOff` | `Safe mode is off. Active package themes are eligible to serve.` (`:149`) | `themes.php:23` | **byte-identical** |
| `safeModeOn` | `Safe mode is on. Every visitor sees the built-in chrome, whatever is installed.` (`:150`) | `Theme safe mode is on. The built-in system theme is being served.` (`themes.php:21`) | present — copy + wrong register (#45) |
| `safeModeAction` | `Turn safe mode on` / `Turn safe mode off` (`x-dc:343`) | `Enter safe mode` / `Exit safe mode` buttons on the recovery page (`theme_safe_mode.php:35,48`); exit needs a password | feature-changed (#44) |
| — | — | `The environment override is forcing safe mode. Remove THEME_SAFE_MODE=1 before exiting here can take effect.` (`theme_safe_mode.php:27`) + `Environment-forced safe mode cannot be exited from this page.` (`:42`) | **feature-added** (#47) |
| `hasActive` | `Serving …` + LKG line (`:160-161`) | `themes.php:33-45` table + LKG line | present — copy (#49) |
| `noActive` | `No package theme is active. The council wears the built-in chrome.` (`:166`) | `No package theme is active.` (`themes.php:31`) | present — fiction on the design side (F6) |
| `hasInstalls` / `noInstalls` | `No theme packages are installed.` (`:209`) | `themes.php:57` | **byte-identical** |
| `p.isEnabled` / `p.isDisabled` | `Enabled` / `Disabled` pills (`:187-188`) | `ucfirst($install['state'])` over three states (`themes.php:67`) | feature-changed (#56) |
| `p.isBuilt` / `p.notBuilt` | `not built` (`:192`) | `themes.php:72` | **byte-identical** |
| `activateArmed` | `Activate {name}?` + impact copy (`:215-216`) | **no armed state** — inline per-row form (`themes.php:81-85`) | gap (#59, #60) |
| `activateError` | `Confirm your password to activate a theme.` (`:225`) | `Your current password is incorrect.` (`ReauthGate.php:43`) rendered in the page-top strip (`themes.php:14-16`) | present — copy + placement (#42) |
| `hasPreview` / `noPreview` | `Previewing … in this admin session only.` / `No session preview is active.` (`:233,238`) | `themes.php:103,101` | **byte-identical** |
| `previewDigest \|\| 'not built'` | (`x-dc:378`) | unreachable — `previewBuildFor()` resolves only serveable builds (`ThemeStateService.php:92-99`) | feature-changed (#64) |
| **loading** | *none in this screen* | n/a | no loading skeleton here (unlike AdminOverview) |
| — | — | Policy failures rendered as `"{code}: {message}"` (`AdminThemeController.php:206`) | **feature-added**, wrong register (#43) |

---

## 5. Slice proposal

Each slice is independently shippable and independently testable. Slices 0 and 1 are prerequisites for the visual work.

### Slice 0 — Repair the live preview (bug fix, no design adoption)
**Why first:** the design's stated premise is "the preview beside the form updates as you type", and it currently does not. Reproducing the screen on top of a broken preview would bake the defect in.
**Touches:** `public/assets/app.css` (delete/merge the duplicate `.brand-preview-*` block at `:3521-3565` into `:876-903` so the bar background and accent marker paint from `--preview-accent`/`--preview-accent-2`); optionally `public/assets/app.js` (extend `contrastToken` to the accent pill).
**Tests:** new PHPUnit case in `tests/Integration/Core/AppBrandingThemeTest.php` asserting only one `.brand-preview-bar { background }` declaration is not sufficient — this needs **browser evidence**: a Playwright spec that types a hex into `[data-brand-primary]` and asserts the computed `background-color` of `.brand-preview-bar` changed. Plus `composer verify:imladris` after the digest refresh.
**Risk:** high value, medium blast radius (touches only `.brand-preview-*`).

### Slice 1 — Themes page head + honesty pass (copy only, no layout)
**Touches:** `templates/admin/themes.php` (add eyebrow `Operator desk · Appearance`, add `.pane-intro`, rewrite safe-mode on-state copy + register, move the error strip onto its form, add the LKG "not serving under safe mode" note); `templates/admin/theme_safe_mode.php` (eyebrow/intro, fix the ungrammatical env-override sentence); `src/Controller/AdminThemeController.php:206` (map policy codes to sentences); `templates/admin/branding.php:20` (de-fictionalise the intro).
**Tests:** `AppImladrisFidelityTest` — extend `test_admin_sibling_pages_render_inside_the_operator_console_register` to include `/admin/themes` and assert `eyebrow`/`pane-intro`. A no-fiction assertion (`assertDoesNotSeeText($res, 'council')`) on `/admin/branding` and `/admin/themes`.
**Risk:** low.

### Slice 2 — Branding screen skin (the bulk of the design adoption)
**Touches:** `templates/admin/branding.php` (2-col field grid, mono hex inputs, short hex labels + real-default placeholders, `Marks` h3 + dashed mark rows with `::file-selector-button`, reorder Favicon second, custom-CSS `:has()` disclosure, status slot beside the submit, reset card moved into the aside with its rust rule + h2); `public/assets/app.css` (all new classes — mark row, field grid, admin field label, status slot, rust/gold callouts, accent pill, 380px aside).
**Preserves:** `.brand-cols` and `.brand-preview` class names (pinned by `AppImladrisFidelityTest:97-98`); `field_attrs`/`field_error` wiring; the `custom_css_available` gate; PRG.
**Tests:** `AppBrandingThemeTest` (all 11 existing cases must stay green, especially `test_custom_css_requires_flag_and_confirmation_before_emitting` and `test_reset_restores_defaults`); `AppFieldErrorA11yTest`; a new 422 round-trip case asserting the custom-CSS textarea is still revealed after a failed save; CSP scan `rg -n "<script|<style| on[a-z]+=" templates/admin`; Playwright desktop + mobile screenshots + a `javaScriptEnabled: false` context proving Save branding, the file inputs and the typed RESET all work.
**Risk:** medium.

### Slice 3 — Reset 422 draft preservation
**Touches:** `src/Controller/BrandingController.php:93-96` (carry the branding form's submitted values into the reset-422 re-render).
**Tests:** new `AppBrandingThemeTest` case: POST with `reset=1`, a wrong `reset_confirm`, and a changed `site_name`; assert 422 **and** that the typed site name survives in the body.
**Risk:** low. Can ship before or after Slice 2 but is only *visible* once the two forms read as one card.

### Slice 4 — Themes screen skin + safe-mode inline card
**Touches:** `templates/admin/themes.php` (Active-theme prose lead + `<dl>` spec list, three-state pill, package cell block/`--text-faint`, inline safe-mode card with an `Enter safe mode` POST when off and a recovery link when on, rollback moved into the Active theme card, `<details>` disclosure around the per-row activate form + impact copy); `templates/admin/theme_safe_mode.php` (rail/`variant=plain` reconciliation); `public/assets/app.css`.
**Preserves:** `.table-scroll` region, CSRF on every form, the LKG gate on rollback, the password requirement on exit-safe-mode and activate.
**Tests:** all 12 cases in `AppThemePackageTest` (especially `test_admin_activation_requires_password_and_enabled_install`, `test_admin_safe_mode_route_serves_system_theme_while_theme_active`, `test_admin_rollback_serves_exactly_the_lkg_bytes`); a new case asserting a package in state `installed` renders the pending pill and the "Enable it from Packages first" link; Playwright JS-off evidence for enter-safe-mode, activate and end-preview.
**Risk:** medium.

### Slice 5 — Custom emoji in the idiom (feature-added, design has no model)
**Touches:** `templates/admin/custom_emoji.php`, `public/assets/app.css`.
**Preserves:** ADR 0023 item 4 (422 re-render + honest "replaced" flash), the per-field `field_attrs`/`field_error` wiring already at `:31-53`.
**Tests:** existing custom-emoji integration tests + a Playwright screenshot proving the two-card layout matches the Branding/Themes register.
**Risk:** low.

### Slice 6 (deferred, needs an ADR) — Real activate confirmation route
Add `GET /admin/themes/{id}/activate` as a server-rendered confirmation page carrying the design's `Activate {name}?` panel verbatim, matching the `structure_confirm` / `tag_merge_confirm` precedent and ADMIN §4.5. **Do not ship inside this migration** — it is a routing change and a destructive-posture change. Record the decision in the new ADR (next free number after 0023).

### Cross-cutting, every slice
1. Refresh `config/imladris-runtime-baseline.json → application_surface.sha256` from `php bin/build-imladris-assets.php --print-application-digest`, then `composer check:imladris` + `composer verify:imladris`.
2. New CSS lands **unlayered in `public/assets/app.css`** — never in the generated `public/assets/imladris.css`, never in `docs/design-system/imladris/components.css` (DesignSync-owned), never with `!important`, and never re-declaring a design token in `:root`.
3. `--gold-050` is not defined anywhere — this screen does not use it, but if a gold wash is needed for the status callout use `--gold-soft`.
4. Run `vendor/bin/phpunit` directly (not `composer test` — 300 s timeout) with a private `DB_TEST_DATABASE`.
