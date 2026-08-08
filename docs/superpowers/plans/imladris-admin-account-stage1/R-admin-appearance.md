# R — admin-appearance: correction addendum to D-admin-appearance.md

**This file supersedes `D-admin-appearance.md` wherever the two disagree, and folds in every finding
from `V-admin-appearance.md`. Read D for the rows it does not touch; read R for anchors, inversions,
strikes and counts.**

**Current design file (re-read in full, 2026-08-03):**
`C:/Users/htper/community-forums/docs/design-system/imladris/templates/admin-appearance/AdminAppearance.dc.html`

| Fact | D report claimed | Truth on disk |
|---|---|---|
| Total lines | 385 | **373** |
| Markup ends at | 246 | **234** (`</x-dc>`; screen `<div>` closes at 233) |
| `<script type="text/x-dc">` runs | 247–383 | **235–371** (`</body></html>` 372–373) |
| Inline `style="` attributes in markup | "~230" | **131** |
| `style-hover=` / `style-focus=` | "15" | **23** |

**Offset rule:** every design-side citation in D (markup *and* `x-dc`) is **exactly +12 too high**;
subtract 12. The four exceptions are the head rows D1–D4, whose markup was **deleted outright** — no
corrected anchor exists for them.

**What changed upstream** (mid-pass DesignSync refresh): the hand-rolled sticky 58px topbar (star SVG
+ `Imladris` wordmark + `Back to the council`), the `Operator desk · Appearance` eyebrow, and the
`Admin mode` pill were replaced by one line —

```html
<!-- AdminAppearance.dc.html:22 -->
<x-import component-from-global-scope="ImladrisDesignSystem_c3e027.AdminNav" area="appearance" hint-size="100%,101px"></x-import>
```

Page padding `26px 28px 110px` → **`22px 28px 110px`** (`:24`); h1 `2.4rem`/`margin: 7px 0 0` →
**`2.1rem`/`margin: 0`** (`:26`); sub-nav top margin `22px` → **`16px`** (`:28`). This screen's
sub-nav is unaffected by the AdminOverview trailing-span deletion — lines 28–33 contain exactly the
two tabs `Branding` | `Themes` and nothing else.

The removal is a **stated design decision**, not sync noise —
`docs/design-system/imladris/components/admin/admin.card.html:43`:

> "Measured against the pages it replaces, this chrome is 10px *shorter*: the redundant
> "Operator desk · Area" kicker is gone, the mode pill moved into the identity row, and the heading
> drops from 2.4rem to 2.1rem."

**There is no eyebrow anywhere on this screen's head.** The `<h1>` is the first child of the content
column, immediately after the `x-import`.

---

## a. Corrected section order + corrected line anchors

### a.1 Corrected top-to-bottom order (verbatim headings, current lines)

| # | Element | Verbatim string | Line |
|---|---|---|---|
| **A1** | **Shared chrome import** (replaces D1/D2/D4) | `<x-import … ImladrisDesignSystem_c3e027.AdminNav area="appearance">` | **22** |
| D3′ | Page h1 (first child of the column) | `Branding & themes` | **26** |
| D5′ | Local tab nav | `aria-label="Appearance sections"` → `Branding` \| `Themes` | **28–33** |
| D6′ | Branding intro | `The chrome the council wears. Colours are stored as hex and resolved into the theme; the preview beside the form updates as you type.` | **38** |
| D7′ | Branding form — field grid | `Site name` / `Primary colour (hex)` / `Accent colour (hex)` / `Default theme for signed-out visitors` / `Theme preset` | **42–70** |
| D8′ | h3 | `Marks` → `Logo` `(current set)`, `Favicon` `(current set)`, `Light theme logo`, `Dark theme logo` | **72–78** |
| D9′ | h3 | `Custom CSS` → `Enable custom CSS`; then (conditional) textarea + `I understand this CSS applies site-wide and can affect usability.` | **80–87** |
| D10′ | Save row | `Save branding` + `Saved. The chrome is live for everyone.` + alert | **89–93** |
| D11′ | Aside label | `Live preview` → shell + footnote `Light and dark logo variants are used when the resolved theme explicitly matches that variant; system theme falls back to the base logo.` | **96–111** |
| D12′ | Aside h2 (rust left-rule) | `Reset to defaults` → copy, `Type RESET to confirm`, `Reset to defaults` button, `Reset. The built-in chrome is back.` | **113–122** |
| D13′ | Themes intro | `Package themes are installed from the registry and built before they can serve. Safe mode drops the council back to the built-in chrome without uninstalling anything.` | **131** |
| D14′ | h2 | `Safe mode` + `{{ safeModeAction }}` | **133–142** |
| D15′ | h2 | `Active theme` + `Roll back to last-known-good` | **144–156** |
| D16′ | h2 | `Installed theme packages` (`Package` `Version` `State` `Latest build` `Actions`) | **158–199** |
| D17′ | h2 (warning left-rule, armed, `aaRise` 180ms) | `Activate {{ activateName }}?` + `Current password` + `Activate` / `Cancel` | **201–215** |
| D18′ | h2 | `Preview` | **217–228** |

**Deleted, no corrected anchor:** D1 (sticky topbar), D2 (`Operator desk · Appearance` eyebrow),
D4 (`Admin mode` pill).

### a.2 Anchor correction table — every design citation in D

**Markup (`:NN`)**

| D row | D cite | Corrected |
|---|---|---|
| #1 | `:24`, `:25`, `:27` | **deleted** — struck (see §b.1) |
| #2 | `:34` | **deleted** — inverted (see §b.2) |
| #3 | `:34` skin | **deleted** — re-anchor to `:98` (see §b.3) |
| #4 | `:35` | `:26` |
| #5 | `:37` | **deleted** — relocated to `components.css:334` / `AdminNav.jsx:58` (see §b.4) |
| #6 | `:40-45` | `:28-33` |
| #7, #24 premise | `:50` | `:38` |
| #8, #13 (themes intro) | `:143` | `:131` |
| #9 | `:54` | `:42` |
| #10 | `:56` | `:44` |
| #11 | `:60-65` | labels `:48`, `:52`; placeholders on the inputs `:49`, `:53` |
| #12 | `:61`, `:65` | `:49`, `:53` |
| #13 | `:84` | `:72` |
| #14, #15 | `:86-89` | `:74-77` |
| #16 | `:86-87` | `:74-75` |
| #17 | `:92-93` | `:80-81` |
| #18 | `:94-99` | `:82-87` |
| #19 | `:96` | `:84` |
| #21 | `:103-104` | `:91-92` |
| #22 | `:102` | `:90` |
| #23 | `:111` | `:99` |
| #25 | `:119` | `:107` |
| #26 | `:112` | `:100` |
| #27 | `:52`, `:108` | `:40`, `:96` |
| #28 | `:110`, `:122` | `:98`, `:110` |
| #29 | `:125` | `:113` |
| #30 | `:126` | `:114` |
| #31 | `:127` | `:115` |
| #32 | `:129` | `:117` |
| #33 | `:132` | `:120` |
| #34 | `:133` | `:121` |
| #41 | "eyebrow present" | **no eyebrow**; intro `:131` only — inverted (see §b.5) |
| #42 | `:225` | `:213` |
| #44 | `:152` | `:140` |
| #45 | `:150` | `:138` |
| #46 | `:149` | `:137` |
| #49 | `:160-161` | `:148-149` |
| #52 | `:162` | `:150` |
| #54 | `:175-179` | `:163-167` |
| #55 | `:184` | `:172` |
| #56 | `:187-188` | `:175-176` |
| #57 | `:192` | `:180` |
| #58 | `:197` | `:185` |
| #59 | `:213-227` | `:201-215` |
| #60 | `:216` | `:204` |
| #61 | `:201` | `:189` |
| #63 | `:233`, `:238`, `:234` | `:221`, `:226`, `:222` |
| #66 | `:111` | `:99` — row struck anyway (see §b.6) |
| #67 | `:13-17` | `:13-17` **unchanged** (the `<helmet>` sits above the deleted block) |
| #69 | `:37`, `:187-188`, `:119` | head pill **deleted**; `:175-176`, `:107` |
| F4 | `:50` | `:38` |
| F5 | `:143` | `:131` |
| F6 | `:166` | `:154` |
| F7 | `:216` | `:204` |
| F13 | `:61` | `:49` |

**`x-dc` script (`x-dc:NN`)**

| D cite | Corrected | Verified content |
|---|---|---|
| `:251-254` | **`:239-242`** | `SEED_INSTALLS` (Imladris Classic / Twilight Hall / Mallorn / Greyhavens) |
| `:260` | **`:248`** | `siteName: 'Imladris', colorPrimary: '#2F5D46', colorAccent: '#C29A44'` |
| `:277-286` | **`:265-274`** | `paintPreview()` |
| `:280-281` | **`:268-269`** | `el.style.setProperty('--bp-primary'…)` / `('--bp-accent'…)` |
| `:291` | **`:279`** | `'The council needs a name.'` |
| `:292` | **`:280`** | `'Primary colour must be a hex value, e.g. #2F5D46.'` |
| `:293` | **`:281`** | `'Accent colour must be a hex value, e.g. #C29A44.'` |
| `:294` | **`:282`** | `'Acknowledge that custom CSS applies site-wide before saving it.'` |
| `:330` | **`:318`** | `resetLocked: s.resetConfirm !== 'RESET'` |
| `:335` | **`:323`** | `siteName: 'RetroBoards'` (doReset) |
| `:343` | **`:331`** | `safeModeAction: s.safeMode ? 'Turn safe mode off' : 'Turn safe mode on'` |
| `:346-347` | **`:334-335`** | `hasActive: !!active && !s.safeMode` / `noActive` |
| `:351` | **`:339`** | `lkgPackage: 'rb.theme.imladris-classic 2.3.4'` |
| `:352` | **`:340`** | `deactivate: () => this.setState({ activeId: null })` |
| `:378` | **`:366`** | `previewDigest: preview ? (preview.digest \|\| 'not built') : ''` |

---

## b. Rows whose ACTION is now INVERTED

### b.1 — #1 "Topbar" — **STRUCK** (was `constraint`)

D described a per-screen sticky 58px topbar with an elven-star SVG, an `Imladris` wordmark and a
`Back to the council` link at `:24-27`. **None of that markup exists in the file.** The row's advice
("do not port") happens to still be right, but it describes nothing, and it is superseded by **A1**
below. Removed from the count. D's supporting production citations were also wrong (V R4):
`layout.php:27` is the `<title>` element, and `:37-40` is only the `<link rel="icon">` block — the
brand mark is `templates/partials/topbar.php:11`.

### b.2 — #2 "Page head eyebrow" — **ACTION REVERSED** (`copy`, holds)

| | |
|---|---|
| **Was** | "Add `· Appearance` on branding; add the whole eyebrow to themes and theme_safe_mode" |
| **Now** | **Delete** `templates/admin/branding.php:11` `<span class="eyebrow">Operator desk</span>` and `templates/admin/custom_emoji.php:12` `<span class="eyebrow">Appearance</span>`. Do **not** add an eyebrow to `themes.php` or `theme_safe_mode.php`. The `<h1>` becomes the first element of the head. |

The design deleted the kicker deliberately and documents why (`admin.card.html:43`). D's action would
have moved production *away* from the design on three files.

### b.3 — #3 "Eyebrow skin" — **RE-ANCHORED** (`copy`, holds)

| | |
|---|---|
| **Was** | Design head eyebrow is `.68rem` / `var(--gold-ink)` / `.18em`; `.eyebrow` (`app.css:37-43`) is `.72rem` / `--text-muted` / `--tracking-caps` — "needs one cross-screen decision" |
| **Now** | That head eyebrow is gone; the quoted `.68rem`/`.18em` spec is unverifiable (see §c.6). The **only** surviving eyebrow-register element on this screen is the aside label `Live preview` at **`:98`** — `var(--font-label)`, **`.64rem`**, **`.16em`**, uppercase, `var(--gold-ink)`. Production renders it with the same global `<p class="eyebrow">` (`branding.php:99`). Re-anchor the row to the preview label; do **not** repaint the global `.eyebrow` off a deleted element, and note that after §b.2 the class is preview-only on this screen. |

### b.4 — #5 "Head pill" — **RECLASSIFIED `copy` → `constraint`, ACTION REVERSED**

| | |
|---|---|
| **Was** | "Repaint `.pill-admin` to the review pair; keep the class name" |
| **Now** | `Admin mode` is **no longer a page element**. It moved into the shared AdminNav identity row: `AdminNav.jsx:45` `modeLabel = 'Admin mode'` → `:58` `<span className="admin-bar-mode">`, styled at `components.css:334` — `padding: 4px 12px`, `var(--radius-pill)`, `var(--surface-review)`, `var(--on-review)`, `var(--font-label)`, `.72rem`, `.08em`, **`text-transform: uppercase`** (a detail D omitted). Production's `.admin-head` pill (`branding.php:14`, `themes.php:9`) is per-page furniture the design has removed from the page. Adopting the AdminNav bar is blocked by **ADR 0023 item 6** (see A1), so the correct action for this migration is: **leave the pill where it is, do not restyle it as page furniture, and record the relocation as a chrome-level deviation for the AdminNav ADR.** |

### b.5 — #41 "Themes head" — **ACTION HALVED**

| | |
|---|---|
| **Was** | `themes.php:7-10` has no eyebrow and no `.pane-intro` → "Add both" |
| **Now** | Add **only** the `.pane-intro` (de-fictionalised `:131`). There is no eyebrow in the design to add. Same correction applies to `theme_safe_mode.php`. |

### b.6 — #66 "`--bp-primary` / `--bp-accent`" — **STRUCK** (was `constraint`)

V M2 holds: the row's own justification says nothing prevents the design's names —
`element.style.setProperty()` from an external script is not CSP-governed. Custom-property names are
invisible in the render. This is not a difference; removed from the count. (Anchor, if ever needed:
`:99` and `x-dc:268-269`.)

### b.7 — #69 "Literal `999px` radii" — **ONE SITE DELETED, DESIGN NOW USES THE TOKEN**

Cited three sites; the head pill (`:37`) is gone. Exactly **three** literal `999px` survive in the
markup: `:107` (accent marker), `:175` and `:176` (state pills). The design system itself now uses
`var(--radius-pill)` for the relocated mode pill (`components.css:334`), which strengthens the
row's action.

### b.8 — Slice 1 — **REWRITE**

D's Slice 1 said: *"`templates/admin/themes.php` (add eyebrow `Operator desk · Appearance`, add
`.pane-intro`, …); `templates/admin/theme_safe_mode.php` (eyebrow/intro, …)"*, with a test asserting
`eyebrow` renders on `/admin/themes`.

Corrected Slice 1:
* `themes.php` — add `.pane-intro`; **no eyebrow**. Rewrite the safe-mode on-state copy + register;
  move the error strip onto its form (**with the `AdminThemeController` change from V M3 — this is
  not template-only, risk medium**); add the LKG "not serving under safe mode" note.
* `theme_safe_mode.php` — add `.pane-intro`; **no eyebrow**; fix the ungrammatical env-override
  sentence; apply the same on-state sentence (V Mi5 — `:22` differs from `themes.php:21`).
* `branding.php` — de-fictionalise `:20` **and delete the `Operator desk` eyebrow at `:11`**.
* `custom_emoji.php` — **delete the `Appearance` eyebrow at `:12`**.
* Test: extend `AppImladrisFidelityTest` to assert `/admin/themes` renders `pane-intro`, and to
  assert **`assertDoesNotSee('class="eyebrow"')`** in `.admin-head` on `/admin/branding`,
  `/admin/themes`, `/admin/custom-emoji`. Keep the `assertDoesNotSeeText($res, 'council')` sweep.

---

## c. Fabricated / no-longer-present quoted strings

Each checked with a literal `grep -F` against the current file.

| # | D row | Quoted string | Grep result |
|---|---|---|---|
| c.1 | D2, #2, #3, Slice 1 | `Operator desk · Appearance` | **NO MATCH** — `Operator desk` appears nowhere in the file |
| c.2 | D4, #5, #69 | `Admin mode` (page head pill, `:37`) | **NO MATCH** in this file. Lives in `components/admin/AdminNav.jsx:45,58` |
| c.3 | D1, F3 | `Back to the council` (`:27`) | **NO MATCH** in this file. Lives in `AdminNav.jsx:44` |
| c.4 | D1, F1 | `Imladris` **wordmark** (`:25`) | **NO MATCH as a wordmark.** The only three `Imladris` hits are `:22` (x-import namespace), `:239` (`Imladris Classic` seed package) and `:248` (`siteName` seed). The wordmark is `AdminNav.jsx:53` |
| c.5 | D1, F2 | "Eight-point elven-star SVG (`:24`)" | **NO MATCH** — no `elven`, no `brand-star`, no star path data in the file. Doubly wrong: V R3 shows production **already ships** the eight-point star as its default mark (`templates/partials/topbar.php:11`), so F2's "not a RetroBoards mark" is false on both sides. **F2 is dropped entirely.** |
| c.6 | #3 | eyebrow skin `.68rem` / `.18em` / `--gold-ink` | **Unverifiable** — the element is deleted. The file's only `--gold-ink` is `:98` at `.64rem` / `.16em`. Treat `.68rem`/`.18em` as unsourced |
| c.7 | #67 | "~230 `style="…"` attributes, 15 `style-hover=`/`style-focus=`" | **Miscount.** Actual: **131** `style="` and **23** `style-hover=`/`style-focus=` in lines 1–234 |
| c.8 | #1 | `templates/layout.php:27` "renders `$brand['name']`"; `:37-40` "the operator favicon/logo" | Wrong on the production side (V R4): `:27` is `<title>`; `:37-40` is only `<link rel="icon">`; the logo is `partials/topbar.php:11` |

Everything else D quoted was re-verified present at the corrected anchor — including all four
`brandError` strings, `Turn safe mode on/off`, `not built`, the four fiction package names,
`The council needs a name.`, `RetroBoards` in `doReset`, and all six byte-identical *match* strings.

---

## d. V-report findings folded in

### d.1 Refutations already applied above
* **V R1** → §b.1–b.5, §b.8, §c.1–c.4.
* **V R3 / R4** → §c.5, §c.8. **F2 dropped.**
* **V R5** → **#26 description corrected.** Production does **not** currently render the computed
  contrast: `app.css:3537` `color: var(--parchment-50)` overrides `app.css:894`
  `var(--preview-accent-contrast)`, so production already ships the design's hardcoded ink and
  `app.js:146` is dead. The *recommendation* (restore the computed contrast — ADR 0009) stands;
  #26 and #24 no longer contradict each other.
* **V R6** → **#24 anchors and severity corrected.** The bar's background declaration is
  `app.css:3536` (`:3531` is the selector), overriding `app.css:893` (not `:892`);
  `.brand-preview-accent` `:3562` vs `:900` was right. Severity widens: the overriding value is
  `var(--brand)`, a **static token** (`app.css:813`) that `/brand.css` never emits
  (`BrandingController::css()` writes only `--accent`, `--brand-primary`, `--accent-contrast`,
  `--accent-2`, `--brand-accent`, `--brand-accent-contrast`). The preview bar therefore shows neither
  the typed colour **nor the saved one** — it is pinned to system green on every install. Slice 0's
  browser evidence must assert the **saved** colour paints the bar with JS off, in addition to the
  keystroke case.

### d.2 Reclassifications
* **V M1 — #18 `constraint` → `copy`.** A verbatim visual copy is achievable with pure CSS
  `:has(input[name=custom_css_enabled]:checked)` — no JS, no CSP conflict, no flag, no authz. No
  production constraint was ever named.
* **NEW #18b (`feature-changed`), from V M1.** `sc-if` removes the node; `:has()` only hides it, so a
  hidden textarea still posts `custom_css`, and `BrandingController.php:155` still validates it when
  the flag is on and the checkbox is off (`elseif ($customCssAvailable && $customCss !== '' && …)`).
  Record and decide explicitly.
* **V M2 — #66 struck** (§b.6).
* **V M3 — #42 re-scoped.** Not template-only and not low risk.
  `ReauthGate::requirePassword()` throws `ValidationException(['current_password' => …])`
  (`src/Security/ReauthGate.php:43`); `AdminThemeController::activate()` `:69` and `rollback()` `:86`
  both hand `$e->errors` straight to `indexView()` with an identical key and no origin. Anchoring the
  error requires the controller to carry the failing form's identity (install id). Classification
  stays `copy`; **risk medium; slice must include `AdminThemeController`.**
* **V R2 — #65 `feature-added` → `feature-changed`, Slice 5 rewritten.** Custom emoji **is** modelled,
  as the third sub-tab of `templates/admin-features/AdminFeatures.dc.html` (`:33-34`, panel
  `:216-283`). Slice 5 must be written against that spec, not invented. Concrete deltas it hands us:
  the missing second clause "Assets are served from the media root; nothing is uploaded here."
  (`AdminFeatures:219` vs `custom_emoji.php:21`); `Allow as a reaction` is gated behind `reactionsOn`
  in the design but always rendered in production (`:56-59`); `Enabled`/`Disabled` are pills in the
  design (`:271-272`) and bare text in production (`:80`); the production-only `Catalogue` h2 (`:65`)
  has no design counterpart. The design's `mallorn` / `Mallorn leaf` / `/emoji/mallorn.webp`
  placeholders are fiction — production's `party` / `Party` / `/emoji/party.webp` already handle it.

### d.3 Rows added from V's MISSED list

| ID | Row | Class | Note |
|---|---|---|---|
| **A1** | **AdminNav ten-area pill tier vs production's locked eight-group rail** (V Mi1). `AdminNav.jsx:8-19` `ADMIN_AREAS` = Overview · Content · People · Members · Appearance · Notifications · Integrations · Packages · Features · Settings, rendered as a sticky two-row block (`:51-74`): identity row (`admin-bar-id`) over `<nav className="admin-tier" aria-label="Admin areas">` in the **pill** register. Production is an eight-group **vertical** rail (`templates/admin/_nav.php:7-50`) — Dashboard · Moderation · Content · People · Appearance · Notifications · Integrations · Settings — with a mobile drawer. The design has **no Moderation area** and promotes Overview/Members/Packages/Features to top-level peers. | **constraint** | **ADR 0023 item 6** locks the grouped IA. Adopting the design's chrome is a new ADR, not a slice. Must be *recorded* — silent omission reads as agreement. This is the single largest structural difference on the screen and D never mentioned it. It also **strengthens** #6: the design now separates the two ranks explicitly (pills for areas, underline tabs for a page's own sections — `AdminNav.jsx:36-38`), so a duplicate local tab strip is doubly wrong. |
| **A2** | **The design moves Custom emoji from Appearance to Features.** `AdminFeatures.dc.html:33-34` vs `_nav.php:31`. | **feature-changed** | IA move; blocked by the same ADR 0023 item 6 discussion as A1. Record, do not act. |
| **A3** | **The outer card boundary must dissolve** (V Mi3). Design `:41`: only the **form** is a card (`padding: 20px 22px; background: var(--surface-raised); border: 1px solid var(--border-hair); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm)`). The `<aside>` `:96` sits outside any card; the preview shell `:99` and the reset section `:113` are separate surfaces. Production `branding.php:22` wraps **both** columns in `<section class="card brand-cols">`. | **copy** | `AppImladrisFidelityTest:97-98` pins the strings `brand-cols` and `brand-preview` — the class names must survive the restructure. |
| **A4** | **`Marks` is itself a two-column grid** (V Mi4). `:73` — `display: grid; grid-template-columns: 1fr 1fr; gap: 12px 18px`. Production renders four full-width stacked `.field`s (`branding.php:60-75`). #14 describes the dashed row but not the grid it sits in. | **copy** | |
| **A5** | *(folds into #45, no new row)* The safe-mode on-state sentence differs between the two production pages (V Mi5): `themes.php:21` "Theme safe mode is on. …" vs `theme_safe_mode.php:22` "Safe mode is on. …". Both render as `.field-error` (danger red). **Both** need the design's `:138` sentence and the review register. | — | scope widening on #45 |
| **A6** | **Production contradicts itself on safe mode** (V Mi6). #51 correctly notes `themeData():164` reads `$state['active_build_id']` directly, so the Active theme card survives safe mode — but `themeData():156` calls `previewBuildFor()`, which returns `null` under safe mode (`ThemeStateService.php:94-96`), so the **Preview card blanks while the Active theme card does not.** The design blanks both (`x-dc:334-335` `hasActive: !!active && !s.safeMode`). | **feature-changed** | Sharper framing than #51 alone. Pick one behaviour and apply it to both cards. |
| **A7** | *(note on #36, no new row)* **Production's contrast rejection exceeds ADR 0009** (V Mi7). ADR 0009 (`docs/adr/0009-…:26-27`) says checks must **warn** and "warnings can be overridden only by admins and are audited". `BrandingController.php:130-135` hard-**blocks** with a 422 and provides no override. Keeping it is defensible; the divergence must be recorded before the design idiom is wrapped around it. | — | ADR divergence, not a design deviation |
| **A8** | *(folds into #52, no new row)* **Rollback label** (V Mi8): design `:150` `Roll back to last-known-good`; production `themes.php:49` is **`Roll back`**. #52 said "adopt placement + label" without quoting production. | — | |
| **A9** | **Reset button skin** (V Mi10). Design `:120`: `background: var(--rust); color: var(--parchment-50)`. Production `branding.php:124`: `<button class="btn danger">`. #29 covers the card's rust left-rule but not the button. | **copy** | |
| — | **Fiction moved, not vanished** (V Mi9). F1 (`Imladris`) and F3 (`Back to the council`) are still live fiction, now at `AdminNav.jsx:53` and `:44`. Any future adopter of `AdminNav` inherits both, plus `modeLabel`. Re-anchor F1/F3 to the component file; keep them on the fiction ledger. | — | see §c.3–c.4 |

### d.4 Rows added from this re-read (missed by both D and V)

| ID | Row | Class |
|---|---|---|
| **A10** | **`Enable it from Packages first` link skin.** #61 marks the row a *match* — true for the **string**, false for the **skin**. Design `:189` paints it `var(--font-label)`, `.72rem`, `.03em`, `color: var(--artifact-link)` (a real token, `imladris.css:144` `--artifact-link: var(--river-500)`). Production `themes.php:87` is a bare `<a>`. The row keeps its *match* status on the string and gains this skin action. | **copy** |
| **A11** | **The armed panel is the screen's only motion.** `:202` carries `animation: aaRise 180ms var(--ease-calm) both` (keyframes `:16`). If #59 lands as the `<details>` disclosure (option b), the reveal must carry the same 180ms rise and must be guarded by `prefers-reduced-motion`. D's #67 noted the keyframe existed but never tied it to an element. | **copy** |
| **A12** | **Corrected head metrics — the numbers the chrome change produced.** Page padding `22px 28px 110px` (`:24`, was 26px top); h1 `2.1rem` / `line-height: 1.1` / `letter-spacing: -0.01em` / **`margin: 0`** (`:26`, was 2.4rem / `margin: 7px 0 0`); sub-nav `margin: 16px 0 0` + `gap: 2px` + `border-bottom: 1px solid var(--border-hair)` (`:28`, was 22px). These drive `.admin-head` / `.admin-pane` sizing once the eyebrow is deleted (§b.2). D's #70 covers spacing only generically. | **copy** |
| **A13** | *(match, not counted)* **`aria-live="polite"` on the preview section** — design `:97`, production `branding.php:98` `<section class="brand-preview" data-brand-preview aria-live="polite">`. Already matches; preserve it through the A3 restructure. | *match* |
| **A14** | *(scaffolding, not counted)* The `<helmet><style>` block `:13-17` mixes **prototype resets** (`body { margin: 0 }`, `html { scrollbar-gutter: stable }`) with one real screen rule (`@keyframes aaRise`). Production has `scrollbar-gutter: stable` only on `.thread-scroll` (`app.css:1880`). **Do not adopt the resets**; do not read `html { scrollbar-gutter: stable }` as a site-wide design decision from one prototype file. | *scaffolding* |

### d.5 Verified-correct, no change needed
V §4 opened and confirmed every production citation in D: routes (`App.php:2148`, `:2152-2153`,
`:2235-2241`, `:2324-2327` — **no `deactivate` route**, so **#53's `feature-removed` holds**), all
four flags, every `BrandingController` / `AdminThemeController` / `ThemeStateService` line,
`PackageThemeRepository:159` (three states), `App.php:567`, `ReauthGate.php:43`, `_nav.php`, the CSS
blocks, `AppImladrisFidelityTest:97-98`, zero inline `style="` under `templates/`, the fiction sweep
(exactly one hit, `branding.php:20`), `DESIGN.md:131` §5.3, ADR 0023 items 4/5/6, ADR 0009, and all
six byte-identical strings. No proposed action reverts an ADR 0021 or ADR 0023 deferral.

---

## e. Corrected classification counts

Arithmetic from D's stated base (64 classified rows: copy 32 · feature-added 10 · feature-removed 1 ·
feature-changed 6 · constraint 15).

| Movement | copy | feat-added | feat-removed | feat-changed | constraint |
|---|---|---|---|---|---|
| D baseline | 32 | 10 | 1 | 6 | 15 |
| #1 struck (§b.1) | | | | | −1 |
| #66 struck (§b.6) | | | | | −1 |
| #5 `copy` → `constraint` (§b.4) | −1 | | | | +1 |
| #18 `constraint` → `copy` (V M1) | +1 | | | | −1 |
| #65 `feature-added` → `feature-changed` (V R2) | | −1 | | +1 | |
| **new** #18b custom-CSS submission (V M1) | | | | +1 | |
| **new** A1 AdminNav IA (V Mi1) | | | | | +1 |
| **new** A2 emoji IA move (V Mi2) | | | | +1 | |
| **new** A3 card boundary (V Mi3) | +1 | | | | |
| **new** A4 Marks grid (V Mi4) | +1 | | | | |
| **new** A6 safe-mode self-contradiction (V Mi6) | | | | +1 | |
| **new** A9 reset button skin (V Mi10) | +1 | | | | |
| **new** A10 `--artifact-link` skin | +1 | | | | |
| **new** A11 `aaRise` disclosure motion | +1 | | | | |
| **new** A12 corrected head metrics | +1 | | | | |
| **Corrected total** | **38** | **9** | **1** | **10** | **14** |

**Corrected: 72 classified rows** — copy **38** · feature-added **9** · feature-removed **1** ·
feature-changed **10** · constraint **14**.

Plus **7 non-counted rows**: the six byte-identical *match* rows (#28, #46, #54, #57, #61 — string
only, see A10 — #63) and **A13**; and one *scaffolding* row **A14**. Total rows in the corrected
table: **79**.

Row bookkeeping: 64 − 2 struck (#1, #66) = 62; + 10 new counted rows (#18b, A1, A2, A3, A4, A6, A9,
A10, A11, A12) = **72**. A5, A7, A8 widen existing rows and add no count. F2 is dropped from the
fiction table, leaving **F1, F3–F13** (F1/F3 re-anchored to `AdminNav.jsx:53`/`:44`).

**Slice order is unchanged** (Slice 0 still first, and its evidence requirement is widened per V R6),
except: **Slice 1 rewritten** (§b.8), **Slice 4 must include `AdminThemeController`** (V M3), and
**Slice 5 rewritten against `AdminFeatures.dc.html:216-283`** (V R2). **Slice 6 stays deferred.** A
new deferral is added alongside it: the AdminNav chrome (A1, A2, and the mode-pill relocation from
§b.4) needs its own ADR against ADR 0023 item 6 — **do not adopt it in this migration.**
