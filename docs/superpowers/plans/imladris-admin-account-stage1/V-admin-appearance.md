# V — admin-appearance: adversarial verification of D-admin-appearance.md

**Verdict in one line:** the production half of the report is excellent and survives verification
almost intact; the **design half is stale** — the peer read a superseded copy of the file, and three
of its structural recommendations now point the wrong way. One classification is a false
`feature-added` with a real spec sitting in another template. One fiction call is factually wrong
about production.

---

## 0. The headline: the report was written against a superseded design file

The report opens: *"385 lines; markup ends at line 246, `<script type="text/x-dc">` runs 247–383"*.

On disk right now:

```
$ wc -l docs/design-system/imladris/templates/admin-appearance/AdminAppearance.dc.html
373
$ git show HEAD:.../AdminAppearance.dc.html | wc -l
385
$ git status --porcelain .../admin-appearance/
 M docs/design-system/imladris/templates/admin-appearance/AdminAppearance.dc.html
```

The working tree was re-synced (DesignSync, file mtime `Aug 3 20:36`) as part of a larger pass that
also touched `AccountSettings`, `AdminContent`, `AdminNotifications`, `AdminOverview`, `AdminPeople`,
`AdminSettings`, `components.css`, `manifest.json`, `production-contract.json`, and added four
brand-new templates (`admin-features/`, `admin-integrations/`, `admin-members/`, `admin-packages/`)
plus `components/admin/`, `PRODUCTION.md`, `RETIRED.md`, `REDUNDANCY-AUDIT.md`.

The diff is `4 insertions, 16 deletions` at the top of the file. **Every design-side line citation in
the report is exactly +12 too high** and must be re-derived before anyone acts on it. Spot check:

| Report cites | Actual |
|---|---|
| Branding intro `:50` | `:38` |
| Field grid `:54` | `:42` |
| h3 `Marks` `:84` | `:72` |
| h3 `Custom CSS` `:92` | `:80` |
| Save row `:101-105` | `:89-93` |
| Reset card `:125` | `:113` |
| Themes intro `:143` | `:131` |
| Armed activate `:213-227` | `:201-215` |
| `x-dc` `resetLocked` `:330` | `:318` |
| `x-dc` `previewDigest` `:378` | `:366` |

---

## 1. REFUTED

### R1 — #1 / #2 / #3 / #5 describe markup that no longer exists

The 16 deleted lines are precisely the sticky topbar, the eyebrow, the h1 wrapper and the mode pill.
They are replaced by one line:

```html
<!-- AdminAppearance.dc.html:22 -->
<x-import component-from-global-scope="ImladrisDesignSystem_c3e027.AdminNav" area="appearance" hint-size="100%,101px"></x-import>
```

and a bare `<h1>` at `:26` (2.1rem, was 2.4rem). There is **no** `Operator desk · Appearance` eyebrow
and **no** `Admin mode` pill anywhere in the screen any more.

This is not an accident of the sync — it is a stated design decision. `components/admin/admin.card.html:41`:

> "Measured against the pages it replaces, this chrome is 10px *shorter*: the redundant
> "Operator desk&nbsp;·&nbsp;Area" kicker is gone, the mode pill moved into the identity row, and the
> heading drops from 2.4rem to 2.1rem."

**Consequence:** the report's #2 and #41 actions — *"Add `· Appearance` on branding; add the whole
eyebrow to themes and theme_safe_mode"* — are now **backwards**. Slice 1 as written would add an
element the design has deliberately deleted, moving production *away* from the design. Production's
`branding.php:11` (`<span class="eyebrow">Operator desk</span>`) and `custom_emoji.php:12`
(`<span class="eyebrow">Appearance</span>`) should be **removed**, not propagated to `themes.php`.
#3 (eyebrow skin, `.68rem`/`--gold-ink`/`.18em`) is moot for this screen.

### R2 — FALSE `feature-added`: the design DOES model custom emoji

Report #65 and Slice 5: *"Custom emoji — Not modelled anywhere in the screen … the design has no
model"*, with a slice that invents an idiom for it.

It is modelled in full, on the sibling screen `templates/admin-features/AdminFeatures.dc.html`, as
the third sub-tab of "Features & badges" (`:33-34` `Custom emoji`, `:216-283` the panel). Verbatim:

| Design (`AdminFeatures.dc.html`) | Production (`templates/admin/custom_emoji.php`) |
|---|---|
| `:219` "Add approved static assets to the post renderer and optionally make them available as reactions. **Assets are served from the media root; nothing is uploaded here.**" | `:21` same sentence, **second clause missing** |
| `:222` h2 `Add or replace emoji` | `:25` — identical |
| `:226-241` `Shortcode` / `Name` / `Asset path` / `MIME type` (`image/webp`, `image/png`) | `:29-54` — identical fields, identical option order |
| `:243-245` `Allow as a reaction` Switch, **gated behind `reactionsOn`** | `:56-59` `.checkline`, **always rendered** |
| `:246` `Save emoji` | `:60` — identical |
| `:253-258` table `Emoji` / `Name` / `Asset` / `Reactions` / `Status` / `Action` | `:71` — identical six columns, identical order |
| `:271-272` `Enabled` / `Disabled` pills | `:80` bare text `Enabled` / `Disabled` |
| `:281` "No custom emoji have been added yet." | `:67` — **byte-identical** |
| *(no heading on the catalogue section)* | `:65` h2 `Catalogue` — production-only |
| `:263-266` monogram initial + `<code>{{ m.code }}</code>` | `:76` real `<img …width="24" height="24">` + `<code>` |

So custom emoji is a `copy`/`feature-changed` screen with a spec to follow, not a `feature-added`
blank slate. The design's placeholders (`mallorn`, `Mallorn leaf`, `/emoji/mallorn.webp`) are fiction
and must not be pasted; production's `party` / `Party` / `/emoji/party.webp` already handle that.

### R3 — FALSE claim about production: fiction entry F2

> F2 — "Eight-point elven-star SVG (`:24`) → **Not a RetroBoards mark**; production uses
> `$brand['logo_path']` / the favicon (`layout.php:37-40`)"

Production **already ships the eight-point star** as its default brand mark, with the same path data
as the design's:

```php
// templates/partials/topbar.php:11
<a class="brand" href="/" aria-label="…"><?php if (!empty($branding['logo_path'])): ?><img class="brand-logo" …>
<?php else: ?><svg class="brand-star" viewBox="0 0 100 100" aria-hidden="true"><g …>
  <path d="M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z"/>
  <path d="M50 21 57.5 42.5 79 50 57.5 57.5 50 79 42.5 57.5 21 50 42.5 42.5Z" opacity="0.5"/>
```

It is the fallback whenever no operator logo is set. Calling it "not a RetroBoards mark" is wrong and
would have us treat shipped chrome as fiction to strip.

### R4 — wrong citations in #1 / F1 / F2

* *"`templates/layout.php:27` renders `$brand['name']`"* — `:27` is the `<title>` element
  (`<title><?= $e($this->block('title', $brand['name'])) ?></title>`), not a wordmark.
* *"`:37-40` the operator logo/favicon"* — `:37-40` is **only** the `<link rel="icon">` block. There
  is no logo there; the logo is `partials/topbar.php:11`.

### R5 — #26 contradicts #24 and misstates production's rendered behaviour

#26 says the design hardcodes `color: var(--parchment-50)` while *"production computes
`--preview-accent-contrast` (app.js:146)"*, action *"Keep the computed contrast"*.

But the very duplicate block #24 flags also carries the ink:

```css
/* public/assets/app.css:3531 */
.brand-preview-bar {
    …
    background: var(--brand);        /* :3536 — overrides :893 var(--preview-accent) */
    color: var(--parchment-50);      /* :3537 — overrides :894 var(--preview-accent-contrast) */
}
```

Production therefore **already renders** the design's hardcoded parchment ink; the computed contrast
at `:894` and `app.js:146` is dead. The recommendation (restore the computed contrast) is right; the
description of current behaviour is wrong, and the two rows contradict each other.

### R6 — #24 is real but understated, and one line number is off

The defect is confirmed and is the report's best finding. Two corrections:

* The bar's background declaration is **`app.css:3536`**, not `:3535` (`:3531` is the selector). The
  overridden rule is `:893`, not `:892`. `.brand-preview-accent` `:3562` vs `:900` is correct.
* It is worse than "does not update as you type". The overriding value is `var(--brand)`, and
  `--brand` is a **static design token** — `app.css:813 --brand: var(--green-500)`. `/brand.css` never
  emits it: `BrandingController::css()` writes `--accent`, `--brand-primary`, `--accent-contrast`,
  `--accent-2`, `--brand-accent`, `--brand-accent-contrast` (`:51`, `:55`) and nothing else. So the
  preview bar shows neither the typed colour **nor the saved one** — it is pinned to the system green
  for every operator on every install. Slice 0 stays first; its test should assert the *saved* colour
  paints the bar with JS off, in addition to the keystroke case.

---

## 2. MISCLASSIFIED

### M1 — #18 "Custom CSS disclosure" is `copy`, not `constraint`

The report's own action defeats its own classification: *"Reproduce the disclosure with pure CSS
`:has(input[name=custom_css_enabled]:checked)` — no JS, survives JS-off."* If a verbatim visual copy
is possible with no JS, no CSP conflict, no flag and no authz involvement, it is a `copy` difference
by the governing rules. No production constraint is named.

There **is** a real sub-difference hiding underneath, and it should be recorded separately as
`feature-changed`: `sc-if` removes the node from the tree, `:has()` only hides it, so a hidden
textarea still posts `custom_css` — and `BrandingController.php:155` still validates it when the flag
is on and the checkbox is off (`elseif ($customCssAvailable && $customCss !== '' && …)`).

### M2 — #66 "`--bp-primary` / `--bp-accent`" names no constraint

The row's own justification is *"`element.style.setProperty()` from an external script is **not**
CSP-governed and is already the production idiom"* — i.e. nothing prevents the design's names. Custom
property names are invisible in the render. Keeping `--preview-accent`/`--preview-accent-2` is fine,
but this is not a `constraint`; it is not really a difference at all and should be dropped from the
count.

### M3 — #42 "Themes error strip" is under-scoped (not `copy`/low/template-only)

Action: *"Anchor the activation/rollback error to its own form; drop the page-level strip"*, filed in
Slice 1 as `templates/admin/themes.php` only, risk low.

It cannot be done in the template. `ReauthGate::requirePassword()` throws
`ValidationException(['current_password' => 'Your current password is incorrect.'])`
(`src/Security/ReauthGate.php:43`), and both `AdminThemeController::activate()` (`:69`) and
`rollback()` (`:86`) hand `$e->errors` straight to `indexView()`. The key is identical for the
rollback form **and for every activate row**, and no origin is carried. Anchoring requires
`AdminThemeController` to pass the failing form's identity (e.g. the install id) into the view. The
slice must include the controller; risk is medium.

---

## 3. MISSED

### Mi1 — the design's entire admin chrome is a ten-area pill tier that conflicts with a locked IA (biggest miss)

`components/admin/AdminNav.jsx:8-19` defines `ADMIN_AREAS` as ten **flat** areas in console order:

> Overview · Content · People · Members · Appearance · Notifications · Integrations · Packages ·
> Features · Settings

rendered as a sticky two-row block (`:51-74`): an identity row (`admin-bar-id`: mark ·
`admin-bar-exit` · `admin-bar-mode`) over `<nav className="admin-tier" aria-label="Admin areas">` in
the **pill** register — "the same idiom the forum topbar uses for primary nav — so it never reads as
a second copy of a page's own underline sub-tabs" (`:36-38`).

Production is an **eight-group vertical rail** (`templates/admin/_nav.php:7-50`): Dashboard ·
Moderation · Content · People · Appearance · Notifications · Integrations · Settings, with a mobile
drawer (`:52-59`, `:92`). The design has **no Moderation area** and adds Overview, Members, Packages,
Features as top-level peers.

This is the single largest structural difference on the screen and the report does not mention it at
all. Classification: **constraint** — ADR 0023 §Shipped item 6 ("Console IA per ADMIN §9.2: grouped
admin nav (Dashboard · Moderation · Content · People · Appearance · Notifications · Integrations ·
Settings)") is a shipped, binding decision; adopting the design's IA is a new ADR, not a slice. But
it must be *recorded* — a silent omission reads as agreement.

Related: the report's #6 ("do not add a duplicate local tab strip") is still correct and is
**strengthened** by this, since the design now separates the two ranks explicitly (pills for areas,
underline tabs for a page's own sections).

### Mi2 — the design moves Custom emoji from Appearance to Features

Design: `AdminFeatures.dc.html:33-34` — the `Custom emoji` tab lives on the Features screen.
Production: `_nav.php:31` lists it under the **Appearance** group. An IA `feature-changed` the report
never records (it treats custom emoji purely as an Appearance-group orphan).

### Mi3 — the card boundary is wrong and must be dissolved

Design (`:41`): only the **form** is a card —
`padding: 20px 22px; background: var(--surface-raised); border: 1px solid var(--border-hair); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm)`.
The `<aside>` (`:96`) sits outside any card; the preview shell (`:99`) and the reset section (`:113`)
are separate surfaces.

Production (`branding.php:22`): `<section class="card brand-cols">` wraps **both** columns — the form
*and* `section.brand-preview`. The report covers the aside width (#27) and the reset placement (#29)
but never notes that the outer card has to go. `copy`. Note `AppImladrisFidelityTest:97-98` pins the
strings `brand-cols` and `brand-preview`, so the class names must survive the restructure.

### Mi4 — "Marks" is itself a two-column grid

`:73` — `display: grid; grid-template-columns: 1fr 1fr; gap: 12px 18px`. Production renders four
full-width stacked `.field`s (`branding.php:60-75`). #14 describes the dashed row anatomy but not the
grid it sits in. `copy`.

### Mi5 — the safe-mode on-state sentence differs between the two production pages

`themes.php:21` — "Theme safe mode is on. The built-in system theme is being served."
`theme_safe_mode.php:22` — "Safe mode is on. The built-in system theme is being served."
Both render as `.field-error` (danger red). #45 cites only the first. Both need the design's sentence
and the review register.

### Mi6 — production is already inconsistent with *itself* on safe mode

#51 correctly notes `themeData():164` reads `$state['active_build_id']` directly, so the Active theme
card survives safe mode. It misses the other half: `themeData():156` calls
`previewBuildFor()`, which returns `null` under safe mode
(`ThemeStateService.php:94-96`), so the **Preview card blanks** while the Active theme card does not.
That is the sharper framing: production contradicts itself, and the design blanks both
(`x-dc:334-335` `hasActive: !!active && !s.safeMode`).

### Mi7 — production's contrast rejection exceeds what ADR 0009 authorises

#36 says "Keep; ADR 0009 requires contrast checks". ADR 0009
(`docs/adr/0009-advanced-theming-custom-css-policy.md:26-27`) actually says:

> "Accessibility checks must **warn** on color contrast regressions before saving token changes.
> **Warnings can be overridden** only by admins and are audited."

`BrandingController.php:130-135` hard-**blocks** with a 422 and provides no override path. Keeping it
is defensible, but the divergence from the ADR should be recorded before the design idiom is wrapped
around it.

### Mi8 — the rollback button label differs

Design `:150`: `Roll back to last-known-good`. Production `themes.php:49`: **`Roll back`**. #52 says
"adopt placement + label" without quoting production's actual string.

### Mi9 — the fiction moved, not vanished

F1 (`Imladris` wordmark) and F3 (`Back to the council`) are still live fiction, but now in
`components/admin/AdminNav.jsx:53` (`<span className="admin-bar-wordmark">Imladris</span>`) and `:44`
(`backLabel = 'Back to the council'`), not in `AdminAppearance.dc.html:25/:27`. Any future adopter of
`AdminNav` inherits both.

### Mi10 — reset button skin

Design `:120`: `background: var(--rust); color: var(--parchment-50)`. Production `branding.php:124`:
`<button class="btn danger">`. #29 covers the card's rust left-rule but not the button.

---

## 4. What survives verification (opened and confirmed)

The production side of this report is unusually accurate. Every one of these was opened:

* **Routes** — `App.php:2148` `/brand.css`; `:2152-2153` branding; `:2235-2241` themes (index,
  safe-mode GET/POST, preview/clear, rollback, `{id}/preview`, `{id}/activate`); `:2324-2327` custom
  emoji. **No `deactivate` route exists** — #53's `feature-removed` is correct.
* **Flags** — `FeatureFlags.php:47` `branding => true`, `:48` `custom_css => false`, `:66`
  `custom_emoji => true`, `:84` `package_themes => true`. All four exactly as stated.
* **`BrandingController`** — `:92-96` reset 422 (and it does re-read `formData()` from settings,
  discarding the sibling form — #35 confirmed); `:114`, `:120`, `:125`, `:128`, `:130-135`, `:151`,
  `:155`, `:166`, `:194-200`, `:216`, `:261-266`, `:283-291`, `:316-337`. Every citation correct.
* **`AdminThemeController`** — `:138` `forced_safe_mode` from `theme.safe_mode`; `:164`
  `$state['active_build_id']`; `:206` `policyMessage()` returns `"{$e->code}: {$e->getMessage()}"`.
* **`ThemeStateService`** — `:59-64` password required only on exit; `:79-81` `activeBuild()` nulls in
  safe mode; `:92-99` `previewBuildFor()` resolves only serveable builds (so #64's "`not built` is
  unreachable" is correct); `:203` `deactivate()`.
* **`PackageThemeRepository:159`** — `WHERE p.type = 'theme' AND ip.state IN ('installed','enabled','disabled')`.
  Three states, exactly as #56 claims.
* **`App.php:567`** — `!empty($features['package_themes']) && $request->path() !== '/admin/themes/safe-mode'`.
  The recovery page really is excluded from package-theme serving.
* **`ReauthGate.php:43`** — `'Your current password is incorrect.'`
* **`_nav.php:5`** `Disabled until the feature flag is enabled`; `:28-32` the Appearance group.
* **CSS** — `.eyebrow` `:37-43` (`.72rem`, `--text-muted`, `--tracking-caps`, and
  `--tracking-caps: 0.16em` at `imladris.css:234` — the "=.16em" gloss is right); `.pill` `:99`;
  `.pill-admin` `:106`; both `.brand-preview-*` blocks (`:876-903`, `:3515-3565`); `app.js:145-147`.
* **`AppImladrisFidelityTest:97-98`** pins `brand-cols` / `brand-preview`. Correct.
* **Zero inline `style="` attributes anywhere under `templates/`** (`grep -rn 'style="' templates/ | wc -l` → `0`). #67 correct.
* **Fiction sweep** — `grep -rniE "council|the hall|warden|counsel|third age|imladris|mallorn|rivendell" templates/admin/`
  returns exactly one hit, `branding.php:20`. The report's "no other fiction found" claim holds.
* **`DESIGN.md:131` (§5.3)** — "every view has a real, shareable, crawlable URL rendered by the
  server." #4's constraint is properly grounded.
* **ADR 0023** — item 4 (anti-draft-loss incl. custom emoji + honest replace copy), item 5
  (`field_error`/`field_attrs`, table scopes/regions, `role="alert"`), item 6 (grouped IA). All three
  citations correct.
* **ADR 0009** — "Raw custom CSS stays behind a dark `custom_css` feature flag and an advanced
  confirmation" (#17 correct); the five rejection rules match `customCssError()` one-for-one.
* **Byte-identical strings** (#28, #46, #54, #57, #61, #63) — all six re-checked and correct.

No proposed action silently reverts an ADR 0021 or ADR 0023 deferral. ADR 0021 contains no
branding/theme deferral that these slices touch (its branding entries are *shipped* items:
"branding upload failures surfaced", "distinct branding reset").

---

## 5. Required corrections before this report is actionable

1. **Re-read the design file.** Re-derive every `(:NN)`; the offset is −12 for the current tree.
2. **Delete #1/#2/#3/#5**, and replace them with the `AdminNav` difference (Mi1). Reverse the
   eyebrow action in #2/#41 and Slice 1: production's `.eyebrow` on `branding.php:11` and
   `custom_emoji.php:12` should be **removed**, not added to `themes.php`.
3. **Reclassify #65 / rewrite Slice 5** against `AdminFeatures.dc.html:216-283`.
4. **Drop F2**; the eight-point star is shipped production chrome (`partials/topbar.php:11`).
5. **Fix #26**, reclassify #18 → `copy` and #66 → not-a-difference, re-scope #42 into the controller.
6. **Correct #24's line numbers** and widen the defect statement to "the saved colour never paints
   the bar either, because `--brand` is a static token `/brand.css` never emits."

Revised counts after these corrections: `copy` ≈ 32 (−3 head rows, +4 missed, +1 reclassified from
constraint), `feature-added` **9** (−1: custom emoji), `feature-removed` 1 (holds), `feature-changed`
**8** (+2: emoji IA, custom-CSS disclosure submission), `constraint` **13** (−2 head, −2
reclassified, +1 AdminNav IA, +1 recovery page).
