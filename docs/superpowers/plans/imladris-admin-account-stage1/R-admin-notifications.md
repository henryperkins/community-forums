# R — admin-notifications: correction addendum to `D-admin-notifications.md`

**This file supersedes `D-admin-notifications.md` and `V-admin-notifications.md` wherever they conflict.**
It corrects, it does not re-derive. Any D-row not named here stands as written, with its design line anchor
translated by §1.

**Design source (re-measured 2026-08-03, working tree, ` M` uncommitted):**
`C:/Users/htper/community-forums/docs/design-system/imladris/templates/admin-notifications/AdminNotifications.dc.html`

| | D report claimed | **Truth (verified by `wc -l` + `grep -n`)** |
|---|---|---|
| Total lines | 453 | **440** |
| Markup portion | 1–279 | **1–267** (`<x-dc>` opens at 9, `</x-dc>` closes at **267**; the screen `<div>` is **20–266**) |
| `<script type="text/x-dc">` | 280–450 | **268–438** (`</script>` at 438, `</body>` 439, `</html>` 440) |

The V report's own header (441 lines / markup 20–266 / script 268–438) is off by one on the total. **440.**

---

## 1. Line-anchor translation

The resync deleted the per-screen chrome (HEAD 22–38) and inserted one `<x-import>` in its place.
The offset is **not** uniform:

| HEAD range cited by D | Current file | Rule |
|---|---|---|
| 1–18 (`<helmet>`, `@keyframes anRise`) | **1–18, unchanged** | identity |
| 22–28 (sticky topbar) | **gone** — line 22 is now the `AdminNav` x-import | deleted |
| 30 (container div) | **24** | −6 |
| 32–38 (page-head block: eyebrow + h1 + chip) | **only the h1 survives, at 26** | block deleted |
| 34 (eyebrow) | **gone** | deleted |
| 37 (`Admin mode` chip) | **gone** | deleted |
| **40 → 450 (everything else, markup *and* script)** | **subtract 12** | −12, verified end-to-end |

Verification of the −12 rule at both extremes and in between (literal grep, current file):
`Notification sections` HEAD 40 → **28**; `Sending domain` h2 HEAD 53 → **41**; `Queue status` h2 HEAD 77 → **65**;
`The log keeps thirty days…` HEAD 158 → **146**; `No addresses are suppressed…` HEAD 200 → **188**;
`No announcements have been published yet.` HEAD 271 → **259**; `const BAD` HEAD 312 → **300**;
`Added by an operator` HEAD 349 → **337**; `broadcastReach` HEAD 440 → **428**.

### 1a. Every design citation in D and V, re-anchored

**Markup**

| D cite | Now | | D cite | Now |
|---|---|---|---|---|
| 11–18 | 11–18 | | 125–131 | 113–119 |
| 22–28 | *deleted* | | 136 | 124 |
| 30 | 24 | | 138 | 126 |
| 32–38 | *deleted* | | 140–143 | 128–131 |
| 34 | *deleted* | | 145 | 133 |
| 35 (h1) | 26 | | 147–149 | 135–137 |
| 37 | *deleted* | | 156–159 | 144–147 |
| 40–45 | 28–33 | | 158 | 146 |
| 51–86 | 39–74 | | 162–168 | 150–156 |
| 52–64 | 40–52 | | 164 / 165 / 166 | 152 / 153 / 154 |
| 54 | 42 | | 171–202 | 159–190 |
| 56–57 | 44–45 | | 174–177 | 162–165 |
| 58 | 46 | | 177 (empty `<th>`) | 174 |
| 61 | 49 | | 179 | 167 |
| 62 | 50 | | 193 | 181 |
| 66–73 | 54–61 | | 194 | 182 |
| 68 / 70 / 71 | 56 / 58 / 59 | | 200 | 188 |
| 76–86 | 64–74 | | 209–221 | 197–209 |
| 76–77 | 64–65 | | 213 / 214 / 215 | 201 / 202 / 203 |
| 78–85 | 66–73 | | 219 | 207 |
| 88–169 | 76–157 | | 223–244 | 211–232 |
| 90–121 | 78–109 | | 227 / 230 | 215 / 218 |
| 93 / 105 / 117 | 81 / 93 / 105 | | 232–234 | 220–222 |
| 95–100 (status opts) | 83–88 | | 236–238 | 224–226 |
| 107–112 (kind opts) | 95–100 | | 241 | 229 |
| 119 / 120 | 107 / 108 | | 246–273 | 234–261 |
| — | — | | 251–255 | 239–243 |
| — | — | | 260 / 262 / 271 | 248 / 250 / 259 |

**Script block** (all −12): 281–310→269–298 · 282–296→270–284 · 284→272 · 285→273 · 286→274 · 288→276 ·
291→279 · 292→280 · 293→281 · 294→282 · 295→283 · 296→284 · 300–302→288–290 · 306→294 · 306–309→294–297 ·
309→297 · 312→300 · 323→311 · 325→313 · 333–336→321–324 · 339→327 · 349→337 · 378→366 · 379–383→367–371 ·
381→369 · 386→374 · 392→380 · 395→383 · 400→388 · 401–403→389–391 · 407→395 · 427→415 · 430→418 · 440→428.

**V-report `X`-row citations were already taken against the working-tree file and need no translation**
(X3 → 117/119/133/135; X4 → 125/133/134/179; X5 → 167 and 227/229; X6 → 187–189; X7 → 76/211/65).

---

## 2. Corrected section order (verbatim headings, top to bottom)

**There is no eyebrow and no page-head block.** The `<h1>` at line 26 is the first child of the content
column, immediately after the x-import at 22.

| # | Element | Current lines | Verbatim text |
|---|---|---|---|
| 1 | shared chrome — `<x-import component-from-global-scope="ImladrisDesignSystem_c3e027.AdminNav" area="notifications" hint-size="100%,101px">` | **22** | *(no page-level string; `Imladris`, `Back to the council` and `Admin mode` all live inside the component)* |
| 2 | `<h1>` — `2.1rem`, `margin: 0`, `--font-display` 500 | **26** | `Email & announcements` (source: `Email &amp; announcements`) |
| 3 | `<nav aria-label="Notification sections">`, `margin: 16px 0 0` | **28–33** | `Email` · `Announcements` |
| 4 | **[Email]** 2-up grid (39), left card | **40–52** | h2 `Sending domain` |
| 5 | **[Email]** 2-up grid, right card | **54–61** | h2 `Send a test email` |
| 6 | **[Email]** unboxed section, caps-eyebrow h2 | **64–74** | h2 `Queue status` |
| 7 | **[Email]** raised card **+ `--shadow-sm`** | **76–157** | h2 `Delivery log` |
| 8 | **[Email]** raised card | **159–190** | h2 `Suppressed addresses` |
| 9 | **[Announcements]** raised card | **197–209** | h2 `Current banner` |
| 10 | **[Announcements]** raised card **+ `--shadow-sm`** | **211–232** | h2 `Publish a banner` |
| 11 | **[Announcements]** raised card | **234–261** | h2 `Recent history` |

D's §1 "Design order" table rows 1 and 2 are deleted outright; rows 3–11 renumber to 1–9 above.
**The production-order tables and all four "Order deltas" in D §1 are unaffected and stand as written**, except
delta 3's framing: production's `header.admin-head` now differs from the design by *containing* an `Admin mode`
pill the design has moved up into shared chrome (see I2), not by *lacking* an eyebrow.

Also unchanged and verified present: the sub-nav has exactly two buttons and **no** trailing area list
(the trailing `Moderation · Content · People …` span was an AdminOverview-only loss).

---

## 3. Rows whose action is INVERTED or otherwise reversed by the chrome change

### I1 — **D3 is STRUCK.** Do not add an eyebrow. `copy` count −1.
> D3 said: *"design:34 eyebrow `Operator desk · Notifications` … Add `<span class="eyebrow">Operator desk ·
> Notifications</span>` above both h1s."* Slice **N1** turns this into a PHPUnit assertion.

`grep -c "Operator desk"` on the current file → **0**. The eyebrow does not exist on this screen or on any of
the other nine admin screens. `components/admin/admin.card.html:43` documents the deletion as intentional:
*"the redundant “Operator desk · Area” kicker is gone, the mode pill moved into the identity row, and the
heading drops from 2.4rem to 2.1rem."*

**Corrected action:** none — production's `email.php:13-16` and `announcements.php:9-12` already have no
eyebrow, so there is **no difference to record**. Delete the row; delete the assertion from N1.

**Cross-screen consequence (out of scope here, must be carried):** production's *other* admin pages —
`templates/admin/dashboard.php:6`, `settings.php:14`, `branding.php:11` (rule at `public/assets/app.css:2822`) —
each render `<span class="eyebrow">Operator desk</span>`. Under the current design those are **deletion
candidates**, not a pattern to extend to `email.php`/`announcements.php`. Any Stage-2 row that says "add an
eyebrow" on any admin screen is inverted the same way.

### I2 — **D5 is INVERTED and reclassified `copy` → `constraint`.**
> D5 said: *"design:37 `Admin mode` chip … Restyle `.pill-admin` to the design chip spec."*

`grep -c "Admin mode"` on the current file → **0**. The chip is now shared chrome:
`components/admin/AdminNav.jsx:45` (`modeLabel = 'Admin mode'`) rendered at `:58` as
`<span className="admin-bar-mode">` inside the AdminNav **identity row**.

**Corrected action:** **keep `<span class="pill pill-admin">Admin mode</span>` exactly where it is**
(`email.php:15`, `announcements.php:11`). Production has no top admin bar to move it into — the console IA is
the ADR-0023-item-6 vertical rail — so verbatim copy of the placement is blocked. Classification `constraint`.
*Optional and separately decidable:* the visual values still exist in the design system at
`components.css:334` (`padding: 4px 12px; border-radius: var(--radius-pill); background: var(--surface-review);
color: var(--on-review); font-family: var(--font-label); font-size: .72rem; letter-spacing: .08em;
text-transform: uppercase`). Adopting them is adopting *shared-chrome* tokens, not this screen's markup — say
so if you do it. D's quoted "4px 12px / 999px / .72rem / .08em" came from HEAD's page head and is no longer
sourced from this file.

### I3 — **D1 is re-anchored and its premise inverted.**
> D1 said: *"Topbar … (design:22-28) … Do not port. **The screen begins at the page head.**"*

Line 22 is now `<x-import …AdminNav area="notifications" hint-size="100%,101px">`. The screen does **not**
begin at a page head — it begins with 101px of **shared** admin chrome, and the design now *does* hold an
opinion about admin chrome (it didn't per-screen before; it holds it once, centrally).

**Corrected action:** still **do not port** — production's operator shell comes from `templates/layout.php`
(`$brand['name']` / `$brand['logo_path']`) plus the rail. But record it as a *shared-chrome* constraint, not a
"this screen has a decorative topbar" dismissal, and pair it with X1 (area tier). Classification stays
`constraint`.

### I4 — **The §3 "Non-fiction check" paragraph is inverted.**
> D said: *"`Operator desk` (design:34) … and `Admin mode` (design:37) are already-adopted production register …
> **Keep them.**"*

Both strings are **absent from the current design file** (0 hits each). The design system deliberately removed
the kicker (admin.card.html:43). `Operator desk` remains legitimate *production* register — that fact is
unchanged — but it is no longer *design-sanctioned chrome for an admin page head*, so it cannot be cited as
design justification for adding anything. `Test message from the operator desk` (now **line 284**) is real and
its row is unaffected.

### I5 — **D6's container spec is stale by the resync.**
> D6 quoted `max-width:1140px; padding:26px 28px 110px` at design:30.

Current **line 24**: `max-width: 1140px; margin: 0 auto; padding: 22px 28px 110px;` — top padding is **22px**,
not 26px. Two more values changed with it and belong in this row (they were previously untracked):
**h1 `font-size: 2.1rem; margin: 0`** (line 26, was 2.4rem / `margin: 7px 0 0`) and **tab-strip
`margin: 16px 0 0`** (line 28, was `22px 0 0`). The x-import declares the shared chrome at **101px** tall —
relevant when reconciling against production's `.admin-head` + rail stack. Classification stays `constraint`;
only the numbers change.

---

## 4. Fabricated / dead quotations — struck on grep

Every string below was checked with a literal `grep -F` against the current file.

| Row | Quoted as | Grep result | Disposition |
|---|---|---|---|
| **D3** | eyebrow `Operator desk · Notifications` @ design:34 | **0 hits** | **Struck** (I1). |
| **D5** | `Admin mode` @ design:37 + its chip spec | **0 hits** | **Struck as a page-head element**; spec relocated to `components.css:334` (I2). |
| **D6** | `padding: 26px 28px 110px` @ design:30 | **0 hits** | Corrected to `22px 28px 110px` @ **24** (I5). |
| **F1** | `Imladris` wordmark @ design:25 | line 25 is blank. The token *does* occur at **line 22** — but only as the build identifier `ImladrisDesignSystem_c3e027.AdminNav`, never as user-visible text. | **Citation dead.** Real source: `components/admin/AdminNav.jsx:53`. Still fiction; still do not port. |
| **F2** | eight-point elven star SVG @ design:24 | line 24 is the container `<div>`; **no `<svg>` anywhere in the file** | **Citation dead.** Real source: `AdminNav.jsx:29` (`<Star size={24} />`). |
| **F3** | `Back to the council` @ design:27 | **0 hits in this file** | **Citation dead.** Real source: `AdminNav.jsx:44`. |
| **D22** | filter row *"baseline-aligned"* | line 78 is `align-items: **flex-end**` | Paraphrase wrong (V/R4). Copy the attribute. |
| **header** | *"453 lines; markup 1–279, script 280–450"* | 440 / 1–267 / 268–438 | Corrected above. |

**No other quoted design string in D is fabricated.** All of these were confirmed present, verbatim, at the
re-anchored lines: `Verified-domain send blocking is enabled.` (50) · `Sends a one-off message to your own
account address and records it in the log below.` (56) · `Queued — it is at the top of the log.` (59) ·
`Nothing matches these filters` (145) · `The log keeps thirty days. Widen the filters to see more of it.` (146) ·
`Enter a full email address.` (167) · `Release` (182) · `No addresses are suppressed. Bounces and complaints
land here automatically.` (188) · `No banner is currently shown.` (207) · all three checkbox labels (220–222) ·
`This will reach {{ broadcastReach }} by email. Broadcasts cannot be recalled once the queue starts.` (225) ·
`A banner needs a message.` (229) · `No announcements have been published yet.` (259) · `Requeue` (136) ·
`Reset` (107) · `council.imladris.example` (42) · and every F4–F12 fiction string at its −12 line.

---

## 5. V-report findings folded in (this addendum is now the single source)

**Refutations**

- **R1** → I1 above. D3 struck.
- **R2** → I2 above. D5 inverted to `constraint`.
- **R3 — D43's test-impact claim is wrong and would redden CI.** `AppAdminEmailTest.php:124-133`
  (`test_suppress_then_remove_round_trip_via_http`) asserts the redirect and row presence/absence only — it
  **never** asserts the button label or the flash string, so **no PHPUnit change is needed**. The actual pin is
  **`tests/browser/gate-a.spec.ts:1281`** — verified verbatim:
  `await row.getByRole('button', { name: 'Remove' }).click({ force: true });`
  That file is what `.github/workflows/browser-evidence.yml` runs, and it is the repo's **only** CI. Renaming
  `Remove` → `Release` without updating line 1281 in the same commit gives a green `composer test` and a red
  CI. **Slice N4's test list must name `gate-a.spec.ts:1281`, not `AppAdminEmailTest.php:124-133`.**
- **R4 — D22:** `align-items: flex-end` (line 78), not "baseline".

**Reclassification**

- **M1 — D18 splits in two.** `constraint` count unchanged, `copy` +1.
  - **D18a `constraint`** — the *mechanism*: POST→redirect→flash. No client success state may ship (PE).
  - **D18b `copy`** — the *placement*: the design puts the confirmation inline beside the Send button
    (line **59**). Production's flash already exists (`AdminEmailController.php:68`); rendering it server-side
    as an inline `role="status"` next to the button costs zero JS and no CSRF change, so "no verbatim copy is
    possible" is **not** established. Adopt it, or decline it in writing.
  - **D19 stands unchanged**: the *string* `Queued — it is at the top of the log.` must not ship — the button
    sends synchronously (`EmailOpsService.php:104-117`).

**Missed rows — now added to the ledger (X1–X7)**

| ID | Section | Class | Design (current line) | Production | Action | Risk |
|---|---|---|---|---|---|---|
| **X1** | Admin chrome — area tier | constraint | `AdminNav` renders `<nav aria-label="Admin areas">` (`AdminNav.jsx:60`) listing **ten flat horizontal pills**: `Overview, Content, People, Members, Appearance, Notifications, Integrations, Packages, Features, Settings` (`AdminNav.jsx:8-19`) | Eight-**group vertical** 224px rail: `Dashboard · Moderation · Content · People · Appearance · Notifications · Integrations · Settings` (`templates/admin/_nav.php:7-50`), browser-pinned by `tests/browser/admin-dashboard.spec.ts:60-70` | Rail stays (ADR 0023 item 6). **Record the IA divergence** — the design has an admin-IA opinion and it is a different one; D1/D2 implied it had none. | medium |
| **X2** | Suppressed addresses | feature-added | 4th `<th>` is **empty**: `<th scope="col" style="…"></th>` (line **174**) | `<th scope="col"><span class="sr-only">Actions</span></th>` (`email.php:177`) | **KEEP production.** ADR 0023 item 5 enumerates `empty <th>s` among the accessibility pockets it fixed; verbatim transcription is a binding-decision revert. | high |
| **X3** | Both tables | copy | `text-align: right` on **Attempts** and **Action**, header *and* cell: lines **117, 119, 133, 135**, plus the suppression action cell at **182** | `table.audit` left-aligned throughout | Right-align both columns, header and cell. | low |
| **X4** | Delivery log / suppressions | copy | **To** → `--font-mono` `.78rem` `--text-body` (125); **Attempts** → `--font-mono` `.8rem` `--text-muted` (133); **Subject** → `--text-muted` + `text-wrap: pretty` (134); suppression **Email** → `--font-mono` `.8rem` (179) | All four in default table ink/face | Adopt. Extends D68, which covered only When/Since. | low |
| **X5** | Both forms | copy | `suppressError` sits **inside** the form, right of `Suppress`, `padding-bottom: 9px` (**167**); `publishError` sits **beside** `Publish banner` in the same flex row (**229**, row at **227**) | `field_error()` rendered *after* the form (`email.php:171`) and *under* the textarea (`announcements.php:41`) | Restructure — but the 422/429 `->errors`/`->old` round-trip must survive intact (`AdminEmailController.php:79-82`, `AnnouncementService.php:161-167`). Anti-draft-loss is non-negotiable. | medium |
| **X6** | Suppressed addresses | copy | `<table>` then a **sibling `<p>`** below it (**187–189**) | `colspan` row replacing the tbody (`email.php:193-195`) | Adopt the sibling-paragraph structure, as D36 already specifies for the delivery log. | low |
| **X7** | Whole screen | copy | Card: `padding: 18px 20px` (`18px 20px 10px` where a table is last), `--surface-raised`, `1px --border-hair`, `--radius-lg`; `--shadow-sm` on **exactly two** sections — Delivery log (**76**) and Publish a banner (**211**). h2: `--font-display` 500 `1.25rem` `--text-strong`, `margin: 0 0 10px\|12px\|14px`. Stat card `padding: 14px 16px`, label `.68rem`/`.1em`. Tabs: `.84rem` `--font-label` `.03em`, active `border-bottom: 2px solid --gold-500` with `margin-bottom: -1px` against the nav's `1px --border-hair` (**28–33**). Tables `width:100%; border-collapse: collapse; font-size:.9rem` (**111, 169, 237**) | `.stat-card` is `13px 15px` (`app.css:3444`); `.stat-label` `.62rem`/`.08em` (`app.css:3459`); no sub-tab pattern exists anywhere | Enumerate these in the slice or they ship unimplemented. D21 covered surface/radius/numeral only. | low |

**Guards (not ledger rows — no count impact)**

- **X8 → slice N3.** `tests/browser/gate-a.spec.ts:1266-1269` asserts `getByRole('heading', …)` for
  `Email delivery`, `Queue status`, `Delivery log`, `Suppressed addresses`. D20 ("unbox the section, restyle
  the h2 as a caps eyebrow") is safe **only because the design keeps it an `<h2>`** (line **65**). Production's
  `.eyebrow` idiom is a `<span>`. **Do not change the tag.**
- **X9** → folded into §4 (F1/F2/F3 citations dead; F4–F12 all real and correctly transcribed).
- **X10 → slice N3.** `email.php:97` hard-codes the status list instead of reading
  `AdminEmailController::STATUSES` (`:20`), which drives validation *and* CSV export (`:142-143`). D27's
  reorder is behaviourally safe but deepens a two-copy drift. One line in the slice.
- **D2's action needs IA sign-off, not a low-risk `copy` label.** An anchor tab strip inside `.admin-pane`
  would be a *third* rendering of the same two destinations (rail group title + rail links + strip). `grep -rn
  "text-tabs\|sub-tab\|tablist" templates/ public/assets/app.css` → zero hits: this is net-new IA on top of an
  ADR-locked, browser-pinned nav. It does not break `admin-dashboard.spec.ts:69` or `gate-a.spec.ts:1261`
  (both scoped to the rail), but it is not a free `copy`.
- **Citation nit:** `app.css:2839` is `.admin .subnav {`, not the `.admin` grid declaration (D6, D21).
- **Dead in the design too:** `@keyframes anRise` (line 16) is declared and **never referenced** — no
  `animation:` anywhere in the file. Nothing to port, and nothing to feel bad about not porting.
- **Non-portable tooling attributes** (do not transcribe): `data-screen-label` (20), `hint-size`,
  `hint-placeholder-val`, `hint-placeholder-count`, `sc-if`, `sc-for`, `x-import`, `style-hover`, `style-focus`.

---

## 6. Rows explicitly re-confirmed against the **current** file (no change)

The resync touched only the chrome. Every substantive body row in D survives, verified by grep at the −12
lines: **D4, D7–D17, D19–D42, D44–D68**, and fiction rows **F4–F12**. In particular the four highest-risk
calls all hold: **D8** (the F24 three-fact block — the design at **40–52** is still unconditional, no
fails-closed state modelled), **D59** and **D65** and **D66** (rate limits + flag gates — production-only,
keep), and both `feature-removed` calls **D35** (`const BAD = ['failed','bounced','complained']` at **300**;
`bounced`/`complained` seed rows at **272/282**) and **D37** (`The log keeps thirty days…` at **146**).

The design still contains **7** delivery-log columns (113–119) — production's 8th, `Detail`, remains
`feature-added` (D29). The design still contains the `verify`/`reset` kind options (**95/96**) that production
can never populate (D26).

---

## 7. Corrected classification counts

| Classification | D report | Δ | **Corrected** |
|---|---|---|---|
| copy | 39 | −1 (D3 struck) −1 (D5 → constraint) +1 (D18b split) +5 (X3, X4, X5, X6, X7) | **43** |
| feature-added | 13 | +1 (X2) | **14** |
| feature-removed | 2 | — | **2** |
| feature-changed | 5 | — | **5** |
| constraint | 9 | +1 (D5 in) +1 (X1) | **11** |
| **Total rows** | **68** | **+7** | **75** |

---

## 8. Slice-plan corrections

- **N1** — delete the `Operator desk · Notifications` deliverable and its PHPUnit assertion (I1). Delete the
  D5 restyle (I2). What remains of N1 is the tab strip (D2, pending IA sign-off) and pane spacing (D6 with the
  corrected 22px / 2.1rem / 16px values). N1 may collapse into N3 if the tab strip is declined.
- **N3** — add the **X8** guard (`Queue status` must stay an `<h2>`) and the **X10** note.
- **N4** — replace *"the round-trip test (`:124-133`) updated for `Release`"* with **"`tests/browser/gate-a.spec.ts:1281`
  updated for `Release` in the same commit"**, and add the **X2** guard (do not transcribe the empty `<th>`;
  keep `<span class="sr-only">Actions</span>`). Add **X5**/**X6**.
- **N5** — add **X5** (publishError placement) without breaking the 422/429 slot.
- **N6** — the ADR should additionally record: the design system's removal of the `Operator desk · Area`
  kicker and the resulting **deletion** candidacy of the three production eyebrows
  (`dashboard.php:6`, `settings.php:14`, `branding.php:11`), and the X1 admin-IA divergence.
