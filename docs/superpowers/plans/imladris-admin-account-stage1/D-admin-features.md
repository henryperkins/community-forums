# Stage 1 diff — `admin-features` ("Admin — features & badges")

**Design source:** `C:/Users/htper/community-forums/docs/design-system/imladris/templates/admin-features/AdminFeatures.dc.html`
(492 lines; markup lines 1–290, `<script type="text/x-dc">` lines 291–490)

**Verdict on ownership (the screen was previously believed to have no design representation — it does, and it governs three production pages):**

| Design tab | Production route | Template | Controller |
|---|---|---|---|
| Feature flags | `GET /admin/features` (`src/Core/App.php:2303`) | `templates/admin/features.php` | `src/Controller/AdminFeatureController.php` |
| Badge rules | `GET/POST /admin/badge-rules` + `{id}/preview|enable|disable|backfill|revoke` (`src/Core/App.php:2315-2321`) | `templates/admin/badge_rules.php`, `templates/admin/badge_rule_preview.php` | `src/Controller/AdminBadgeRuleController.php` |
| Custom emoji | `GET/POST /admin/custom-emoji` + `{shortcode}/enable|disable` (`src/Core/App.php:2324-2327`) | `templates/admin/custom_emoji.php` | `src/Controller/AdminCustomEmojiController.php` |

This **confirms** the live `PRODUCTION.md` mapping of badge rules to `templates/admin-features`
(`docs/design-system/imladris/PRODUCTION.md:47`) and **extends** it: the same template also owns
custom emoji, which the mirror `README.md:114` summarises only as "features & badges".

The controller path given in the task brief (`src/Controllers/…`) does not exist; the real
namespace directory is `src/Controller/` (singular).

---

## 1. Answers to the three sensitive questions

### Does the design model **readiness**? — Yes, faithfully in structure; wrongly in content.

The design carries the readiness column verbatim: header `Readiness / next step`
(design:78), a status pill on `--surface-review`/`--on-review` plus a muted note paragraph
(design:96–99), and an em-dash `—` for rows with no readiness (design:100). Production is
structurally identical at `templates/admin/features.php:72-79`.

But the design's **status vocabulary and per-flag assignments are sample data that contradict
production, in the dangerous direction**:

| Flag | Design readiness (`AdminFeatures.dc.html`) | Production readiness (`AdminFeatureController.php`) |
|---|---|---|
| `custom_css` | `Missing admin operations` — *"No revert path in the console yet — enable only with a database rollback plan."* (:323) | **`Safety-blocked`** — *"Theme safe mode does not suppress /brand.css custom CSS, so the documented recovery path leaves broken CSS active."* (:80-84) |
| `link_previews` | `Safety-blocked` — *"Outbound fetch has no SSRF allowlist."* (:324) | **`Missing admin operations`** — reaffirmed by `docs/adr/0021-…:67-69` (:75-78) |
| `expanded_files` | `Safety-blocked` — *"Needs content scanning on the upload path."* (:325) | **`Missing user UI`** (:69-73) |

The design **downgrades `custom_css` from Safety-blocked to Missing admin operations**. Adopting
its readiness text would silently revert a binding decision and understate a live safety finding.
Production readiness data is authoritative and must not be touched by this adoption.

The design also invents a sixth status, **`Ready for acceptance`** (design:309, 310, 313, 314,
317, 322), which has no production equivalent — production's `READINESS` map holds exactly five
statuses and deliberately omits any "all clear" label (rows that are simply shipped render `—`).

### Does the design model **corrupt overrides**? — Yes, and the alert copy is a verbatim match.

Design:55 and `templates/admin/features.php:18` carry byte-identical copy:

> The `settings.features` value is not a JSON object, so all stored feature overrides are being
> ignored and code defaults are in effect. Rewrite it as a JSON object (see
> `docs/runbooks/operations.md` §2) to restore your overrides.

Production drives it from `FeatureFlags::overridesCorrupt()` (`src/Core/FeatureFlags.php:125-129`,
set in `load()` at :179-183). Only the presentation differs (`<p class="field-error">` vs the
design's `role="alert"` rust-bordered panel).

The design additionally carries a **"Recovery drill —" simulator strip** (design:49–52) whose
button toggles the corrupt state client-side (`toggleCorrupt`, design:393). That is prototype
scaffolding with no production counterpart and must not be built.

### Does the design model the **rollback path**? — Yes, as a free-text column; production computes it.

Design column header `Rollback / enablement note` (design:77) matches production
(`templates/admin/features.php:55`) exactly. Design rows carry hand-written notes; production
generates all four variants deterministically in `AdminFeatureController::rollbackNote()`
(:259-271). Production's generation is behaviour and wins.

### Flag defaults — does the design present a default-off flag as available?

**No.** Every design row with `def: false` renders `off` in Effective and `default-off` in Default
(design:400–403), and its rollback note is phrased as *"Enable to…"* / *"Reserved."*. No toggle
exists anywhere on the screen; the intro states *"there are intentionally no toggles here"*
(design:47). That matches production's read-only posture exactly.

However the design's flag **inventory is fiction**:

- Design "Declared: 24" (design:293). Production `FeatureFlags::DEFAULTS` declares **57** flags
  (`src/Core/FeatureFlags.php:26-104`).
- Design "20 default-on · 4 default-dark" (design:294), yet its own row data lists **six** `def:false`
  flags — internally inconsistent. Production: **50 default-on, 7 default-off** (`custom_css`,
  `link_previews`, `expanded_files`, `server_extensions`, `governance`, `service_principals`,
  `verified_links`).
- Design invents two flags that do not exist in `DEFAULTS`: **`federation`** (design:329) and
  **`analytics_export`** (design:330) — grepped, `NOT PRESENT` in `src/Core/FeatureFlags.php`.
- Design omits three real default-off flags: `governance`, `service_principals`, `verified_links`.
- Design places `group_dms` under the group heading **"Implemented, default-dark"** while its own
  row says `def: true` and *"Graduated to default-on (ADR 0022)"* — another internal contradiction.
  Production has `'group_dms' => true` at `src/Core/FeatureFlags.php:54`.

Conclusion: adopt the design's **table anatomy and chip presentation**; take **zero** flag rows,
group labels, counts, or readiness strings from it.

---

## 2. Section-order comparison

### Design order (top → bottom, verbatim headings/eyebrows)

| # | Design element | Verbatim string |
|---|---|---|
| D1 | `<x-import … AdminNav area="features">` (design:22) | — (shared bar; carries `Imladris` wordmark, `Back to the council`, `Admin mode`) |
| D2 | `<h1>` (design:26) | `Features &amp; badges` (renders "Features & badges") |
| D3 | `<nav aria-label="Capability sections">` tab strip (design:28–35) | `Feature flags` · `Badge rules` · `Custom emoji` |
| D4 | Flash banner `role="status"`, `sc-if flash` (design:37–42) | `{{ flashText }}` |
| D5 | `<!-- ═══ Feature flags ═══ -->` panel intro `<p>` (design:47) | *"A read-only view of the declared flags, their configured overrides in `settings.features`, and the effective runtime state. Readiness distinguishes rows that are not simply shipped. Enablement stays a deliberate `settings.features` write — there are intentionally no toggles here."* |
| D6 | Recovery-drill strip (design:49–52) | eyebrow `Recovery drill —` + button `{{ corruptLabel }}` |
| D7 | Corrupt alert `role="alert"`, `sc-if corrupt` (design:54–56) | *"The `settings.features` value is not a JSON object…"* |
| D8 | `<section aria-label="Feature flag summary">`, 4 tiles (design:58–66) | `Declared` · `Defaults` · `Effective` · `Overrides` |
| D9 | Per-group card, `<h2>{{ g.group }}</h2>` + table (design:68–108) | groups in order: `Core · default-on`, `Platform · P5 Gate A (ADR 0018)`, `Implemented, default-dark`, `Reserved · Gate B (no UI)`; cols `Flag`/`Effective`/`Default`/`Override`/`Rollback / enablement note`/`Readiness / next step` |
| D10 | `<h2>` card (design:111–129) | `Unknown overrides` + intro + cols `Key`/`Cast value`/`Raw value` |
| D11 | `<!-- ═══ Badge rules ═══ -->` left column `<h2>` (design:138–167) | `Create rule` + intro *"Rules award automatically once the metric crosses the threshold. Scope to a board to count only what happened there."* + fields `Badge`/`Rule`/`Threshold`/`Board scope` + `Create rule` |
| D12 | Right column `<h2>`, `sc-if noPreview` (design:171–191) | `Rules` + `✦` rows with `Preview` / `Backfill` / `{{ r.toggleLabel }}` / `Revoke awards` |
| D13 | Right column preview panel, `sc-if hasPreview` (design:195–210) | `Back to badge rules` (chevron) + `<h2>{{ previewBadge }}</h2>` + meta + lead + member rows + `No members would receive this badge.` |
| D14 | `<!-- ═══ Custom emoji ═══ -->` intro `<p>` (design:219) | *"Add approved static assets to the post renderer and optionally make them available as reactions. Assets are served from the media root; nothing is uploaded here."* |
| D15 | Left column `<h2>` (design:222–247) | `Add or replace emoji` + `Shortcode`/`Name`/`Asset path`/`MIME type` + Switch `Allow as a reaction` (`sc-if reactionsOn`) + `Save emoji` |
| D16 | Right column, **unheaded** table + empty state (design:250–282) | cols `Emoji`/`Name`/`Asset`/`Reactions`/`Status`/(visually-hidden)`Action`; empty `No custom emoji have been added yet.` |

### Production order (three pages)

**`templates/admin/features.php`**

| # | Element | Verbatim |
|---|---|---|
| P1 | `.admin-head` eyebrow + h1 + pill (:6-9) | eyebrow `Runtime controls`, h1 `Feature flags`, pill `Admin mode` |
| P2 | `admin/_nav` partial (:12) | grouped rail (`Dashboard`/`Moderation`/`Content`/`People`/`Appearance`/`Notifications`/`Integrations`/`Settings`) |
| P3 | `.pane-intro` (:15) | long paragraph enumerating all five readiness statuses + `src/Core/FeatureFlags.php` + `docs/runbooks/operations.md` §2 |
| P4 | `<p class="field-error">` corrupt (:17-19) | as above |
| P5 | `.admin-dashboard-grid` 4 tiles (:21-42) | `Declared`/`Defaults`/`Effective`/`Overrides` |
| P6 | per-group `<section class="card">` + `.table-scroll` + `table.audit.audit-flags` (:44-86) | groups `Phase 2 / Base`, `Phase 3 / Composer and Trust`, `Phase 4 Gate A`, `Phase 4 Carryover`, `Phase 5 Gate A`, `Phase 5 Gate B` (+ `Uncategorized`, controller:145) |
| P7 | `<h2>Unknown overrides</h2>` card (:88-109) | empty state `No undeclared keys are present in settings.features.` |

**`templates/admin/badge_rules.php`**: P8 header (h1 `Badge rules`, no eyebrow) → P9 `_nav` →
P10 `<h2>Create rule</h2>` form → P11 `<h2>Rules</h2>` + `No badge rules.` / `ul.link-list`.

**`templates/admin/badge_rule_preview.php`**: P12 header (h1 `Badge rule preview`) → P13 `_nav` →
P14 `<p><a>Back to badge rules</a></p>` → P15 `<h2>{badge_name}</h2>` + meta + `No users would
receive this badge.` / `ul.link-list`.

**`templates/admin/custom_emoji.php`**: P16 header (eyebrow `Appearance`, h1 `Custom emoji`) →
P17 `_nav` → P18 `.pane-intro` → P19 `<h2>Add or replace emoji</h2>` form →
P20 `<h2>Catalogue</h2>` + empty state / `table.audit`.

### Order verdict

Section **sequence within each panel already matches the design** (intro → corrupt → stats →
groups → unknown; create → list; intro → create → catalogue). The differences are (a) the three
panels live on three separately-headed pages instead of one tabbed area, (b) the design's flash
banner sits below the tab strip while production's sits in `templates/layout.php:61`, and (c) the
design has no "Catalogue" heading where production does.

---

## 3. Difference table

| # | Section | Classification | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 1 | Page identity | copy | one `<h1>Features &amp; badges</h1>`, no eyebrow (design:26) | three h1s: `Feature flags` (features.php:7), `Badge rules` (badge_rules.php:8), `Custom emoji` (custom_emoji.php:13); eyebrows `Runtime controls` (features.php:6) and `Appearance` (custom_emoji.php:12) | Collapse to one h1 "Features & badges" on all four templates; delete both eyebrows | low |
| 2 | Page identity | copy | no `Admin mode` pill in the page body (moved into `AdminNav`, design:22) | `<span class="pill pill-admin">Admin mode</span>` in every header (features.php:9, badge_rules.php:9, badge_rule_preview.php:9, custom_emoji.php:15) | Remove from page bodies once the shared admin bar carries it; **blocked on the AdminNav slice** | low |
| 3 | Navigation | copy | `<x-import … AdminNav area="features">` — flat 10-area tier (design:22) | `admin/_nav` grouped rail with `features` under **Settings**, `badge_rules` under **People**, `custom_emoji` under **Appearance** (`templates/admin/_nav.php:26,31,47`) | Re-home all three under one `features` area; owned by the shared AdminNav slice, not this one | medium |
| 4 | Tab strip | copy | `<nav aria-label="Capability sections">` with `Feature flags` / `Badge rules` / `Custom emoji` (design:28–35) | none — three unrelated pages | Add the tab strip to all four templates | low |
| 5 | Tab strip | constraint | `<button onClick="{{ goFlags }}">` (design:29–34) | n/a | Must be `<a href="/admin/features">`, `/admin/badge-rules`, `/admin/custom-emoji` — verified at `src/Core/App.php:2303,2315,2324`. `aria-current="page"` on the active tab (design already does this) | low |
| 6 | Tab strip | constraint | no feature-flag handling anywhere | `badge_rules` 404s when dark (`AdminBadgeRuleController.php:88-93`); `custom_emoji` 404s when dark (`AdminCustomEmojiController.php:66-73`) | Render dark tabs as the `_nav.php:81-84` disabled idiom (`aria-disabled="true"` + `Disabled until the feature flag is enabled`), never as live links | medium |
| 7 | Tab strip | copy | 3 tabs; the badge-rule **preview is a sub-state of the Badge rules tab**, not a fourth tab (design:170/194) | preview is its own page with its own h1 (badge_rule_preview.php:8) | Preview keeps its own route (PE) but renders the shared h1 + tab strip with `Badge rules` current | low |
| 8 | Flash | copy | in-page `role="status"` banner: check-circle SVG, `--surface-done` ground, `--green-200` border, `--success` 3px left rule, `afRise` 200ms (design:37–42), placed **below** the tab strip | `<div class="flash" role="status">` (`templates/partials/flash.php:3`) rendered at the top of `<main>` (`templates/layout.php:61`) | Restyle + reposition. Shared across every admin screen — coordinate, do not fork per template | medium |
| 9 | Flags intro | copy | short 3-sentence paragraph ending *"there are intentionally no toggles here."* (design:47) | long paragraph (features.php:15) | Adopt the design's sentence structure and length | low |
| 10 | Flags intro | feature-added | design drops the readiness legend, the `src/Core/FeatureFlags.php` pointer and the `docs/runbooks/operations.md` §2 pointer | features.php:15 enumerates all five statuses and both pointers | **Keep** the legend and pointers — operator documentation the design never modeled. Relocate below the tables in the design idiom rather than delete | medium |
| 11 | Recovery drill | feature-removed | `Recovery drill —` eyebrow + `Simulate a corrupt settings.features value` / `Restore valid overrides` toggle (design:49–52, 392–393) | no equivalent | **Do not build.** Prototype-only affordance; shipping it would give operators a fake control | low |
| 12 | Corrupt alert | copy | `role="alert"`, `color-mix(--rust 9%, --surface-raised)` ground, 3px `--rust` left rule, `--danger` ink, `--radius-md` (design:54–56). Copy is byte-identical to production | `<p class="field-error">` (features.php:17-19) | Restyle only; the string already matches verbatim | low |
| 13 | Corrupt state | copy | when corrupt, tiles read `Effective … code defaults in effect` and `Overrides 0 … all overrides ignored` (design:394–396) | tiles always read `N on · N off` / `N unknown override(s)` (features.php:35,40) | Add the two corrupt-mode detail strings; production already computes overrides=0 in this state | low |
| 14 | Stat tiles | copy | 4 tiles: uppercase `.64rem` head, `2rem` display count, `.86rem` detail; `--surface-raised` + `--shadow-xs` + `--radius-lg` (design:58–66) | `.card.queue-card.is-static` in `.admin-dashboard-grid` (features.php:21-42) | Restyle to the design's tile spec; head/count/detail semantics already match | low |
| 15 | Stat tiles | copy | `Declared`/`Defaults`/`Effective`/`Overrides` (design:293–296) | identical labels (features.php:23,28,33,38) and identical detail grammar incl. singular/plural `unknown override(s)` (features.php:40) | No change to data or labels | low |
| 16 | Flag groups | feature-changed | 4 posture-based groups: `Core · default-on`, `Platform · P5 Gate A (ADR 0018)`, `Implemented, default-dark`, `Reserved · Gate B (no UI)` (design:300,312,321,327) | 6 phase-based groups + `Uncategorized` (`AdminFeatureController.php:16-46,145`) | **Keep production's taxonomy.** The design's is sample data and self-contradicts (`group_dms` filed under "default-dark" while `def:true`). Adopt only the h2 typography/card treatment | high |
| 17 | Flag rows | feature-changed | 24 rows incl. **invented flags `federation` and `analytics_export`** (design:329-330); omits `governance`, `service_principals`, `verified_links` | 57 rows from `FeatureFlags::DEFAULTS` (`src/Core/FeatureFlags.php:26-104`) | **Take zero rows from the design.** Rows are enumerated from `DEFAULTS` | high |
| 18 | Flag table | copy | 6 columns in exact order `Flag`/`Effective`/`Default`/`Override`/`Rollback / enablement note`/`Readiness / next step`; uppercase `.64rem` `--font-label` heads on `--border-soft` (design:73–78) | identical 6 columns, identical header strings (features.php:51-56) | Restyle headers only | low |
| 19 | Flag cell | copy | `<code>` chip on `--surface-sunken`, `--radius-sm`, `.78rem` mono (design:83) | bare `<code>` (features.php:63) | Restyle | low |
| 20 | Flag cell | feature-added | no operations affordance | inline `<a href>Operations</a>` when the flag is effectively on (features.php:64-66; map at `AdminFeatureController.php:99-115`, gated at :250) | **Keep.** Style it in the design idiom (accent link after the code chip) | medium |
| 21 | Effective cell | copy | 6px dot (`--leaf` / `--ink-300`) + lowercase `on` / `off`, `.72rem` label font (design:85-86) | `<span class="state state-active">Effective on</span>` (features.php:68; strings at `AdminFeatureController.php:246`) | Adopt dot + `on`/`off` | low |
| 22 | Default cell | copy | mono `.78rem` muted `default-on` / `default-off` (design:88, 403) | `<span class="state">Default on/off</span>` (features.php:69; `AdminFeatureController.php:245`) | Adopt the design's mono lowercase form | low |
| 23 | Override cell | copy | gold pill `--gold-100`/`--gold-700` with `on`/`off`, or faint `none` (design:90-91) | `Override on` / `Override off` / `No override` with `state-active`/`state-paused`/`state-pending` (features.php:70; `AdminFeatureController.php:247-248`) | Adopt pill + shortened strings | low |
| 24 | Rollback cell | copy | `max-width: 34ch`, `.88rem`, `text-wrap: pretty` (design:93) | plain `<td>` (features.php:71) | Restyle; keep production's generated notes (`AdminFeatureController.php:259-271`) | low |
| 25 | Readiness cell | copy | `--surface-review` pill + `.84rem` muted note, `max-width: 30ch`; `—` when absent (design:95–101) | same structure, `state-*` classes, `&mdash;` (features.php:72-79) | Restyle only | low |
| 26 | Readiness data | feature-changed | design reclassifies `custom_css` → `Missing admin operations`, `link_previews` → `Safety-blocked`, `expanded_files` → `Safety-blocked` (design:323–325) | `custom_css` → `Safety-blocked` (`AdminFeatureController.php:80-84`); `link_previews` → `Missing admin operations` (:75-78, reaffirmed `docs/adr/0021-…:67-69`); `expanded_files` → `Missing user UI` (:69-73) | **Production wins outright.** Adopting the design would downgrade a live safety finding and revert ADR 0021 §7 | high |
| 27 | Readiness status | feature-removed | sixth status `Ready for acceptance` (design:309,310,313,314,317,322) | five statuses only; shipped rows render `—` | **Do not build.** No production concept of "ready for acceptance" | medium |
| 28 | Readiness note | feature-added | note is plain text | note carries an optional inline link (`readiness_href`/`readiness_link`), dropped when the target would 404 (features.php:75; `AdminFeatureController.php:192-224`) | **Keep**, styled as an accent link inside the design's note paragraph | medium |
| 29 | Flag table scroll | feature-added | bare `overflow-x: auto` on the section, `min-width: 820px` on the table (design:69,71) | `.table-scroll` with `tabindex="0" role="region" aria-label="… feature flags"` (features.php:47) | **Keep** production's keyboard-reachable scroll region; apply the design's card padding around it | medium |
| 30 | Unknown overrides | copy | `<h2>Unknown overrides</h2>` + intro (byte-identical to production) + 3 cols `Key`/`Cast value`/`Raw value` (design:111–128) | identical heading, identical intro, identical columns (features.php:89,93,96) | Restyle card only | low |
| 31 | Unknown overrides | feature-added | no empty state — the table renders headerless-but-empty | `No undeclared keys are present in settings.features.` (features.php:91) | **Keep** the empty state; give it the design's muted-italic empty treatment | low |
| 32 | Badge rules layout | copy | two-column grid `minmax(300px, 380px) 1fr`, 18px gap, `align-items: start` (design:136) | stacked full-width cards (badge_rules.php:14,55) | Adopt the grid | low |
| 33 | Create rule | copy | intro *"Rules award automatically once the metric crosses the threshold. Scope to a board to count only what happened there."* (design:139) | none | Add | low |
| 34 | Create rule | feature-changed | `Rule` select options are raw identifiers `post_count`/`thread_count`/`reputation`/`solved_count` (design:151); `Badge`/`Board scope` bind names/slugs (design:145,162) | human labels `Post count`/`Thread count`/`Reputation`/`Solved answers` (badge_rules.php:30); `badge_id`/`board_id` bind integer ids (badge_rules.php:22,46) | **Production wins on the value contract and the labels** (the design has no catalogue or board table). Adopt only the field chrome. Note the list row already shows the raw `rule_type` in both (design:372 vs badge_rules.php:65) | low |
| 35 | Create rule | copy | `<option value="">All boards</option>` (design:162) | identical (badge_rules.php:44) | No change | low |
| 36 | Create rule | feature-added | `<input type="number" min="1">`, no max (design:157) | `min="1" max="1000000"` matching `BadgeRuleService::create` (`src/Service/BadgeRuleService.php:50-52`) | **Keep** the max | low |
| 37 | Create rule errors | feature-added | one form-level `role="alert"` paragraph (design:140) | per-field `field_error()` + `field_attrs()` aria wiring (badge_rules.php:20,26,29,35,38,40,43,50) with `->errors`/`->old` round-trip at 422 (`AdminBadgeRuleController.php:38-46`) | **Keep** per-field errors (constraint 7). Add the design's form-level alert as a *summary* above the fields | medium |
| 38 | Create rule errors | feature-removed | duplicate guard: *"A rule for that badge and metric already exists on this scope."* (design:425) | no duplicate check exists in `BadgeRuleService::create` (`src/Service/BadgeRuleService.php:45-56`) | **Do not build in this pass.** Real gap — needs a service check + unique index. File separately | low |
| 39 | Rules list | copy | `<h2>Rules</h2>`; rows are flex lines with an `aria-hidden` `✦` star in `--star`, bold badge name, mono meta, Enabled/Disabled pill, spacer, right-aligned action cluster with 14px gaps (design:172–189) | `<h2>Rules</h2>` + `ul.link-list` with `.badge`/`.badge-muted` and `.linkbtn` forms (badge_rules.php:56,60-79) | Restyle to the design's row anatomy | low |
| 40 | Rules list | copy | action order `Preview` · `Backfill` · `{{ toggleLabel }}` · `Revoke awards`, with `Revoke awards` in `--danger` (design:183–186) | order `Preview` · `Enable`(if off) · `Backfill` · `Disable`(if on) · `Revoke awards` (badge_rules.php:67-75) | Reorder so the toggle sits between Backfill and Revoke; strings already match verbatim | low |
| 41 | Rules list | constraint | every action is a `<button onClick>` (design:183–186) | `Preview` is `<a href>` (GET, badge_rules.php:67); the four mutations are `<form method="post">` with `csrfField()` and `aria-label` (badge_rules.php:69-75) | **Keep production's mechanism.** Style the POST buttons to look like the design's link-buttons | low |
| 42 | Rules list | feature-removed | `backfillOn` prop can hide `Backfill` (design:184) | Backfill is always available (badge_rules.php:71) | **Do not build** a hide condition; the prop is a prototype knob with no production flag behind it | low |
| 43 | Rules list | feature-added | no empty state | `No badge rules.` (badge_rules.php:58) | **Keep**; apply the design's muted-italic empty treatment | low |
| 44 | Preview panel | copy | back affordance is a chevron + `Back to badge rules` in `--font-label` `.78rem` muted (design:196) | `<p><a href="/admin/badge-rules">Back to badge rules</a></p>` (badge_rule_preview.php:13) — string matches verbatim | Restyle; keep the `<a>` (PE) | low |
| 45 | Preview panel | copy | h2 = badge name, then mono meta line, both inside the right-hand card of the Badge rules grid (design:197-198) | h2 = badge name + `<p class="muted">` meta on a standalone page (badge_rule_preview.php:15-16) | Render inside the tab layout | low |
| 46 | Preview panel | copy | lead line `{N} member(s) meet this rule today.` (design:448) | `BadgeRuleService::preview()` returns `total` but `badge_rule_preview.php` never renders it | Render the lead using the already-computed `total` | low |
| 47 | Preview rows | copy | `Monogram` component + username in `--accent` + right-aligned mono metric (design:202–206) | plain `<a href="/admin/users/{id}">` + `<span class="muted">Metric: N</span>` (badge_rule_preview.php:23-24) | Add the monogram (`monogram_initials`/`monogram_class`, `src/Support/helpers.php:21,29`); right-align the metric | low |
| 48 | Preview rows | feature-added | username is non-interactive text | username links to the user record (badge_rule_preview.php:23) | **Keep** the link; paint it `--accent` per the design | low |
| 49 | Preview rows | copy | `Metric: ` + `toLocaleString()` → thousands separators (design:449) | `Metric: <?= (int) $user['metric'] ?>` (badge_rule_preview.php:24) | Use `number_format()` | low |
| 50 | Preview empty | copy | `No members would receive this badge.` (design:209) | `No users would receive this badge.` (badge_rule_preview.php:18) | Change `users` → `members` (matches the register used across `admin-members`) | low |
| 51 | Emoji intro | copy | adds a second sentence: *"Assets are served from the media root; nothing is uploaded here."* (design:219) | first sentence only (custom_emoji.php:21) — verbatim match | Append the second sentence. Accurate: `validStaticPath` accepts only `/emoji/*.png|webp` or `/media/{id}` (`src/Service/CustomEmojiService.php`) | low |
| 52 | Emoji layout | copy | two-column grid `minmax(300px, 380px) 1fr` (design:220) | `.custom-emoji-panel` wrapper (custom_emoji.php:23) | Align the grid to the design's track sizes | low |
| 53 | Emoji form | copy | `<h2>Add or replace emoji</h2>` + fields in order `Shortcode`/`Name`/`Asset path`/`MIME type` in a single column, then the reaction switch, then `Save emoji` (design:222–246) | identical heading and field order, but the four fields sit in a `.form-grid` (custom_emoji.php:25-60) | Adopt the design's single-column stack; heading and labels already match verbatim | low |
| 54 | Emoji form | constraint | `Switch` component with `onChange` (design:244) | `<label class="checkline"><input type="checkbox" name="allow_reactions" value="1">` (custom_emoji.php:56-59) | Must stay a form-submittable checkbox (PE); style it as the Imladris Switch | low |
| 55 | Emoji form | feature-removed | `reactionsOn` prop can hide the reaction switch (design:243) | always rendered | **Do not build** the conditional; no production flag maps to it | low |
| 56 | Emoji form | feature-changed | 3 validation strings (design:462–464): *"Shortcodes are 2–40 characters: letters, numbers, underscore, plus or hyphen."*, *"A display name is required."*, *"The asset path must be an absolute path ending in .webp or .png."* | 4 strings in `CustomEmojiService::create` — production requires **lowercase**, enforces the 80-char name cap, and accepts `/media/{id}` which the design's regex rejects | **Production wins on all four.** The design's strings misdescribe real validation | medium |
| 57 | Emoji form | feature-added | no MIME error | `Custom emoji must be PNG or WebP.` | **Keep** | low |
| 58 | Emoji form | feature-added | no per-field error rendering | per-field `field_attrs()` + `<span class="field-error" id="err-emoji-*">` with `emoji_old` round-trip at 422 (custom_emoji.php:31-53; `AdminCustomEmojiController.php:29-34`) — an ADR 0023 §4 landed remediation ("anti-draft-loss closures (custom emoji…)") | **Keep.** Reverting is a silent ADR revert | high |
| 59 | Emoji catalogue | copy | **no heading** — the table is the section (design:250) | `<h2 id="custom-emoji-catalogue-heading">Catalogue</h2>` (custom_emoji.php:65) | Remove the h2; move the accessible name onto the section as `aria-label="Custom emoji catalogue"` so nothing is lost | low |
| 60 | Emoji catalogue | copy | column order `Emoji`/`Name`/`Asset`/`Reactions`/`Status`/`Action`, with `Action` **visually hidden** (design:253–258) | identical order, `Action` visible (custom_emoji.php:71) | Visually hide the `Action` header | low |
| 61 | Emoji cell | feature-changed | 24px `--gold-100` chip showing a 2-letter initial (`shortcode.slice(0,2)`) + mono `:code:` (design:265-266, 476) | `<img src="{image_path}" alt=":{shortcode}:" width="24" height="24">` + `<code>:code:</code>` (custom_emoji.php:76) | **Keep the real image** (production behaviour); wrap it in the design's 24px `--radius-sm` chip frame. The design uses initials only because the prototype has no assets | low |
| 62 | Emoji status | copy | `Enabled` / `Disabled` pills (`--surface-done`/`--on-done` and `--surface-sunken`) (design:273-274) | plain text `Enabled`/`Disabled` (custom_emoji.php:80) | Adopt the pills; strings already match | low |
| 63 | Emoji reactions cell | copy | `Allowed` / `Post rendering only`, `.88rem` muted (design:477) | identical strings (custom_emoji.php:79) | Restyle only | low |
| 64 | Emoji action | copy | outlined pill button, `4px 12px`, `--radius-md`, `1.5px --border-soft`, `.72rem` label font, right-aligned cell (design:276) | `<button class="btn btn-small">` inside a POST form (custom_emoji.php:82-85) | Restyle; keep the form + `csrfField()` | low |
| 65 | Emoji empty | copy | `No custom emoji have been added yet.` centred, `40px 0` padding, italic (design:281) | identical string, `<p class="muted">` (custom_emoji.php:67) | Restyle only | low |
| 66 | Emoji table scroll | feature-added | bare `overflow-x: auto`, `min-width: 560px` (design:250-251) | `.table-scroll` with `tabindex="0" role="region" aria-label="Custom emoji catalogue"` (custom_emoji.php:69) | **Keep** the a11y region | low |
| 67 | Flash strings — badge rules | copy | `Rule created — {badge} awards at {rule} ≥ {n}.`, `{badge} rule disabled — no new awards will be made.`, `{badge} rule enabled.`, `Backfilled N award(s) for {badge}. Each grant is audited.`, `All {badge} awards made by this rule were revoked.` (design:430,438,441,442) | `Badge rule created.` (:48), `Badge rule enabled.` (:64), `Badge rule disabled.` (:71), `Badge rule backfilled N awards.` (:78), `Badge rule revoked N awards.` (:85) | Adopt the design's object-naming register — but **keep production's counts** on backfill/revoke (the design's revoke flash drops the count) | low |
| 68 | Flash strings — emoji toggle | copy | `:{code}: disabled — it will render as plain text.` / `:{code}: enabled.` (design:482) | `Custom emoji disabled.` / `Custom emoji enabled.` (`AdminCustomEmojiController.php:45,53`) | Adopt the design's strings; shortcode is escaped by `partials/flash.php:3` | low |
| 69 | Flash strings — emoji save | feature-changed | `:{code}: added to the catalogue.` / `:{code}: replaced.` (design:471) | `Custom emoji saved.` / `Custom emoji replaced — :{code}: already existed.` (`AdminCustomEmojiController.php:35-37`) | **Keep production's replace copy** — ADR 0023 §4 lists "honest emoji replace copy" as a landed remediation. Only the create branch may take the design's phrasing | high |
| 70 | Motion | copy | `@keyframes afRise` (opacity 0 → 1, translateY 6px → 0, 200ms ease-out) on the flash banner and the preview panel (design:16,38,195) | none | Add to `public/assets/app.css` (or the generated `imladris.css`) behind the existing reduced-motion guard | low |
| 71 | Styling mechanism | constraint | every rule is an inline `style="…"` plus `style-hover` / `style-focus` pseudo-attributes (design, throughout) | strict CSP `style-src 'self'`, no `'unsafe-inline'` | All of it becomes external CSS classes; the rendered result must match pixel-for-pixel | low |

---

## 4. Fiction strings

| # | Design string (path:line) | Proposed neutral production string |
|---|---|---|
| 1 | `Imladris` wordmark in the `AdminNav` bar (design:22, by reference) | the operator's configured brand name (`$brand['name']`, already the pattern at `templates/layout.php:69`) |
| 2 | `Back to the council` — `AdminNav` `backLabel` default (design:22, by reference) | `Back to the forums` |
| 3 | `Awaiting the council's sign-off on generated brief tone.` (design:309) | n/a — the whole `Ready for acceptance` status is design-only and is not being built (diff #27) |
| 4 | `Loremaster of Evals` — badge catalogue + rule sample (design:336, 341) | badge names come from the `badges` table via `BadgeRepository::all()`; no literal ships |
| 5 | Board scope options `evaluations`, `audit-trails`, `interpretability` (design:162) | board options come from `BoardRepository::allOrdered()`; no literal ships |
| 6 | Preview usernames `elrond`, `galadriel`, `erestor`, `glorfindel`, `arwen`, `lindir`, `mellon` and displays `Elrond`…`Mellon` (design:345–351) | real rows from `BadgeRuleService::preview()`; no literal ships |
| 7 | Shortcode placeholder `mallorn` (design:227) | keep production's `party` (custom_emoji.php:31) |
| 8 | Name placeholder `Mallorn leaf` (design:231) | keep production's `Party` (custom_emoji.php:36) |
| 9 | Asset path placeholder `/emoji/mallorn.webp` (design:235) | keep production's `/emoji/party.webp` (custom_emoji.php:41) |
| 10 | Emoji catalogue samples `mallorn` / `Mallorn leaf`, `vilya` / `Ring of air`, `forge` / `Forge fire`, `palantir` / `Palantír` (design:355–358) | real rows from `CustomEmojiService::catalogue()`; no literal ships |
| 11 | Flag `federation` (design:329) and `analytics_export` (design:330) | not fiction *lexicon* but fiction *data* — these flags do not exist in `FeatureFlags::DEFAULTS`; rows are enumerated from `DEFAULTS` only |

No other Imladris lexicon terms (`wardens`, `counsel`, `regard`, `commend`, `the hall`,
`Third Age`) appear in this screen.

---

## 5. State inventory

| Design state (verbatim) | Production equivalent | Verdict |
|---|---|---|
| Flash banner `role="status"`, `{{ flashText }}` (design:37-42) | `templates/partials/flash.php:3`, rendered `templates/layout.php:61` | present; presentation + placement gap |
| Corrupt alert: *"The `settings.features` value is not a JSON object, so all stored feature overrides are being ignored and code defaults are in effect. Rewrite it as a JSON object (see `docs/runbooks/operations.md` §2) to restore your overrides."* | `templates/admin/features.php:18` — **byte-identical** | present; presentation gap only |
| `Recovery drill —` / `Simulate a corrupt settings.features value` / `Restore valid overrides` (design:50-51, 392) | none | design-only; do not build |
| Corrupt tile details `code defaults in effect`, `all overrides ignored` (design:395) | none — tiles always show the numeric form | **gap** (small copy addition) |
| Effective `on` / `off` (design:85-86) | `Effective on` / `Effective off` (`AdminFeatureController.php:246`) | present; string gap |
| Default `default-on` / `default-off` (design:403) | `Default on` / `Default off` (:245) | present; string gap |
| Override `on` / `off` / `none` (design:90-91, 405) | `Override on` / `Override off` / `No override` (:247) | present; string gap |
| Readiness `Operational configuration required` | `AdminFeatureController.php:203,215` (live-computed) | present |
| Readiness `Missing admin operations` | `:75` (`link_previews`) | present, different flag |
| Readiness `Safety-blocked` | `:80` (`custom_css`) | present, different flag |
| Readiness `Reserved (ADR 0018)` | `:50` (`GATE_B_RESERVED`) | present |
| Readiness `Ready for acceptance` (design:309 etc.) | none | design-only; do not build |
| Readiness `Missing user UI` | design has none | production-only (`:70`); keep |
| Readiness absent → `—` (design:100) | `&mdash;` (features.php:77) | present |
| Unknown overrides empty | design renders an empty table | production `No undeclared keys are present in settings.features.` (features.php:91) | production-only; keep |
| Rule validation *"The threshold must be a whole number of 1 or more."* (design:423) | `Threshold must be between 1 and 1000000.` (`BadgeRuleService.php:51`) | present, production more accurate |
| Rule validation *"A rule for that badge and metric already exists on this scope."* (design:425) | none | **real gap**; file separately, do not build now |
| Rule validation — badge / rule-type / board | design has none | production `Choose an enabled badge.` / `Choose an approved rule type.` / `Choose an existing board.` | production-only; keep |
| Rules list empty | design has none | `No badge rules.` (badge_rules.php:58) | production-only; keep |
| Rule pills `Enabled` / `Disabled` (design:179-180) | badge_rules.php:66 — same strings | present |
| Rule toggle label `Disable` / `Enable` (design:435) | badge_rules.php:69,73 — same strings | present |
| Preview lead `{N} member(s) meet this rule today.` (design:448) | `total` computed in `BadgeRuleService::preview()`, never rendered | **gap** |
| Preview empty `No members would receive this badge.` (design:209) | `No users would receive this badge.` (badge_rule_preview.php:18) | present; one-word gap |
| Preview metric `Metric: 8,740` (design:449) | `Metric: 8740` (badge_rule_preview.php:24) | present; formatting gap |
| Emoji validation (3 design strings, design:462-464) | 4 production strings in `CustomEmojiService::create` | present, production more accurate |
| Emoji save flash `:{code}: added to the catalogue.` / `:{code}: replaced.` (design:471) | `Custom emoji saved.` / `Custom emoji replaced — :{code}: already existed.` (`AdminCustomEmojiController.php:35-37`) | present; replace branch is ADR-locked |
| Emoji toggle flash `:{code}: disabled — it will render as plain text.` / `:{code}: enabled.` (design:482) | `Custom emoji disabled.` / `Custom emoji enabled.` (:45,:53) | present; string gap |
| Emoji `Allowed` / `Post rendering only` (design:477) | custom_emoji.php:79 — same strings | present |
| Emoji `Enabled` / `Disabled` (design:273-274) | custom_emoji.php:80 — same strings | present |
| Emoji empty `No custom emoji have been added yet.` (design:281) | custom_emoji.php:67 — **byte-identical** | present |
| **Loading / skeleton** states | design has none anywhere on this screen | production has none | no gap |
| **Pending / disabled submit** states | design has none | production has none | no gap |
| **Filters / search / pagination** | design has none | production has none | no gap |

---

## 6. Slice proposal

Each slice is independently shippable and independently testable.

**S1 — Tabbed "Features & badges" area (IA only, no restyle).**
Add the `Capability sections` tab strip (`<a href>`, `aria-current="page"`, flag-gated disabled
tabs reusing the `_nav.php:81-84` idiom) and the single `<h1>Features & badges</h1>` to
`features.php`, `badge_rules.php`, `badge_rule_preview.php`, `custom_emoji.php`. Drop the
`Runtime controls` and `Appearance` eyebrows. Depends on the AdminNav slice only for moving the
`Admin mode` pill — ship without that and leave the pill in place.
*Tests:* integration — each of the three routes renders three tabs with the correct one current;
with `badge_rules=false` the Badge rules tab renders as `aria-disabled` and the route still 404s;
same for `custom_emoji`. Playwright screenshot of all three tabs.

**S2 — Feature-flags panel restyle.**
Stat tiles, per-group cards, 6-column table, effective dot, default/override chips, readiness
pill, corrupt `role="alert"` panel, unknown-overrides card, corrupt-mode tile detail strings,
intro rewrite with the readiness legend relocated. **No change to** `GROUPS`, `READINESS`,
`OPERATIONS`, `rollbackNote()`, or the `.table-scroll` regions.
*Tests:* integration — corrupt override still surfaces the alert; `custom_css` still reports
`Safety-blocked`; `link_previews` still reports `Missing admin operations`; the Operations link
still appears only when the flag is effectively on. Playwright: normal + corrupt renders.

**S3 — Badge rules panel restyle.**
Two-column grid, create-rule intro copy, `✦` rule rows with pills and the reordered action
cluster, form-level error summary above the retained per-field errors, flash-string adoption.
*Tests:* integration — a 422 create still re-renders with `field_attrs` aria wiring and `old`
values intact; all five flashes assert their new strings; backfill/revoke flashes still carry
their counts.

**S4 — Badge-rule preview restyle.**
Render inside the Badge rules tab shell; chevron back link; monogram rows; `previewLead` from the
already-computed `total`; `members` wording; `number_format()` metric; keep the user-record link.
*Tests:* integration — lead count matches `total`; empty preview renders
`No members would receive this badge.`; a 4-digit metric renders with a separator.

**S5 — Custom emoji panel restyle.**
Two-column grid, single-column field stack, Switch-styled checkbox, second intro sentence,
unheaded catalogue with `aria-label`, visually-hidden `Action` header, 24px chip frame around the
real `<img>`, status pills, outlined toggle button, centred italic empty state, toggle flash
strings.
*Tests:* integration — the create 422 path still round-trips `emoji_old`/`emoji_errors`
(ADR 0023 §4 regression guard); the replace flash is still
`Custom emoji replaced — :{code}: already existed.`; enable/disable POSTs still carry CSRF.

**S6 — Admin flash banner restyle (shared).**
`partials/flash.php` gains the design's success banner anatomy; placement moves below the tab
strip on admin screens. Cross-cutting — sequence with the AdminNav/layout slice, do not fork.
*Tests:* one integration assertion per admin surface that `role="status"` survives; Playwright
diff on two unrelated admin screens.

**S7 — Gap ticket, no UI.**
Duplicate badge-rule guard (design:425). Needs a `BadgeRuleService::create` check plus a unique
index on `(badge_id, rule_type, board_id)`. Record as a design-identified gap; decide separately.
**Do not** ship the error string without the enforcement — inert copy is not evidence
(PRODUCT_DESIGN §13).
