# R — admin-overview: correction addendum to D-admin-overview.md

**Status:** this file supersedes the design-side half of `D-admin-overview.md` and folds in every
refutation and reclassification from `V-admin-overview.md`. Where R and D disagree, **R wins**.
Rows not mentioned here survive from D unchanged.

**Design source (re-read in full, 2026-08-03):**
`C:/Users/htper/community-forums/docs/design-system/imladris/templates/admin-overview/AdminOverview.dc.html`

| Fact | D said | Truth |
|---|---|---|
| Total file lines | 405 (implied) | **394** |
| `<x-dc>` opens | — | line **9** |
| Markup body | lines 21–275 | lines **21–262** (screen root `<div data-screen-label="Admin — overview">` at :21, closes :262) |
| Markup boundary / `</x-dc>` | — | **:263** — markup ends at :263 |
| `<script type="text/x-dc">` | :277 | **:264** — every `x-dc:NNN` citation in D is **+13**; subtract 13 |

**Second design file now in scope, never opened by D:**
`docs/design-system/imladris/components/admin/AdminNav.jsx` (76 lines) + `AdminNav.d.ts` + `admin/admin.card.html`.
`AdminOverview.dc.html:24` mounts it:
`<x-import component-from-global-scope="ImladrisDesignSystem_c3e027.AdminNav" area="overview" hint-size="100%,101px"></x-import>`

---

## 1. Corrected section order (verbatim headings, corrected anchors)

The page head is now **h1-only**. There is **no eyebrow anywhere above the h1**, and no per-page topbar.

| # | Section — verbatim heading string(s) | Corrected anchor | D's stale anchor |
|---|---|---|---|
| 1 | *(no text)* — shared admin chrome, one `x-import` of `AdminNav` | **:24** | :24-30 (inline topbar) |
| 2 | Page frame `max-width: 1160px; margin: 0 auto; padding: 22px 28px 110px` | **:26** | :32 |
| 3 | `Admin console` — bare `<h1 style="margin: 0; … font-size: 2.1rem; …">`, first child of the column | **:29** | :35-41 |
| 4 | `Admin sections` sub-nav — underline tabs `Dashboard` / `Audit log` only | **:32-37** (`margin: 16px 0 0`) | :44-50 (`margin: 22px 0 0`) |
| 5 | Dashboard intro — “Start with the live queues and health signals, then review what has changed across the council.” | **:42** | :55 |
| 6 | `Live operations` / `Queue health` / `Live` chip | **:44-78** (eyebrow :47, h2 :48, chip :50, grid :52) | :57-91 |
| 6a | Card `Reports open` (rust / `Needs review`) | **:53-58** (label :54, count :55, detail :56, state :57) | :66-71 |
| 6b | Card `Approval hold` (amber / `Waiting`) | **:59-64** (detail :62, state :63) | :72-77 |
| 6c | Card `Appeals` (amber / `Waiting`) | **:65-70** (detail :68, state :69) | :78-83 |
| 6d | Card `Email queue` (success / `Clear`) | **:71-76** (detail :74, state :75) | :85-88 |
| 7 | `Triage` / `Needs attention` | **:80-99** (eyebrow :83, h2 :84, count pill :86, `<ul>` :88, `<li>` :90-93, link :91, age :92, empty `<p>` :97) | :93-112 |
| 8 | `Community pulse` / `Community today` | **:101-115** (eyebrow :102, h2 :103, grid :104, card :106-112) | :114-128 |
| 9 | `Audit trail` / `Recent activity` / `View full audit log →` | **:117-145** (eyebrow :120, h2 :121, link-button :123, table :125, 5 `<th>` :127-131, rows :136-140) | :130-158 |
| 10 | *(audit tab)* audit intro — “Every moderation and admin action, append-only. …” | **:152** | :165 |
| 11 | Filter form card — `Actor` `Action` `Target type` `Target #` `From` `To`, `Apply filters`, `Reset`, result label | **:154-196** (grid :155, fields :156-189, actions row :191-195, Apply :192, Reset :193, label :194) | :167-209 |
| 12 | Loading skeleton (6 bars, `adPulse`) | **:198-207** | :211-219 |
| 13 | Error state — `The log could not be read` | **:209-215** (h2 :211, p :212, `Try again` :213) | :222-228 |
| 14 | Table card, 6 cols — `When Actor Action Target Reason Change` + empty `Nothing matches these filters` | **:217-248** (shell :218, `<th>` :221-226, rows :230-237, target link :234, change cell :236, empty block :241-247) | :230-261 |
| 15 | Pager — `Previous` / `{{ pageLabel }}` / `Next` | **:250-256** (Previous :252, label :253, Next :254) | :263-269 |

**x-dc anchors (subtract 13 from every D citation):** `AUDIT` rows :266-289 · `PAGE_SIZE = 10` :292 ·
`reset` :313 · `isEmpty` :321 · `pool` :323 · client filter predicates :325-333 · `attention` :340-345 ·
`qReports/qHold/qAppeals/qEmail` :355-358 · `activityCards` :362-367 · `recentAudit … slice(0, 6)` :368 ·
`retry` :378 · `resultLabel` :380 · `noRows` :382 · `showPager` :383 · `pageLabel` :384 ·
`atFirstPage/atLastPage` :385-386.

**Order verdict (unchanged):** production still renders the design's dashboard order exactly. The order pins at
`AppAdminDashboardRemediationTest.php:264-274` and `admin-dashboard.spec.ts:92` stay green.

---

## 2. INVERTED ROWS — the action is now the opposite of what D proposed

These are the load-bearing corrections. Adopting D as written would move production **away** from the design.

### Row 3 — Head geometry · `copy` · **INVERTED**
- **D said:** design head is a flex row, `align-items: flex-start`, h1 **2.4rem**, pill `margin-top: 8px`. Action: “Raise h1 to 2.4rem, switch to flex-start.”
- **Truth (`:29`):** the head is a single bare `<h1 style="margin: 0; font-family: var(--font-display); font-weight: 500; font-size: 2.1rem; line-height: 1.1; letter-spacing: -0.01em; color: var(--text-strong);">Admin console</h1>`. No wrapper, no flex row, no pill, `margin: 0`.
- **Corrected action:** set `.admin-head h1` to **2.1rem** (production is 1.9rem — `app.css:2825-2828`) and `margin: 0`. Reduce the `.admin` top padding from 24px to **22px** to match `:26`. Do **not** introduce `align-items: flex-start` — with one child there is no cross-axis question. Keeping the `.admin-head` bottom rule (`app.css:2820`) remains defensible on the 3-node-grid grounds D gave, but it is now a production-only addition, not a design match — record it as such.
- Folds in V/Mi4.

### Row 4 — Eyebrow skin · `copy` · **INVERTED for the head eyebrow**
- **D said:** design head eyebrow `Operator desk`, `.68rem`, `--gold-ink`, `.18em` at `:37`. Action: “Scope admin eyebrow size/colour.”
- **Truth:** `grep -F "Operator desk"` returns **nothing** in the current file. The head eyebrow was deleted upstream. `components/admin/admin.card.html:43` documents it verbatim: *“the redundant “Operator desk · Area” kicker is gone, the mode pill moved into the identity row, and the heading drops from 2.4rem to 2.1rem.”*
- **Corrected action, part A (deletion):** **delete** `<span class="eyebrow">Operator desk</span>` from `templates/admin/dashboard.php:6` and `<span class="eyebrow">Accountability</span>` from `templates/admin/audit.php:12`. The `<h1>` becomes the first child of the head. This is the single largest copy delta on the screen and D had it backwards.
- **Corrected action, part B (retained):** the **section** eyebrows survive and are unchanged — `Live operations` (:47), `Triage` (:83), `Community pulse` (:102), `Audit trail` (:120), all `.64rem` / `.16em` / `var(--gold-ink)`. Production `.eyebrow` is `.72rem` / `var(--tracking-caps)` (.16em) / `var(--text-muted)` (`app.css:37-43`). Scope a `.admin .eyebrow` override to `.64rem` + `var(--gold-ink)`; tracking already matches.
- Folds in V/R1 and V/Mi3.

### Row 5 — “Admin mode” pill · `copy` · **INVERTED**
- **D said:** design puts the pill in the page head at `:40` with `--surface-review` / `--on-review`. Action: “Swap to the review pair.”
- **Truth:** `grep -F "Admin mode"` returns **nothing** in `AdminOverview.dc.html`. The pill moved into the shared chrome — `AdminNav.jsx:45` (`modeLabel = 'Admin mode'`) rendered at `:58` as `<span className="admin-bar-mode">`, and omitted entirely when `modeLabel` is null.
- **Corrected action:** **remove** `<span class="pill pill-admin">Admin mode</span>` from the page head in `templates/admin/dashboard.php:9` and `templates/admin/audit.php:15`. If operator-mode identity is still wanted, it belongs once in the shared admin chrome (the `_nav.php` rail header or the layout), not repeated per page. Do **not** re-skin `.pill-admin` in place — that cements the thing the design deleted.
- Note: `--surface-review` / `--on-review` are no longer cited by this screen at all. If the pill is relocated, the token choice must be re-derived from `AdminNav`’s stylesheet, not from this file.

### Row 1 — Per-screen topbar · `constraint` · **premise inverted, disposition survives**
- **D said:** design has a sticky 58px bar with an 8-point star SVG, the `Imladris` wordmark and `‹ Back to the council` at `:24-30`.
- **Truth:** `:24` is **one line** — the `AdminNav` `x-import`. `grep -F` finds no `Imladris` wordmark, no `Back to the council`, no star SVG in this file. Those strings now live on the shared component (`AdminNav.jsx:53`, `:44`, `Mark()` at `:27-30`).
- **Corrected action:** unchanged in effect — do not port brand chrome; `templates/layout.php` owns the shell and renders `$brand`. But the *reason* changes: this is no longer a per-screen elision, it is a shared-chrome contract binding all ten `admin-*` templates. See new row **N1**.

### Row 7 — Pseudo-nav span · **STRUCK**
- **D said:** design carries a non-interactive `Moderation · Content · People · Appearance · Notifications · Integrations · Settings` span at `:49`; action “do not ship — dead chrome.”
- **Truth:** `grep -F "Moderation · Content"` returns **nothing**. The span was deleted upstream (AdminOverview was the only screen that had one). The sub-nav at `:32-37` is four `sc-if` buttons and nothing else.
- **Disposition:** row struck — there is no longer a difference to record. `−1 feature-removed`.

---

## 3. FABRICATED / NO-LONGER-PRESENT QUOTED STRINGS

Every quoted design string in D was re-checked with a literal `grep -F` against the current file.

| D row / §3 item | Quoted string | Result |
|---|---|---|
| Row 3, Row 4 | `Operator desk` | **NOT FOUND** — deleted upstream |
| Row 5 | `Admin mode` (in this file) | **NOT FOUND** — now `AdminNav.jsx:45/:58` |
| Row 1, §3 item 1 | `Imladris` wordmark | **NOT FOUND as a wordmark** — the only occurrence of the token “Imladris” is inside the `x-import` component path at `:24`. Wordmark is `AdminNav.jsx:53` |
| Row 1, §3 item 2 | Eight-point elven-star SVG | **NOT FOUND** — `AdminNav.jsx:27-30` resolves `EightPointStar` off the namespace |
| Row 1, §3 item 3 | `Back to the council` | **NOT FOUND** — `AdminNav.jsx:44`; also `AccountSettings.dc.html:29` and `UserProfile.dc.html:29`, so it is a **cross-screen** fiction string needing one production answer |
| Row 7 | `Moderation · Content · People · Appearance · Notifications · Integrations · Settings` | **NOT FOUND** |
| Row 3 | head `align-items: flex-start`, pill `margin-top: 8px`, h1 `2.4rem` | **NOT FOUND** — the head is `:29` alone at 2.1rem |
| Row 2 | `padding: 26px 28px 110px` | **NOT FOUND** — now `22px 28px 110px` (`:26`) |
| Row 4 | subnav `margin: 22px 0 0` | **NOT FOUND** — now `16px 0 0` (`:32`) |

**Strings D quoted that ARE still present (verified by `grep -F`, anchors corrected):**
“Start with the live queues and health signals, then review what has changed across the council.” :42 ·
`Reports open` :54 · “3 unclaimed · 1 harassment” :56 · `Needs review` :57 · `Waiting` :63, :69 ·
“1 past the 72-hour promise” :68 · `Email queue` :72 · “Last run 11 minutes ago” :74 · `Clear` :75 ·
“No pending operator work right now. The queues are clear.” :97 · `View full audit log →` :123 ·
“Every moderation and admin action, append-only. Filter it, page through it, and follow a target's whole trail from its own record.” :152 ·
“The log could not be read” :211 · “The audit trail is append-only and intact — this is a read failure, not a gap in the record.” :212 ·
`Try again` :213 · “Nothing matches these filters” :243 · “The record is complete; this slice of it is simply empty.” :244 ·
`Reset filters` :245 · `1 entry` / `N entries` :380 · `Page N of M` :384.

**Fiction table (D §3) corrected:** items 1–3 are **not on this screen** — re-file them against
`components/admin/AdminNav.jsx` as a cross-screen decision. Items 4–11 all survive at corrected anchors:
item 4 :42 · item 5 `Commends given` :366 · item 6 :68 and :343 · item 7 `Wardens may now merge tags` :277 ·
item 8 actors `erestor`/`elrond`/`glorfindel`/`melian`/`celebrian` :266-289 · item 9 `#lore` :266 ·
item 10 `Keeper of the record — 100 accepted` :275 · item 11 `ledger-sync` :344. All correctly called.

---

## 4. V-REPORT REFUTATIONS AND RECLASSIFICATIONS, FOLDED IN

| Row | V finding | Corrected disposition |
|---|---|---|
| 18 `Reports open` | **R2** — `admin-dashboard.spec.ts:99-101` uses `toHaveText(string[])`, which is **full-string equality**, not substring. `Reports open` fails it. D's justification (the PHPUnit substring pin survives) is true but insufficient. | Stays `copy`. **Action rewritten:** the rename is a *contract change*, not a free copy fix — it must land together with an update to `admin-dashboard.spec.ts:99-101` and an explicit note against `docs/superpowers/plans/2026-07-18-admin-dashboard-ui-remediation.md:39` (“dashboard queue/activity labels” are pinned). Risk **low → medium**. Slice S2's test list is incomplete as written. D's separate claim that dropping `text-transform: uppercase` is safe is **correct** (`toHaveText` reads `textContent`). |
| 32 Activity grid | **M4** — only two metrics exist, so 2 columns is the correct rendering of a `feature-removed` set, not a `copy` production must change. | **Reclassified `copy` → `feature-removed`** (downstream of row 29). The `gap: 12px → 14px` adoption is folded into row 33 (`copy`), which already owns activity-card anatomy. |
| 36 `View full audit log →` | **R3** — the `::after` mitigation is not proven safe: `admin-dashboard.spec.ts:105` matches by accessible name (`getByRole('link', { name: 'View full audit log' })`, full-string) and Chromium **includes CSS generated content in accname**. The house pattern is an `aria-hidden` span (`dashboard.php:92`), which breaks the byte-identical PHPUnit pin at `AppAdminDashboardRemediationTest.php:280`. | Stays `copy`. **Action rewritten:** use the house `<span aria-hidden="true">→</span>` pattern and **relax the byte-identical PHPUnit pin to a DOM/substring assertion in the same commit**. Risk **medium → high**. The two pins are in genuine tension; D presented it as solved. |
| 41 Dashboard empty audit | **M1** — design models no empty branch (`recentAudit` :368; no `sc-if` in the dashboard table at :133-143); production has the string and D keeps it. D's own §4 state inventory already called it `feature-added`. | **Reclassified `copy` → `feature-added`.** Action unchanged (keep the string, restyle). |
| 42 Row hover | **M2** — design has no `tr:hover`; production has `.admin .audit tr:hover td` and D recommends “keep”. “Keep” is only available under `feature-added`. | **Reclassified `copy` → `feature-added`.** |
| 54 Loading skeleton | **M5** — the named constraint is wrong. A `@keyframes` block ships fine from `public/assets/app.css`; CSP forbids only the **inline delivery**, which is a mechanism constraint, never a licence to change the visual result. | Stays `constraint`. **Reason rewritten:** the load-bearing constraint is **progressive enhancement** — the page is server-rendered in one pass, so there is no loading state to reach. CSP is a footnote about delivery, not the reason. |
| 57 Unfiltered vs filtered empty | **R4** — D's own two columns read “one state” on both sides, i.e. no difference, yet it is filed as `copy` with an action to build a second empty state. Verified against x-dc `:321-333`: `isEmpty` yields `pool = []`, `noRows = true`, and the single `Nothing matches these filters` block covers both no-data and no-match. | **STRUCK.** Inventing a state neither side models is not a sanctioned deviation. `base_query` existing at `AuditQueryService.php:73` is not a warrant. `−1 copy`. |
| 59 Change column | **R5** — the plan citation does not support the conclusion. `…2026-07-18…:37` pins the **stored** `before_json`/`after_json` payloads asserted by an integration test; it says nothing about the disclosure widget. | Stays `feature-changed`, action unchanged. **Drop the plan citation** — the conclusion (keep `<details>`; the stored data is raw JSON, not a prose diff) stands on the data shape alone. |
| 61 Page size | **M3** — same concept, different mechanics, and D keeps production's 50 on operator-surface grounds. That is the `feature-changed` disposition; filing it `copy` with a “keep production” action is self-contradictory. | **Reclassified `copy` → `feature-changed`.** Also cite the governing clamp: `AuditQueryService.php:72` (`$perPage = max(1, min(200, $perPage))`), not only the repo's 200 cap. |

**V/R6 minor-citation corrections — partially wrong, re-verified here.** Only one of V's five ±1 fixes holds:
`.admin` really is at **app.css:2800** (D said 2799 — D wrong). The other four are V's error, not D's:
`.filter-grid` is at **3129**, `.admin .audit th` at **3239**, `.admin .audit tr:hover td` at **3267** — D was
right on all three. `.admin .audit code` opens at **3270**. `human_datetime()` is declared at
`helpers.php:65` inside a `function_exists` guard opening at :64. Use the numbers in this paragraph.

**V claims that hold and need no change:** every `AdminDashboardService` citation (`:62` `'Reports'`, `:86-98`,
`:101-124`, `:126-169` attention entries carrying only `label`+`href`, `:173-186`, `:48` `recent(10)`,
statuses limited to `attention|clear|unavailable`); the three-status pin; the `AuditQueryService` error strings and
actor→id resolution; the prefix `LIKE`; the kernel-500 finding for row 55; the ADR 0023 items 4/5/6 locks; the
token check; the byte-for-byte filter-field match (row 49, now `:156-189` vs `audit.php:24-57`); and the finding
that no proposed action reverts an ADR 0021 or ADR 0023 deferral.

---

## 5. NEW ROWS — present in the current design file, missed by D

| # | Section | Classification | Design (corrected anchor) | Production | Action | Risk |
|---|---|---|---|---|---|---|
| **N1** | Admin area navigation | feature-changed | `AdminNav` mounts on all ten `admin-*` templates (`:24`). Identity row = star + `Imladris` + `Back to the council` + `Admin mode` pill (`AdminNav.jsx:52-59`); `nav.admin-tier[aria-label="Admin areas"]` = **ten** horizontal pills in console order — **Overview · Content · People · Members · Appearance · Notifications · Integrations · Packages · Features · Settings** (`AdminNav.jsx:8-19`, `:60-73`), `.is-active` + `aria-current="page"` on the current one | 8-group **vertical** rail, `aria-label="Admin navigation"`, 224px sticky + mobile drawer (`_nav.php:7-50`). Different taxonomy: Members, Packages and Features are **not** top-level — Invitations under People (`_nav.php:25`), Packages under Integrations (`:38`), Feature flags under Settings (`:47`) | **Keep the rail.** ADR 0023 item 6 and ADMIN §9.2 lock the IA. But record this as a **cross-screen** decision binding all ten admin screens, not an admin-overview-local elision, and record the deltas explicitly: ten-area vs eight-group taxonomy, pill vs link register, horizontal tier vs left rail | high |
| **N2** | Page-level sub-nav (third chrome rank) | feature-removed | `nav[aria-label="Admin sections"]` at `:32-37` — underline tabs, `gap: 2px`, `margin: 16px 0 0`, `border-bottom: 1px solid var(--border-hair)`, active tab `2px solid var(--gold-500)`. `admin.card.html:43` states the intended three ranks: area pill tier → page `<h1>` → the page's own underline sub-tabs | **No page-level sub-nav exists.** Dashboard and Audit log are siblings in two *different* rail groups (`_nav.php:9` under “Dashboard”, `_nav.php:15` under “Moderation”) | **Do not build.** Adding a tab strip would duplicate rail navigation and cut across the locked 8-group IA | low |
| **N3** | Section rhythm | copy | Each dashboard section carries `margin-bottom: 30px` individually (`:44`, `:80`, `:101`); the last section (`:117`) carries none | Uniform `.admin-pane { gap: 22px }` (`app.css:2923`) | Raise the pane gap toward 30px in the admin scope, or move to per-section margins. Cheap; no test surface | low |
| **N4** | `Community today` heading block | copy | `:101-103` is a **plain block** — `<span>` eyebrow then `<h2>`, no flex row, no right-hand slot | `dashboard.php:64-69` wraps it in `.section-heading-row` with an empty `<div>`, forcing `justify-content: space-between` on a one-child row (`app.css:2946`) | Drop the wrapper for this section; render eyebrow + h2 as a plain block | low |
| **N5** | Attention list rendering shape | copy | Always renders the `<ul>` (`:88-95`) and appends the empty `<p>` **after** it (`:96-98`) | Renders the `<p>` **or** the `<ul>` (`dashboard.php:46-60`) | Cosmetically equivalent today, but it changes where the empty sentence sits relative to the list rule. Match the design: always render the `<ul>`, append the `<p>` | low |
| **N6** | `code` action chip | copy | `padding: 1px 6px; border-radius: var(--radius-sm); background: var(--surface-sunken); font-size: .76rem; color: var(--text-body)` (`:138` dashboard, `:233` audit) | `.admin .audit code` (`app.css:3270-3277`): `border-radius: 4px` — a **hardcoded value where the design uses a token** — plus `font-size: .82em` and `color: var(--text-strong)` | Adopt the token radius and the `.76rem` / `--text-body` pair. Fold into slice S5 as a real diff row, not just a touch-list mention | low |
| **N7** | Reset control labels | copy | Two distinct strings for one handler: `Reset` in the form actions (`:193`) and `Reset filters` in the empty block (`:245`); both call `resetFilters` (x-dc `:313-316`), which clears typed fields **and** applied filters | One label (`Reset`, `audit.php:61`) and no empty-state control | When the empty-state reset lands (row 56), label it `Reset filters` — not `Reset`. Both are `<a href="/admin/audit">` under PE | low |

**Scope correction to row 58 (no new row).** D audited only `audit.php`. `templates/admin/dashboard.php:107`
renders `<?= $e($row['target_type']) ?> #<?= (int) $row['target_id'] ?>` — **plain text for every type, including
`user`**, which *is* linked on the audit page (`audit.php:85-86`). The design is asymmetric in the same direction:
dashboard targets are plain mono `--text-muted` (`:139`), audit targets are `--artifact-link` (`:234`).
So design and production **agree** on the dashboard. Row 58 stands as written but must be stated as
audit-page-scoped; there is no dashboard-side action.

---

## 6. Corrected classification counts

| Class | D | Δ | **R** |
|---|---|---|---|
| copy | 41 | −1 (57 struck) −1 (32→feature-removed) −1 (41→feature-added) −1 (42→feature-added) −1 (61→feature-changed) +5 (N3, N4, N5, N6, N7) | **41** |
| feature-added | 6 | +1 (41) +1 (42) | **8** |
| feature-removed | 7 | −1 (7 struck) +1 (32) +1 (N2) | **8** |
| feature-changed | 5 | +1 (61) +1 (N1) | **7** |
| constraint | 4 | — | **4** |
| **total rows** | **63** | −2 struck, +7 new | **68** |

Struck rows: **7** (pseudo-nav span — string no longer exists) and **57** (unfiltered-empty variant — neither side models it).

---

## 7. Slice-plan consequences

- **S1** gains the two deletions (head eyebrows in both templates; the in-head `Admin mode` pill in both templates)
  and loses “raise h1 to 2.4rem” → **2.1rem**. It also gains N3 and N4. Its test list must add an assertion that
  neither `Operator desk` nor `Accountability` nor `pill-admin` appears in the admin head markup.
- **S2** must add `tests/browser/admin-dashboard.spec.ts:99-101` to its test list, or drop row 18 entirely.
  Row 32's column-count half is struck; only `gap: 14px` survives, inside S4/row 33.
- **S5** must add N6 (`code` chip) as a diff row, and swap row 36's `::after` for the `aria-hidden` span **plus**
  the PHPUnit pin relaxation.
- **S7** loses row 57 (no unfiltered-empty variant) and gains N7 (`Reset filters` label for the empty-state control).
- **New cross-screen item for the parent pass:** `AdminNav` (N1) and `Back to the council` (fiction, also on
  `AccountSettings.dc.html:29` and `UserProfile.dc.html:29`) need one answer across all screens, not per-screen answers.
