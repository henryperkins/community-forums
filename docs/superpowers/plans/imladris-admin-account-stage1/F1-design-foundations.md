# F1 — Design register + token plumbing (Stage 1 foundations)

Analyst scope: tokens, component vocabulary, microcopy register, asset-build mechanics, cascade
layering. Route verification, per-screen anatomy diffing and feature classification belong to the
other Stage-1 analysts; where I touch them it is only to keep Stage 2 from breaking a foundation.

Sources read in full:
`docs/design-system/imladris/README.md`, `RUNTIME_CONTRACT.md`, `LOCAL_RECONCILIATION.md`,
`production-contract.json`, `manifest.json`, `tokens/{colors,fonts,spacing,typography}.css`,
`styles.css`, `components.css`, `guidelines/*.card.html`,
`public/assets/imladris.css` (generated), `public/assets/app.css` (admin/settings/account regions),
`bin/build-imladris-assets.php`, `src/Support/ImladrisAssetBuilder.php`,
`config/imladris-runtime-baseline.json`, `tests/Unit/Core/ImladrisRuntimeAssetTest.php`,
`tests/Integration/Core/AppImladrisRuntimeTest.php`, `src/Security/SecurityHeaders.php`,
`templates/layout.php`, `templates/partials/settings_nav.php`, `templates/admin/_nav.php`.

Design screens analysed (style-stripped copies written to
`…/scratchpad/stage1/stripped/*.stripped.html` for reference):

| # | file | markup lines | x-dc starts |
|---|---|---|---|
| 1 | `docs/design-system/imladris/templates/account-settings/AccountSettings.dc.html` | 496 | :497 |
| 2 | `…/templates/admin-appearance/AdminAppearance.dc.html` | 246 | :247 |
| 3 | `…/templates/admin-content/AdminContent.dc.html` | 305 | :306 |
| 4 | `…/templates/admin-notifications/AdminNotifications.dc.html` | 279 | :280 |
| 5 | `…/templates/admin-overview/AdminOverview.dc.html` | 276 | :277 |
| 6 | `…/templates/admin-people/AdminPeople.dc.html` | 351 | :352 |
| 7 | `…/templates/admin-settings/AdminSettings.dc.html` | 216 | :217 |

---

## 0. Headline findings

**H1 — The scope grew under us. Four more admin screens landed mid-analysis.**
`docs/design-system/imladris/templates/` now also contains `admin-features/AdminFeatures.dc.html`,
`admin-integrations/AdminIntegrations.dc.html`, `admin-members/AdminMembers.dc.html`,
`admin-packages/AdminPackages.dc.html` (all four untracked in git — `?? docs/design-system/imladris/templates/admin-*/`,
mtimes 2026-08-03 20:21–20:23, i.e. *after* the 7 briefed screens at 06:01). A DesignSync pull is
evidently in flight. Stage 2 must confirm whether the brief is 7 screens or 11 before planning.
The four newcomers also drop the fictional topbar entirely (no "Imladris" wordmark, no
"Back to the council"), which suggests the design source is being de-fictionalised as it is authored.

**H2 — The "false negative" trap is real and total: 63 of the 65 `var(--…)` references in the 7
screens already exist in the generated `public/assets/imladris.css`, and 30 of those exist *only*
there — they are absent from `app.css`.** Auditing against `app.css` alone would produce 30 phantom
"missing token" findings (`--gold-500`, `--shadow-md`, `--radius-lg`, `--font-display`, …). See §1.2.

**H3 — Only three tokens are genuinely unresolvable, and only one is a real gap.**
`--bp-primary` / `--bp-accent` are screen-local custom properties declared inline on the branding
preview element (`AdminAppearance.dc.html:111`) and mutated by the x-dc script
(`:280-281`) — production already has the exact equivalents (`--preview-accent`,
`--preview-accent-2`, `--preview-accent-contrast`, `app.css:878-880`, driven by
`app.js:141-146`). `--gold-050` is a **true missing token**: used at
`admin-integrations/AdminIntegrations.dc.html:45` and `admin-members/AdminMembers.dc.html:437`,
defined nowhere in `tokens/colors.css`, `imladris.css` or `app.css`. Substitute `--gold-soft`.

**H4 — `app.css` is unlayered; `imladris.css` is fully layered. `app.css` therefore wins every
contested declaration, and 181 of the design system's 211 class names are contested.** Notably
`.card` in `app.css:159` (`--surface`, `--border`, `--radius` = 7px, no shadow) beats
`.card` in `@layer imladris.components` (`--surface-raised`, `--border-hair`, `--radius-lg` = 12px,
`--shadow-xs`). The design system's own card anatomy is currently dead in production except for
`box-shadow`, which app.css does not contest. See §5.

**H5 — Live foundations defect: the twilight staff pair does not flip under the default theme.**
`imladris.css`'s `[data-theme="dark"]` block defines 45 tokens; `app.css`'s `[data-theme="dark"]`
and `@media (prefers-color-scheme: dark) { [data-theme="system"] }` blocks define 43 each, both
missing `--surface-staff` and `--on-staff` (`app.css:788-871`). `templates/layout.php:4` defaults
`theme` to `system`, and `layout.php:19` stamps `data-theme="system"` — under which
`imladris.css`'s `[data-theme="dark"]` selector never matches. Result: `.badge-staff` renders the
light-register `--gold-100` ground on a twilight page for every user on the default theme. This is
exactly the class of bug `LOCAL_RECONCILIATION.md` claims to have fixed; the fix only landed in the
`data-theme="dark"` path. `test_status_ledger_pairs_are_defined_in_both_colour_registers` does not
catch it because it only inspects `imladris.css`.

**H6 — Live foundations defect: the operator branding preview no longer tracks typed colours.**
`app.css` declares `.brand-preview-*` **twice** — once at `:876-903` (painting from
`--preview-accent` / `--preview-accent-2`) and again at `:3521-3565` (painting from `--brand` and
`--accent-2`). Later, same-specificity, unlayered → the second block wins for
`.brand-preview-bar { background }` (`:3535`) and `.brand-preview-accent { border-left }` (`:3562`).
`app.js:141-143` still sets the `--preview-*` properties, and `.brand-preview-body a` /
`.btn` (`:897-898`, uncontested) still read them, so the preview is half-live: link + button
respond to typing, the bar and the accent marker are frozen. The design requires all four to
respond (`AdminAppearance.dc.html:112,117,118,119`).

**H7 — CSP forbids inline `style` attributes, and production has zero of them today**
(`grep -rn 'style="' templates/ --include=*.php` → 0 hits).
`SecurityHeaders::csp()` emits `style-src 'self'` with no `style-src-attr`, so style attributes are
blocked. Every one of the ~1,500 inline styles in the 7 screens must become an external class. This
is the per-screen CONSTRAINT deviation named in the brief. **However**, CSP does not govern the
CSSOM: `element.style.setProperty()` from an external script is allowed and is already the
production idiom (`app.js:141`). That is the only sanctioned mechanism for the branding preview's
`--bp-*` behaviour and for anything else that must set a value at runtime.

**H8 — The design screens do not use the spacing scale at all.** Zero `var(--space-N)` references
across all 11 screens; every gap/padding/margin is a literal px. They also never use
`--radius-pill`, writing `999px` instead (51 occurrences). Stage 2 CSS should transcribe the design's
literal px values verbatim (they are the ground truth for "spacing must match") rather than
"improving" them onto the 4px scale — but should use `--radius-pill` where the design writes `999px`,
since the value is identical and the token exists.

---

## 1. Token inventory

### 1.1 What the screens are entitled to use

`RUNTIME_CONTRACT.md:14` is unambiguous: *"Consume **semantic tokens** … never raw primitives, so
the register flips for free."* The full entitled surface, from
`docs/design-system/imladris/tokens/colors.css` + `spacing.css` + `typography.css`, is:

**Surfaces / lines** — `--surface-page`, `--surface-raised`, `--surface-sunken`, `--surface-cool`,
`--surface-inverse`, `--border-hair`, `--border-soft`, `--border-strong`, `--rule-gold`.

**Brand / accent** — `--brand`, `--brand-hover`, `--brand-press`, `--brand-subtle`,
`--on-brand-subtle`, `--gold`, `--gold-soft`, `--gold-ink`.

**Ink** — `--text`, `--text-strong`, `--text-body`, `--text-muted`, `--text-faint`, `--text-inverse`.
(`--text-body` is a **colour**; the size is `--text-size-body`. `ImladrisAssetBuilder::validateCssSource()`
throws if that collision is ever reintroduced — `src/Support/ImladrisAssetBuilder.php:241-243`.)

**Status ledger (colour + a word, never colour alone)** —
`--success` / `--surface-done` / `--on-done`; `--warning` / `--surface-review` / `--on-review`;
`--pending` / `--surface-pending` / `--on-pending`; `--info` / `--surface-info` / `--on-info`;
`--surface-staff` / `--on-staff`; plus `--star`, `--artifact-link`, `--presence`, `--danger`.

**Legacy aliases still live** — `--surface`, `--surface-2`, `--surface-3`, `--border`, `--accent`,
`--accent-contrast`, `--accent-2`.

**Shape / depth / motion** — `--radius-sm|md|lg|xl|pill`, `--radius`,
`--shadow-xs|sm|md|lg|xl|inset`, `--gilt`, `--ease-calm`, `--dur-fast|base|slow`, `--focus-ring`.

**Space / layout** — `--space-1|2|3|4|5|6|8|12`, `--maxw`, `--sidebar-w`, `--list-w`, `--topbar-h`.

**Type** — `--font-display|label|body|mono`, `--font`, `--weight-regular|medium|semibold|bold`,
`--tracking-caps` (.16em), `--tracking-wide` (.04em), `--text-display-xl|lg|md|sm`, `--text-title`,
`--text-thread`, `--text-size-body`, `--text-sm`, `--text-meta`, `--text-eyebrow`, `--text-chip`.

### 1.2 What the 7 screens actually reference, and where it lives

65 distinct `var(--…)` references. **63 are already defined in the generated
`public/assets/imladris.css`.** Breakdown:

**Group A — present in `imladris.css` AND in `app.css` (33).** Safe everywhere; note that where
`app.css` "redefines" one it is only inside `[data-theme="dark"]` / `[data-theme="system"]`
twilight blocks, with identical values.
`--accent`, `--accent-contrast`, `--border-hair`, `--border-soft`, `--border-strong`, `--brand`,
`--brand-subtle`, `--danger`, `--focus-ring`, `--gold-ink`, `--gold-soft`, `--info`,
`--on-brand-subtle`, `--on-done`, `--on-info`, `--on-pending`, `--on-review`, `--presence`,
`--star`, `--success`, `--surface-done`, `--surface-info`, `--surface-page`, `--surface-pending`,
`--surface-raised`, `--surface-review`, `--surface-sunken`, `--text`, `--text-body`, `--text-faint`,
`--text-muted`, `--text-strong`, `--warning`.

**Group B — present ONLY in `imladris.css` (30). THE FALSE-NEGATIVE TRAP.** These are live in
production today via the generated layer; do **not** re-declare them in `app.css`.
`--amber`, `--artifact-link`, `--ease-calm`, `--font-body`, `--font-display`, `--font-label`,
`--font-mono`, `--gold-100`, `--gold-200`, `--gold-400`, `--gold-500`, `--gold-600`, `--gold-700`,
`--green-200`, `--green-500`, `--green-600`, `--green-800`, `--ink-900`, `--parchment-50`,
`--parchment-100`, `--radius-sm`, `--radius-md`, `--radius-lg`, `--river-200`, `--rust`,
`--shadow-xs`, `--shadow-sm`, `--shadow-md`, `--shadow-lg`, `--shadow-inset`.

**Group C — absent from the generated CSS (2, both screen-local, not design tokens).**

| token | where used | what it is | production equivalent |
|---|---|---|---|
| `--bp-primary` | `AdminAppearance.dc.html:111` (declared), `:112,117,118` (read), `:280` (JS write) | inline-declared local for the branding live preview | `--preview-accent` (`app.css:878`), set via `app.js:139` |
| `--bp-accent` | `AdminAppearance.dc.html:111` (declared), `:119` (read), `:281` (JS write) | ditto, accent marker | `--preview-accent-2` (`app.css:880`), set via `app.js:141` |

Neither needs a new token. Both are already implemented in production, but see **H6** — the
production wiring is half-broken and must be repaired for the design's live-preview behaviour to
hold.

### 1.3 Extending the audit to all 11 admin/account screens

Re-running the diff over the four new screens surfaces exactly one additional token:

| token | where used | status | proposed production token |
|---|---|---|---|
| `--gold-050` | `admin-integrations/AdminIntegrations.dc.html:45`, `admin-members/AdminMembers.dc.html:437` | **MISSING everywhere.** The gold ramp in `tokens/colors.css:52-58` runs `--gold-800 … --gold-100`; there is no `-050` step. `grep -c gold-050` → 0 in both `imladris.css` and `app.css`. | **`--gold-soft`** (`= --gold-100` light, `rgba(194,154,68,.16)` twilight). Both uses are a saved-confirmation callout ground paired with `border: 1px solid var(--gold-200); border-left: 3px solid var(--gold-500)`, which is precisely the `--gold-soft` role. |

If `var(--gold-050)` is ever transcribed literally into `app.css`, `ImladrisRuntimeAssetTest::test_every_required_runtime_variable_has_a_definition`
will fail — it scans `imladris.css` + `app.css` for every `var(--x)` without a fallback and asserts
each is defined.

### 1.4 Primitive usage inside the screens — a twilight hazard

The screens themselves break the "semantic tokens only" rule 322 times. Occurrences across all 11:

```
--gold-500 ×174   --rust ×46   --gold-100 ×23   --gold-200 ×22   --gold-400 ×20
--gold-600 ×12    --green-200 ×8   --gold-700 ×8   --amber ×6   --parchment-50 ×5
--leaf ×4   --river-200 ×3   --green-800 ×3   --ink-300 ×2   --gold-050 ×2
--parchment-100 ×1   --ink-900 ×1   --green-600 ×1   --green-500 ×1
```

Numbered primitives **do not flip** in the twilight register — `[data-theme="dark"]` remaps only the
semantic aliases. This is the documented root cause of the `.badge-staff` defect described in
`LOCAL_RECONCILIATION.md`. Stage 2 substitution rules:

| design primitive | keep as-is when… | substitute when it is a *ground* or *ink* |
|---|---|---|
| `--gold-500` | it is a 1–3px **rule / border / marker** (rules read correctly in both registers) | as a fill: `--gold` (semantic alias of the same value) |
| `--gold-100` as a background | never | `--gold-soft` |
| `--gold-700` as ink on gold ground | never | `--on-staff` (badge) or `--gold-ink` (small text) |
| `--gold-200` as a border | acceptable — the design uses it as a hairline on gold plates | — |
| `--rust`, `--amber`, `--leaf` | as a 3px status left-rule (`--rust` is the design's danger rule) | as ink/ground: `--danger`, `--warning`, `--success` + `--surface-*` pair |
| `--parchment-50` as ink on a coloured bar | never | `--accent-contrast` |
| `--ink-900` on a gold chip | never | `--on-staff` |
| `--green-200` as a border | acceptable (matches `components.css` `.chip-solved`) | — |

The 21 `color-mix()` expressions must be copied verbatim; they are already the production idiom
(`app.css:2963`, `:3576-3583`):

```
color-mix(in srgb, var(--gold-100) 60%, transparent)      ×19   (gold plate wash)
color-mix(in srgb, var(--rust) 9%, var(--surface-raised)) ×12   (danger row wash)
color-mix(in srgb, var(--surface-raised) 92%, transparent) ×7   (topbar translucency)
color-mix(in srgb, var(--rust) 12%, var(--surface-raised)) ×4   (danger chip ground)
color-mix(in srgb, var(--rust) 10%, var(--surface-raised)) ×2
color-mix(in srgb, var(--surface-raised) 94%, transparent) ×1
```
The `--gold-100` wash at 60% is itself a twilight hazard for the same reason as above — prefer
`color-mix(in srgb, var(--gold-soft) 60%, transparent)` unless a visual diff shows a light-register
regression.

---

## 2. Component vocabulary

### 2.1 A structural warning before the table

**The 7 screens contain ZERO `class=` / `className=` attributes.**
`grep -oE 'class(Name)?="[^"]*"'` returns nothing for any of them. There is no class vocabulary to
copy; every anatomy is expressed as an inline style string, and the `x-import
component-from-global-scope="ImladrisDesignSystem_c3e027.Button|Switch|Monogram"` calls in
`AccountSettings.dc.html` are React preview imports (`components/core/Button.jsx` etc.), not markup.

The named vocabulary therefore has to be recovered from `components.css`, which is where the design
system's class-based anatomies live — and `components.css` is the design-side file that **is** in
the builder's allowlist (`ImladrisAssetBuilder::CSS_SOURCES`, line 24). Everything under
`templates/` and `ui_kits/` is explicitly excluded (`ImladrisAssetBuilder.php:215-222`).

### 2.2 Design anatomy → design-system class → production class

`DS class` = the name in `docs/design-system/imladris/components.css` (shipping in
`@layer imladris.components`). `Prod class` = the name already in `public/assets/app.css`.
`W` = who currently wins in the browser (see §5).

| # | Anatomy (as it appears in the screens) | DS class | Prod class | W | Delta to resolve in Stage 2 |
|---|---|---|---|---|---|
| 1 | Console frame — centred column, `max-width 1100–1160px`, `padding 26px 28px 110px` | — | `.admin` (`app.css:2800`, 2-col grid, 1260px, `24px 28px 64px`), `.settings-screen` (`:2602`, 1000px, `26px 28px 64px`) | prod | **Structural.** Design is a single centred column with a *horizontal tab* subnav; production is a 224px sticky vertical grouped rail. **Do not demolish the rail** — ADR 0023 §6 locks "grouped admin nav (Dashboard · Moderation · Content · People · Appearance · Notifications · Integrations · Settings) per ADMIN §9.2", and `AdminOverview.dc.html:49` *itself* prints that exact group list as a caption, i.e. the design's tab bar is a per-screen elision of the locked IA, not a replacement for it. Treat as a scoping artefact for the other analysts; the max-width/padding numbers are plain copy differences. |
| 2 | Page head — eyebrow + `h1` + right-aligned "Admin mode" pill | — | `.admin-head` (`:2813`), `.admin-head h1 { 1.9rem }`, `.settings-head` (`:2608`) | prod | Design `h1` is `2.4rem` (AdminOverview) / `2.05rem` (section screens) `--font-display` 500; prod is `1.9rem` / `1.95rem`. Copy. |
| 3 | Eyebrow (lapidary caps over a heading) | `.eyebrow` (`components.css` via `typography.css:71`) | `.eyebrow` (`app.css:37`) | prod (identical values) | **Copy difference.** Both are `--font-label` / uppercase / `--tracking-caps` (.16em) — tracking matches. But size and colour do not: design uses `.64rem`(dashboard section) / `.66rem`(panel) / `.68rem`(page head, `.18em`) and **`color: var(--gold-ink)`**; `.eyebrow` is `.72rem` / `--text-muted`. Add a gold-ink admin/account eyebrow at `.66rem`. A `--danger`-inked variant also exists (`AccountSettings` danger zone). |
| 4 | Section header row — eyebrow+h2 on the left, live-dot / count / action on the right | — | `.section-heading-row` (`:2946`), `.status-legend` + `.status-legend i` (`:2955`,`:2963`) | prod | Design dot is `7px` `--presence`, no halo; prod is `7px` `--success` + `0 0 0 3px color-mix(--success 15%)`. Copy. |
| 5 | Intro paragraph under the head | — | `.pane-intro` (`:2936`, `--text-muted`, `max-width 64ch`) | prod | Design is `max-width: 66ch; font-size: 1rem; line-height: 1.55; --text-muted; text-wrap: pretty`. Copy (66ch vs 64ch). |
| 6 | Card / config panel (parchment, hairline, lg radius, xs–sm shadow) | `.card` (`components.css:82`) | `.card` (`app.css:159`) | **prod, and wrongly** | `.card` in app.css paints `--surface`/`--border`/`--radius`(7px)/no shadow and beats the DS `.card` (`--surface-raised`/`--border-hair`/`--radius-lg`/`--shadow-xs`). Screens use exactly the DS values (`background: var(--surface-raised); border: 1px solid var(--border-hair); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm)`). **Fix in `app.css`, not by deleting the layered rule** — deleting it would regress every other consumer of the layer. |
| 7 | Queue-health card (label / big count / detail / status caps, 3px status left-rule) | — | `.queue-card` + `-head/-count/-detail/-state` (`:2975-3028`), `.admin-dashboard-grid` (`:2970`) | prod | Design: `grid-template-columns: repeat(4, 1fr); gap 14px`; card `padding 16px 17px 14px; gap 3px; border-left: 3px solid <status>; --radius-lg; --shadow-sm`, count in **`--font-mono` 400 `1.9rem`**, head `.74rem/.05em` **not uppercase**, state `.66rem/.1em` uppercase. Prod: `auto-fit minmax(160px,1fr)/12px`, `min-height 168px`, count in `--font-display 2.1rem`, head uppercase `.68rem/.06em`, state `.65rem/.07em`, left-rule via `::before` with only three states (`success` / `attention`=rust / `unavailable`=faint) — **no amber "Waiting"**. All copy differences; the missing amber tier is the substantive one (`AdminOverview.dc.html:72,78` show two amber cards). |
| 8 | Attention/triage panel with gold left-rule + count pill | — | `.attention-panel` (`:3029`), `.attention-total` (`:3037`) | prod | Design count pill: `padding 2px 10px; 999px; --surface-sunken; --font-mono .82rem; --text-body`. Prod: `min-width 30px; height 30px; --gold-soft; --gold-ink`. Copy. |
| 9 | Activity/community-pulse card (title+detail left, big count right) | — | `.activity-card` + `-grid/-copy/-title/-count` (`:3047-3086`) | prod | Design count `--font-display 2rem` — matches prod. Grid: design `repeat(2,1fr) gap 12px`; prod identical. Near-parity. |
| 10 | Audit / delivery / role table | `.audit` is production-only | `.audit` (`app.css:608`, and `.admin .audit` `:3211`), `.table-scroll` (`:3217`), `.table-scroll-wide` (`:3236`), `.activity-table-shell` (`:3087`), `.table-scroll-cue` (`:3092`) | prod (DS has none) | Design `th`: `--font-label`, `font-weight 400`, `.66rem`, `letter-spacing .12em`, uppercase, **`--text-faint`**, `border-bottom: 1px solid var(--border-soft)`. Prod `th`: `.68rem`, `.04em`, **`--gold-ink`**, `border-bottom: 1.5px solid var(--border-strong)`, `weight medium`. Design `td`: `padding 10px`, `1px --border-hair`, `--text-body`; timestamps `--font-mono .78rem --text-faint`; targets `--font-mono .78rem` **`--artifact-link`**. Prod `td`: `padding 9px 10px`, matches otherwise, plus `tr:hover td { background: var(--surface-sunken) }` which the design does not have. All copy. **Keep `.table-scroll`** — it is production's mobile horizontal-scroll affordance and has no design counterpart. |
| 11 | Inline `<code>` in a table cell | `.audit code` | `.admin .audit code` (`:3270`) | prod | Design: `padding 1px 6px; --radius-sm; --surface-sunken; --font-mono .76rem; --text-body`. Prod: `.82em`, `--text-strong`, `border-radius: 4px`. Copy (4px ≡ `--radius-sm`). |
| 12 | Status chip / pill (word + colour) | `.badge`, `.chip`, `.chip-solved/-needs/-decision_made/-locked/-archived`, `.pill`, `.pill-admin`, `.pill-online`, `.tier*` (`components.css:44-79,327-337`) | `.badge*`, `.chip*`, `.pill*` all exist in `app.css`; `.tier*` does **not** | prod for badge/chip/pill; **DS wins for `.tier*`** | Design chip anatomy in these screens: `display:inline-flex; align-items:center; gap:6px; padding:2px 10px; border-radius:999px; --font-label; font-size:.7rem; letter-spacing:.05em` + a `--surface-*`/`--on-*` pair. A smaller table variant is `padding:1px 9px; font-size:.66rem; letter-spacing:.06em`. Both are **borderless**; `components.css .chip` and `app.css .chip` both carry a 1px border. Copy. |
| 13 | Role / state dot chip | — | `.state` + `.state-*` (`:3465-3502`), `.role-pill` / `.role-admin` / `.role-moderator` (`:3505-3514`) | prod (DS has none) | Design uses the surface/on pair chip (#12), not a leading dot. `.state-*` maps status→dot colour and already covers `sent/queued/failed/bounced/complained/suspended/banned/deactivated/active/paused/pending/revoked`. Keep the production mapping (behaviour), adopt the design's chip skin (presentation). |
| 14 | Stat-card row (count over caps label) | — | `.stat-cards` / `.stat-card` / `.stat-num` / `.stat-label` (`:3436-3464`) | prod (DS has none) | Design (`AdminNotifications` "Queue status", `AdminSettings` "Queue states"): count `--font-mono`, label `--font-label` caps. Prod `.stat-num` is `--font-display 1.7rem semibold`. Copy. |
| 15 | Meter / budget bar | **neither side has a class** | none | — | **NEW.** `AdminSettings.dc.html:133,140`: track `height:8px; border-radius:999px; background:var(--surface-sunken); overflow:hidden`, fill `height:100%; border-radius:999px; width:<pct>%`, `--accent` for calls / `--gold-500` for tokens; label row above is `--font-label .74rem/.05em --text-muted` + `--font-mono .84rem --text-body`, `margin-bottom:6px`. **Percentage width cannot be an inline style under CSP.** Options, in order of preference: (a) a real `<progress>` element styled by class — `app.css:1096-1099` already styles `.composer-upload-card progress` with `--radius-pill` track/value, so the idiom exists; (b) a `data-pct` bucket class set server-side. Do not use `style="width:…"`. |
| 16 | Filter bar (labelled inputs in a grid + apply/reset + result count) | — | `.filter-form` (`:3126`), `.filter-grid` (`:3129`, `auto-fit minmax(180px,1fr)`, `gap 12px 16px`, `align-items:end`) | prod (DS has none) | Design uses the same shape. Near-parity; check the result-label typography (`--font-label .76rem/.06em --text-faint`). |
| 17 | Pager | — | none named | — | **NEW.** `AdminOverview.dc.html:264-268`: `display:flex; align-items:center; justify-content:space-between; gap:14px; margin-top:18px`; buttons `padding:7px 15px; --radius-md; border:1.5px solid var(--border-soft); background:transparent; --text-body; --font-label .78rem/.04em`, hover `background: var(--surface-sunken)`; label `--font-label .76rem/.06em --text-faint`. This is the design's **secondary/ghost button**, matching `components.css .btn-secondary`/`.btn-ghost` in role but not in metrics. |
| 18 | Empty state (in-card) | `.empty { padding: 24px 0 }` is production-only | `.empty` (`app.css:157`), `.inbox-empty*` (`:1702-1704`), `.profile-panel-empty` (`:2251`) | prod | Design: `padding:40px 20px; text-align:center`; `h3` `--font-display 500 1.2rem --text-strong margin:0`; `p` `margin:6px 0 0; .93rem; --text-muted`; optional ghost button `margin-top:15px`. `.empty` in production is a bare `padding:24px 0` and does nothing else. Copy — production needs a real empty-state anatomy. |
| 19 | Error state (read failure) | — | none | — | **NEW.** `AdminOverview.dc.html:223-227`: `padding:30px 26px; text-align:center; --surface-raised; 1px --border-hair; border-left:3px solid var(--rust); --radius-lg`; `h2` `1.35rem --font-display 500`; `p` `max-width:48ch; .95rem/1.5; --text-muted; text-wrap:pretty`; retry = ghost button. |
| 20 | Loading skeleton | — | none | — | **CONSTRAINT — do not build.** `AdminOverview.dc.html:212-219` is six pulsing bars driven by `@keyframes adPulse` declared in a `<helmet><style>`. Server-rendered pages have no loading state, PE forbids client rendering, and the `<style>` block is CSP-illegal. Record as a constraint deviation; ship only the empty + error states. |
| 21 | Callout / alert banner (`role="alert"` / `role="status"`) | — | none named | — | **NEW.** Two skins: gold status (`background: var(--gold-050)` → **use `--gold-soft`**, `border:1px solid var(--gold-200)`, `border-left:3px solid var(--gold-500)`, `--radius-md`, `padding:13px 17px`) and rust alert (`color-mix(in srgb, var(--rust) 9%, var(--surface-raised))`, rust left-rule). |
| 22 | Toast / saved confirmation | — | none | — | `AccountSettings.dc.html:488-491`, `role="status"`. Fixed-position confirmation. Under PE this is a **flash message** — production already has `Flash`; render it server-side in the same skin. Do not build a JS toast. |
| 23 | Sticky unsaved-changes bar | — | none | — | `AccountSettings.dc.html:478-483`. **CONSTRAINT.** Requires JS dirty-tracking; production forms post on submit. Either (a) omit and record as feature-removed, or (b) build as pure decoration behind `has-js`. Do **not** let it become the only save affordance — that breaks the no-JS path. |
| 24 | Engraved scribe panel / field row (account console register) | `.scribe-panel`, `.scribe-panel-head`, `.field-row`, `.row-bullet`, `.row-input`, `.row-mark` (`components.css:237-263`) | same names, `app.css:2641-2699` | prod (values near-identical) | Production already carries the lapidary register. `AccountSettings` uses a plainer bordered-section skin than `.scribe-panel`; reconcile in Stage 2. |
| 25 | Text input / textarea / select | `.input`, `.textarea`, `.input-engraved`, `.textarea-engraved`, `.input-pill`, `.field`, `.field-label` (`components.css:153-181`) | `.input`, `.input-engraved`, `.textarea-engraved`, `.field` exist; **`.input-pill`, `.field-label`, `.textarea` (class) do not** | prod for the shared ones | Design field label in these screens: `--font-label .78–.82rem/.02em --text-muted; display:block; margin-bottom:5px` — matches `.admin .field > span:first-child` / `.settings-pane .field > span:first-child` (`app.css:3194`). Reuse that. |
| 26 | Switch (set-gem toggle) | `.switchline`, `.switch`, `.switch-text` (`components.css:184-201`) | same names in `app.css` | prod | `AccountSettings` imports `ImladrisDesignSystem_c3e027.Switch` 12×. Production must render `.switchline > input.switch + .switch-text` server-side with a real checkbox. |
| 27 | Choice card (theme / density pickers) | `.choice-card`, `-title`, `-desc`, `.theme-swatch`, `.sw-bg/-card/-accent`, `.swatch-parchment/-twilight/-system` (`components.css:204-228`) | all present (`app.css:2020-2033`) | prod | **Divergence to note:** the DS `.choice-card` uses `box-shadow: inset 0 0 0 1.5px` + a `::after` gold dot; production uses `border-color` + `inset 0 0 0 1px var(--accent)`. Also the DS swatch accents are gold (`--gold-500`/`--gold-400`), production's are green (`--green-700`/`--green-500`). The screens render the picker as `<button aria-pressed>`, which is **not PE-safe** — production's radio-in-label is correct; keep behaviour, adopt skin. |
| 28 | Checkbox in a fieldset (capability grid) | `.gem-check`, `.gem-radio`, `.gem-field`, `.gem-leaf/-river/-rust/-gold` (`components.css:266-297`) | `.gem-check`, `.gem-field`, `.gem-leaf/-river/-gold` present; **`.gem-radio`, `.gem-rust` DS-only** | mixed | `AdminPeople.dc.html:126-139` uses bare `<fieldset><legend>` + checkbox rows. `<fieldset>` styling has no design-system class on either side — **NEW**. |
| 29 | Segmented / text-tab filter (All · Failed only, Most used · A–Z) | `.segmented`, `.segmented-item`, `.text-tabs`, `.text-tab` (`components.css:300-322`) | **absent from `app.css`** | **DS wins — usable as-is** | These four classes are among the 30 that live only in the layered file, so they render exactly as the design system specifies. Best candidates for direct reuse. |
| 30 | Primary / secondary / ghost / danger button | `.btn`, `.btn-small`, `.btn-secondary`, `.btn-ghost`, `.btn-accent`, `.btn-danger`, `.btn-icon`, `.linkbtn` (`components.css:11-40`) | all except `.btn-danger` and `.btn-icon` | prod for the shared ones; **DS wins for `.btn-danger` / `.btn-icon`** | Screens' ghost button metrics (`padding:7–8px 15–18px; --radius-md; 1.5px --border-soft; transparent; --font-label .78–.8rem/.04em`) must be reconciled with `.btn-secondary`/`.btn-ghost`. |
| 31 | Monogram avatar | `.monogram`, `.mono-0…9`, `-sm/-lg/-xl`, `.monogram-gilt`, `.avatar-wrap`, `.presence-dot` (`components.css:93-123`) | present except `-sm/-lg/-xl` | prod; DS wins for the size modifiers | `AccountSettings.dc.html:437` imports `Monogram size="sm"` → `.monogram-sm` (28px) resolves from the layer. |
| 32 | Definition list (generation contract, merge impact) | `SpecTable` lives in `components/doc/` — **excluded from the runtime** (`ImladrisAssetBuilder.php:219`) | none | — | **NEW.** Plain `<dl>` in `AdminSettings.dc.html:162-166`, `AdminContent.dc.html:200-207`. Must be authored fresh in `app.css`; do not reach for `components/doc.css`, which is deliberately outside the closure and asserted absent by `ImladrisRuntimeAssetTest` (`assertStringNotContainsString('components/doc.css', $css)`). |
| 33 | Translucent sticky topbar | — | `.topbar` (production shell) | prod | `background: color-mix(in srgb, var(--surface-raised) 92%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border-hair); height 58px`. The screens each re-draw a *fictional* topbar with the Imladris wordmark and a "Back to the council" link — **do not port it**; production's real chrome already exists. |

### 2.3 The 30 design-system classes production never overrides

These render straight from `@layer imladris.components` and are free to use without touching
`app.css`:

`.btn-danger`, `.btn-icon`, `.field-label`, `.gem-radio`, `.gem-rust`, `.input-pill`,
`.monogram-sm`, `.monogram-lg`, `.monogram-xl`, `.participant-stack`, `.post-rep`, `.post-sign`,
`.reaction-glyph`, `.regard`, `.segmented`, `.segmented-item`, `.sign-handle`, `.sign-title`,
`.text-tab`, `.text-tabs`, `.textarea`, `.thread-author`, `.thread-byline-sep`,
`.thread-meta-author`, `.thread-snippet`, `.tier`, `.tier-legend`, `.tier-loremaster`,
`.tier-member`, `.tier-veteran`.

(`.tier-*` names are lexicon fiction — see §3.3. The *classes* are safe; the *labels* are not.)

---

## 3. Microcopy register — a checklist for a PHP template author

Distilled from `README.md` §"Content fundamentals" (lines 29-50),
`guidelines/voice.card.html`, `guidelines/vocabulary.card.html`, `guidelines/type-label.card.html`,
`guidelines/status.card.html`, and `RUNTIME_CONTRACT.md` §Emoji.

### 3.1 Rules

1. **Casing: sentence case everywhere.** Headings, buttons, chip-words, nav items, table headers as
   authored text. "Save registration mode", not "Save Registration Mode".
2. **The one uppercase is the lapidary cap.** `--font-label` (Marcellus) + `text-transform:
   uppercase` + `letter-spacing: var(--tracking-caps)` (.16em) is a **typographic device**, applied
   only to: eyebrows, table `<th>`, chip/state labels, stat labels, and small meta lines. It is never
   emphasis and never applies to a sentence. Never write a string in literal capitals in PHP — let
   CSS do it, so screen readers and copy edits stay sane.
3. **Person: second singular for the member, first plural for the community.** "You have unsaved
   changes." / "Everything the community knows about you". Never "Users can…" in member-facing copy.
   Admin copy addresses the operator as **you** and refers to members in the third person.
4. **Sentence style: elevated, plain, declarative, a little grave.** Semicolons and em-dashes are in
   register. Two-clause constructions are the house move: *"The record is complete; this slice of it
   is simply empty."* / *"Regard is earned, never granted."* / *"AI proposes; the council approves."*
5. **No exclamation marks. No hype. No startup-speak.** No "Oops", "Whoops", "Awesome", "Let's",
   "Nice!", "🎉". `voice.card.html` principles verbatim: *Warm, not chatty · Plain, not corporate ·
   Sentence case · You, the reader.*
6. **Empty states name the fact, then the remedy.** "Nothing matches these filters" + "The record is
   complete; this slice of it is simply empty." + a reset control. Never "No data".
7. **Error states distinguish failure from absence.** "The log could not be read" + "The audit trail
   is append-only and intact — this is a read failure, not a gap in the record."
8. **Status is a word plus a colour — never a colour alone, never an emoji.** Every chip carries a
   label (`Sent`, `Queued`, `Needs review`, `Waiting`, `Clear`, `Active`, `Revoked`, `Enabled`,
   `Disabled`, `Protected anchor`, `Custom`). `guidelines/status.card.html` fixes the pairs:
   Solved→`--surface-done`/`--on-done`, Needs answer→`--surface-review`/`--on-review`,
   Decision→`--brand-subtle`/`--green-800`, Info→`--surface-info`/`--on-info`,
   Danger→transparent ground, `--rust` ink, dashed `--rust` border.
9. **Emoji are prohibited in UI chrome.** `README.md:49` and `RUNTIME_CONTRACT.md:17`. The only
   sanctioned standalone glyph is **✦** (the commend star) where inline SVG is impractical. Authored
   content and the composer's emoji tooling are unaffected — this rule is about chrome only.
   Note the screens themselves violate it: `AccountSettings.dc.html:323-324` uses ★ / ☆ for the
   board favourite toggle. Replace with the Lucide `star` icon or `.star-marker`/✦.
10. **Icons are Lucide, stroke 1.75–2, round caps/joins.** Never invent a glyph. The eight-point
    house star and four-point commend star are fixed assets (`assets/elven-star.svg`,
    `assets/commend-star.svg`) — do not redraw.
11. **Numbers are mono and tabular.** `--font-mono` + `font-variant-numeric: tabular-nums` for
    counts, timestamps, digests, routes, ids.
12. **Ceremonial flourishes are allowed only in colophons and leaderboard footnotes** — never in
    functional UI, and never in admin. There is no place for one in these 7 screens.

### 3.2 What the register already looks like in production

The eyebrow vocabulary is *already adopted* and matches the design exactly — no change needed:
`templates/admin/dashboard.php:6` "Operator desk", `:20` "Live operations", `:41` "Triage",
`:66` "Community pulse", `:86` "Audit trail"; `templates/admin/audit.php:12` "Accountability";
`templates/admin/branding.php:11` "Operator desk", `:99` "Live preview";
`templates/account/*.php` "Account". Stage 2 should preserve these strings.

### 3.3 FICTION TERMS — every instance found in the 7 screens, with the production string

**Rule: none of the left column may ever reach a PHP template.** Each row is a CONSTRAINT deviation
(brief §3).

| Design string (verbatim) | Where | Production string |
|---|---|---|
| `Imladris` (wordmark in every topbar) | all 7 screens, ×~40 | *Do not port the topbar at all.* Production renders the operator's own site name from `$brand['name']` (`templates/layout.php:27`). |
| `Back to the council` | all 7 screens, topbar link | **`Back to the forum`** (or reuse whatever `templates/partials/topbar.php` already renders). |
| `Your seat at the council` | `AccountSettings` page-head eyebrow | **`Your account`** |
| `Everything the council knows about you, and everything it does on your behalf.` | `AccountSettings` intro | **`Everything this community knows about you, and everything it does on your behalf.`** |
| `Council` (rail group heading) | `AccountSettings` rail | **`Community`** |
| `Fields defined by the wardens` | `AccountSettings` profile panel | **`Fields defined by the operators`** |
| `The wardens choose which fields exist; you choose what goes in them.` | `AccountSettings` | **`Staff choose which fields exist; you choose what goes in them.`** |
| `A second factor keeps your seat at the council secure…` | `AccountSettings` 2FA | **`A second factor keeps your account secure even if your password is lost.`** |
| `Regard` (rail item + panel title) | `AccountSettings` ×20 | **`Reputation`** |
| `Commends` (ledger unit) | `AccountSettings`, `AdminOverview` | **`Reputation`** / **`Points`** (whatever `reputation_ledger` already prints) |
| `Regard is earned, never granted.` | `AccountSettings` ledger footnote | **`Reputation is earned, never granted.`** (keeps the cadence, drops the lexicon) |
| `Hidden — wardens only` | `AccountSettings` privacy select | **`Hidden — staff only`** |
| `You still earn regard; you just won't be ranked publicly.` | `AccountSettings` | **`You still earn reputation; you just won't be ranked publicly.`** |
| `Their public counsel stays readable — blocking is not moderation.` | `AccountSettings` blocks | **`Their public posts stay readable — blocking is not moderation.`** |
| `…but counsel and posting are blocked until you reactivate.` | `AccountSettings` lifecycle | **`…but replying and posting are blocked until you reactivate.`** |
| `Public counsel is preserved under a deleted-member identity` | `AccountSettings` delete | **`Public posts are preserved under a deleted-member identity`** |
| `Email me the weekly council summary` | `AccountSettings` notifications | **`Email me the weekly digest`** |
| `Saved to your seat.` | `AccountSettings` toast | **`Saved.`** |
| `Worthy of the council` | `AccountSettings` x-dc data | **`Worthy of note`** (or drop — it is sample data) |
| `Europe / Rivendell`, `Rivendell` | `AccountSettings` timezone/location sample | Real IANA zones (`Europe/London`). Sample data only. |
| `Erestor`, `erestor@imladris.council`, `Elrond`, `Glorfindel`, `Arwen`, `Galadriel`, `Lindir`, `Gildor` | all screens, sample rows | Neutral placeholders. Prefer `@example.com` addresses. |
| `Loremaster`, `Legend`, `Veteran`, `Member` (tier ladder), `.tier-loremaster` / `.tier-legend` | `AccountSettings`, `components.css:334-337` | Production's own badge/tier names. **The CSS class names may stay** (they are not user-visible); the **labels** must not. |
| `Marks of esteem`, `Grant or revoke a mark of esteem.` | `AdminPeople` x-dc | **`Badges`** / **`Grant or revoke a badge.`** |
| `The chrome the council wears.` | `AdminAppearance` intro | **`The chrome this community wears.`** |
| `Safe mode drops the council back to the built-in chrome…` | `AdminAppearance` themes intro | **`Safe mode drops the site back to the built-in chrome without uninstalling anything.`** |
| `No package theme is active. The council wears the built-in chrome.` | `AdminAppearance` | **`No package theme is active. The site uses the built-in chrome.`** |
| `…so safe mode can always bring the council home.` | `AdminAppearance` activate | **`…so safe mode can always restore it.`** |
| `Imladris Classic`, `rb.theme.imladris-classic`, `Mallorn`, `rb.theme.mallorn` | `AdminAppearance` x-dc | Sample package data. Use neutral names. |
| `Categories order the council's rooms; boards are the rooms themselves.` | `AdminContent` intro | **`Categories order the forum's rooms; boards are the rooms themselves.`** |
| `A council needs at least one room.` | `AdminContent` empty state | **`A forum needs at least one room. Add a category below, then put a board inside it.`** |
| `The warden's table. Staff only.` / `Queues, holds, and the warden's table.` / `Wardens` / `Tag warden` / `custom.tag_warden` | `AdminContent`, `AdminPeople`, `AdminOverview` x-dc | **`The moderation queue. Staff only.`** / **`Queues, holds, and moderation.`** / **`Moderators`** / **`Tag moderator`** / `custom.tag_moderator` |
| `Vocabulary and the council lexicon.` | `AdminContent` x-dc | **`Vocabulary and the tag catalogue.`** |
| `Wardens may now merge tags` | `AdminOverview` x-dc audit row | **`Moderators may now merge tags`** |
| `Counsel` / `counsel` (as a noun for replies) | `AdminContent` x-dc | **`Replies`** / **`replies`** |
| `…review what has changed across the council.` | `AdminOverview` intro | **`…review what has changed across the community.`** |
| `The name the council goes by` | `AdminSettings` identity | **`The name this community goes by`** |
| `The council needs a name.` | `AdminSettings` validation ×2 | **`The community needs a name.`** |
| `The council approves; the model proposes.` | `AdminSettings` Thread Intelligence intro | **`Staff approve; the model proposes.`** (preserves the chiasmus, drops the lexicon) |
| `council.imladris.example`, `*@imladris.example`, `Your weekly council digest`, `Confirm your seat at the council`, `The council stays readable throughout.` | `AdminNotifications` sample rows | Neutral sample data: `mail.example.com`, `*@example.com`, `Your weekly digest`, `Confirm your email address`, `The site stays readable throughout.` |
| `community.imladris` | `admin-packages` (new screen) | Neutral package namespace. |

**Also fiction, not caught by a lexicon grep:** the eight-point elven star SVG rendered in every
screen's topbar (`<path d="M50 3 63.8 16.7 …">`). It is the *design system's* house mark, not
RetroBoards branding. Production's mark comes from `$brand['logo_path']` / the favicon
(`templates/layout.php:37-40`). Do not hardcode the star.

---

## 4. Asset build mechanics — exactly how design CSS reaches production

### 4.1 The allowlist

`src/Support/ImladrisAssetBuilder.php:19-25` — the builder reads **five** files and nothing else:

```php
private const CSS_SOURCES = [
    'tokens/fonts.css',
    'tokens/colors.css',
    'tokens/typography.css',
    'tokens/spacing.css',
    'components.css',
];
```

all relative to `docs/design-system/imladris/`, plus every `*.woff2` / `*.txt` under
`docs/design-system/imladris/assets/fonts/` (recursively).

**Explicitly excluded** (recorded in the emitted `resources/imladris/manifest.json`,
`ImladrisAssetBuilder.php:215-222`): `_archive`, `_ds_bundle.js`, `components/doc.css`, `feature-ui`,
**`templates`**, **`ui_kits`**.

> **The consequence Stage 2 must internalise: nothing in `templates/*.dc.html` can ever become
> production CSS through the builder.** The screens are not a CSS source. Every rule derived from
> them is hand-authored, and there are only two places it can go:
> **(a) `docs/design-system/imladris/components.css`** — if it is a genuinely reusable design-system
> primitive; then rebuild so it ships in `@layer imladris.components`. But note it will then *lose*
> to any same-named rule in `app.css` (§5), and `components.css` is DesignSync-owned — see §4.6.
> **(b) `public/assets/app.css`** — unlayered, always wins, and is where every existing
> `admin-*` / `settings-*` / `queue-card` / `audit` rule already lives. **This is the right home for
> admin/account screen CSS.**

### 4.2 Outputs

`expectedFiles()` produces, and `build()` writes:

- `resources/imladris/tokens/{fonts,colors,typography,spacing}.css` and
  `resources/imladris/components.css` — verbatim copies of the sources.
- `resources/imladris/assets/fonts/**` and `public/assets/fonts/imladris/**` — byte-identical WOFF2
  + the OFL `LICENSES/`.
- `public/assets/imladris.css` — the concatenation, with a fixed header:
  ```
  /* Generated from the allowlisted Imladris runtime sources.
     Run `composer build:imladris`; do not edit this file directly. */
  @layer imladris.tokens, imladris.components;
  ```
  then one `/* Source: <relative> */` + `@layer imladris.tokens { … }` block per token file and
  `@layer imladris.components { … }` for `components.css`.
- `resources/imladris/manifest.json` — provenance: inspected commit, per-source SHA-256, font list,
  exclusions, runtime filters.

Anything under `resources/imladris/` or `public/assets/fonts/imladris/` that is *not* in
`expectedFiles()` is **deleted** on build (`removeUnexpectedFiles()`, `:385-396`). Do not park files
there.

### 4.3 Two source transforms and three source guards

Transforms (`runtimeCss()`, `:250-280`):
1. `tokens/fonts.css`: `../assets/fonts/` → `fonts/imladris/`.
2. `tokens/spacing.css`: the global `@media (prefers-reduced-motion: reduce)` block is **excised**
   and replaced with `/* Reduced-motion behavior remains application-owned at runtime. */`. If that
   exact block ever changes shape the builder throws
   `"tokens/spacing.css reduced-motion contract changed; reconcile the runtime filter."`
   (Production owns reduced-motion at `app.css:775-777`.)

Guards (`validateCssSource()` `:236-248`, plus `:275-277`):
1. No `http://` / `https://` anywhere in a source.
2. No `--text-body: <number>rem|px` — the colour/size collision must stay dead.
3. `tokens/typography.css` must contain `--text-size-body: 1.0625rem`.
4. **No `!important` in any generated output** — *"contains a runtime !important declaration that can
   invert layer priority."* This is deliberate: `!important` reverses the layer cascade and would let
   the layered file beat `app.css`.

### 4.4 Commands

```bash
composer build:imladris          # php bin/build-imladris-assets.php          → writes
composer check:imladris          # php bin/build-imladris-assets.php --check  → diff-only, exit 1 on drift
composer verify:imladris         # check:imladris  +  phpunit ImladrisRuntimeAssetTest AppImladrisRuntimeTest
php bin/build-imladris-assets.php --print-application-digest   # prints the current surface sha256
```
Current state, verified this session: `--check` → `Imladris runtime assets are current.`;
`--print-application-digest` → `f8a09441fadaef32a10332cf4c3fa51c6a694e72bd0a08c3ac6f3144bfe9249d`,
matching `config/imladris-runtime-baseline.json`.

### 4.5 THE TRIPWIRE — what happens the moment Stage 2 edits a template

`config/imladris-runtime-baseline.json` records a normalised SHA-256 over an
**application-owned presentation surface**:

```json
"application_surface": {
  "roots":      ["templates", "public/assets"],
  "files":      ["USER.md","ADMIN.md","COMMUNITY.md","COMPOSER.md","src/Core/FeatureFlags.php"],
  "extensions": ["php","css","js"],
  "excluded":   ["public/assets/imladris.css"],
  "sha256":     "f8a0…9d"
}
```

`digestApplicationSurface()` (`ImladrisAssetBuilder.php:315-375`) walks `templates/**` and
`public/assets/**` for `.php`/`.css`/`.js`, adds the five named files, sorts, and hashes
`path\0sha256(content)` per entry with LF-normalised content.

`expectedFiles()` compares it and **throws before writing anything**:

```
Production presentation changed after Imladris reconciliation. Review the design-system
contract before updating config/imladris-runtime-baseline.json. Current digest: <hex>
```

**Therefore: editing a single character of any `templates/*.php` or `public/assets/app.css` breaks
BOTH `composer build:imladris` AND `composer check:imladris` AND `composer verify:imladris`.**
This is not a warning — the exception is thrown from the shared `expectedFiles()`, so the build path
fails too.

**The Stage 2 recipe, in order:**
1. Make the template / `app.css` / `app.js` changes.
2. `php bin/build-imladris-assets.php --print-application-digest` → new hex.
3. Paste it into `config/imladris-runtime-baseline.json` → `application_surface.sha256`.
4. `composer check:imladris` → must print "current".
5. `composer verify:imladris` → runs the two test files.

`LOCAL_RECONCILIATION.md` calls step 3 *"an explicit design-contract review step, not an automatic
part of the asset build."* Treat it as a reviewed act, not a chore — and note it in the PR body.

Additional guards that fire on the *other* fields:
- `reconciled_through_commit` in the baseline must equal the one in
  `docs/design-system/imladris/production-contract.json` (`:133-136`).
- `composer_contract` must equal `production-contract.json → composer.spec` (`:137-139`).
- Every entry in `production-contract.json → surface_specs` must appear in
  `application_surface.files` (`:145-149`).
- `manifest.json → unresolved_gaps` and `production-contract.json → unresolved_gaps` must both be
  `[]` (`:109-118`).
- `ImladrisRuntimeAssetTest::test_reviewed_application_baseline_covers_…` pins
  `reconciled_through_commit` to the literal string `6d81da590a12bd09bb8d0e282c042aa03d755a94`.
  Bumping the reconciliation commit therefore also requires editing that test.

### 4.6 The four tests you can break, and how

| test (all in `tests/Unit/Core/ImladrisRuntimeAssetTest.php` unless noted) | breaks when |
|---|---|
| `test_checked_in_runtime_asset_matches_the_allowlisted_design_system_sources` | any drift between the five sources and `public/assets/imladris.css`; also asserts no `https?://`, no `!important`, no `components/doc.css`, no `_archive/`, no reduced-motion block. |
| `test_every_required_runtime_variable_has_a_definition` | you add `var(--x)` **without a fallback** to `app.css` (or `imladris.css`) where `--x` is nowhere declared. **This is the `--gold-050` landmine.** |
| `test_status_ledger_pairs_are_defined_in_both_colour_registers` | you remove a `--surface-*`/`--on-*` pair from either register of `imladris.css`. (Note: it does **not** check `app.css`'s `[data-theme="system"]` block — hence **H5**.) |
| `test_application_css_does_not_redeclare_design_system_foundations` | you add a `:root { … --parchment-50: … }` to `app.css`; also requires `app.css` to keep `font-size: var(--text-size-body)` and `background-image: var(--surface-texture, none)`. |
| `test_staff_badge_uses_the_flipping_semantic_pair_exactly_once` | `.badge-staff` appears more than once in `app.css`, or stops painting `--on-staff` on `--surface-staff`. |
| `test_application_quiet_thread_rows_reset_design_system_hover_motion` | `.thread-row:hover` in `app.css` loses `transform: none`. |
| `AppImladrisRuntimeTest::test_every_layout_loads_the_runtime_design_system_before_application_overrides` (integration) | `imladris.css` stops preceding `app.css` in `templates/layout.php`, or `_ds_bundle.js` appears in any response. |

### 4.7 Changing `components.css` — read this first

Per `MEMORY.md → imladris-package-pipeline`, the authoritative source of the design system is the
live Claude Design project, synced into the repo via **DesignSync (Workflow agents only — Agent
subagents cannot see the tool)**. `docs/design-system/imladris/components.css` is a *mirror*.
Hand-editing it will be clobbered by the next sync (and `LOCAL_RECONCILIATION.md` exists precisely
to document the deliberate local deltas). **Prefer `app.css` for all Stage 2 admin/account CSS.**
Only push a change into `components.css` when it is a real, cross-surface design-system primitive,
and record it in `LOCAL_RECONCILIATION.md` when you do.

---

## 5. Layering — where new admin CSS must live

### 5.1 The mechanism

`templates/layout.php:42-46`, in order:

```html
<link rel="stylesheet" href="/assets/imladris.css">   <!-- fully layered -->
<link rel="stylesheet" href="/assets/app.css">        <!-- fully unlayered -->
<link rel="stylesheet" href="/assets/wysiwyg-composer.css">   <!-- flag-gated -->
<link rel="stylesheet" href="/theme/<digest>.css">            <!-- package theme -->
<link rel="stylesheet" href="/brand.css?v=…">                 <!-- operator colours -->
```

`imladris.css` opens with `@layer imladris.tokens, imladris.components;` and wraps **100 %** of its
content in those two layers. `grep -n "@layer" public/assets/app.css` → **no matches**: `app.css` is
entirely unlayered.

In the CSS cascade, **unlayered normal declarations beat every layered normal declaration,
regardless of specificity or source order.** So:

> **`app.css` wins any declaration it contests. Full stop.** A `body .admin .card` selector inside
> `@layer imladris.components` still loses to a bare `.card` in `app.css`.

The resolution is **per-declaration, not per-rule**, which produces a subtle hybrid. Concrete,
verified example — `.card`:

| property | `@layer imladris.components` (`components.css:82`) | `app.css:159` (unlayered) | rendered |
|---|---|---|---|
| `background` | `var(--surface-raised)` | `var(--surface)` | **app.css** |
| `border` | `1px solid var(--border-hair)` | `1px solid var(--border)` | **app.css** |
| `border-radius` | `var(--radius-lg)` (12px) | `var(--radius)` (7px) | **app.css** |
| `padding` | `18px` | `18px` | app.css (same) |
| `box-shadow` | `var(--shadow-xs)` | *not declared* | **imladris.css** |
| `margin-bottom` / `max-width` / `overflow-x` | *not declared* | declared | app.css |

So production's card is a chimera: app.css geometry + the layer's shadow. Every Stage 2 "card"
mismatch is of this shape.

### 5.2 The overlap census

211 classes in `imladris.css`; 871 in `app.css`; **181 overlap**. The overlapping set (app.css wins
every contested property) is essentially the whole primitive library: `btn*`, `card`, `chip*`,
`badge*`, `pill*`, `input`, `switch*`, `gem-*`, `choice-card*`, `scribe-panel*`, `field-row`,
`monogram*`, `mono-0…9`, `thread-*`, `post-*`, the entire `composer-*` family, `eyebrow`, `hash`,
`muted`, `sr-only`, `theme-swatch`, `swatch-*`. Full list in the analysis; the 30-item
non-overlapping set is in §2.3.

### 5.3 The `!important` trap

`!important` **inverts** layer order: an important declaration in a layer beats an important
declaration in the unlayered "implicit outer layer". The builder therefore hard-refuses to emit
`!important` (`ImladrisAssetBuilder.php:275-277`). Do not add `!important` to `app.css` to "win" —
you already win; and do not add it to a design source, because the build will fail.

### 5.4 Recommendation for Stage 2

1. **All new admin/account CSS goes in `public/assets/app.css`, unlayered.** It is where
   `.admin*`, `.settings*`, `.queue-card`, `.activity-card`, `.stat-card`, `.audit`, `.state-*`,
   `.role-pill`, `.brand-preview-*`, `.filter-grid`, `.scribe-panel` already live, and it is the only
   file guaranteed to win.
2. **Never re-declare a token in `app.css`'s `:root`.** Read the layer. Adding
   `:root { --parchment-50: … }` fails `test_application_css_does_not_redeclare_design_system_foundations`.
3. **If you add a new semantic colour token, you must touch three places, not one:**
   `tokens/colors.css` `:root` → light; `tokens/colors.css` `[data-theme="dark"]` → twilight; **and
   `app.css`'s `@media (prefers-color-scheme: dark) { [data-theme="system"] { … } }` block
   (`app.css:836-871`)** — because `layout.php` defaults to `data-theme="system"` and
   `imladris.css`'s `[data-theme="dark"]` selector does not match it. Skipping the third gives you
   exactly bug **H5**. Ideally, fix H5 first by adding `--surface-staff` / `--on-staff` to both the
   `[data-theme="dark"]` (`:788`) and `[data-theme="system"]` (`:838`) blocks in `app.css`, and add a
   test that the three dark registers agree.
4. **Fix the duplicate `.brand-preview-*` blocks (H6) before touching `AdminAppearance`.** Delete the
   stale `app.css:876-903` block or merge it into `:3521-3565` so the later, winning rules read
   `--preview-accent` / `--preview-accent-2` again. Otherwise the design's live-preview behaviour
   cannot be demonstrated.
5. **Fix `.card` (or stop using it on these screens).** Either bring `app.css:159` onto the design's
   values (`--surface-raised` / `--border-hair` / `--radius-lg` / `--shadow-xs`) — which changes every
   other `.card` consumer and needs a browser-evidence pass — or introduce a scoped
   `.admin-pane .card` / `.settings-pane .card` override. The second is safer for a Stage-2 diff.
6. **Reuse `.segmented` / `.segmented-item` / `.text-tabs` / `.text-tab` from the layer as-is** for
   the screens' filter toggles (`AdminSettings` All/Failed only, `AdminContent` Most used/A–Z,
   `AdminPeople` All/Protected anchors/Custom, `AccountSettings` ledger filters). They are
   uncontested and already correct — but the screens render them as `<button aria-pressed>`, which
   is not PE-safe; render them as links or as a GET form's submit buttons.

---

## 6. Quick reference for Stage 2

- **Token gaps to close:** `--gold-050` → `--gold-soft` (2 uses, both in the *new* screens);
  `--bp-primary`/`--bp-accent` → `--preview-accent`/`--preview-accent-2` (already exist, wiring
  broken).
- **Do not "add" any of the 30 Group-B tokens.** They are live.
- **Every inline style must become a class in `app.css`.** No `style=` attributes; CSP blocks them
  and production currently has zero.
- **Runtime-varying values (meter width, preview colours) go through
  `element.style.setProperty()` in `public/assets/app.js`** — CSSOM writes are not CSP-governed and
  this is the established production pattern (`app.js:139-143`).
- **After any template/`app.css`/`app.js` edit:** re-run `--print-application-digest`, update
  `config/imladris-runtime-baseline.json`, then `composer verify:imladris`.
- **Grep the diff for fiction before committing:**
  `grep -rniE 'council|warden|counsel|regard|commend|esteem|imladris|rivendell|mallorn|loremaster|third age|elven|seat at the' templates/ public/assets/`
  should return nothing new.
