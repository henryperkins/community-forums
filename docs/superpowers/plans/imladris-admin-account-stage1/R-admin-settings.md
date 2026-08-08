# R — admin-settings: correction addendum to D-admin-settings.md

**Status:** this addendum supersedes `D-admin-settings.md` wherever the two disagree, and folds in
every finding of `V-admin-settings.md`. It is the single corrected source for this screen. It does
**not** re-derive the diff — D's production research is sound and survives; what follows corrects
line anchors, strikes dead citations, inverts the rows the chrome refresh flipped, and re-counts.

---

## 0. Anchor facts (verified against disk, 2026-08-03)

| | D's premise | V's claim | **Truth on disk** |
| --- | --- | --- | --- |
| File length | 299 lines | 288 lines | **287 lines** (`wc -l` = 287; line 287 is `</html>` + trailing newline) |
| Markup block | ends :216 | ends :204 | **`<x-dc>` :9 → `</x-dc>` :204** |
| x-dc script | :217-297 | :205-285 | **:205-285**; `</body>` :286, `</html>` :287 |
| Chrome | inline sticky topbar :22-28 | x-import :22 | **`<x-import … AdminNav area="settings" hint-size="100%,101px">` at :22** |

D read the pre-refresh file (`git show HEAD:…` = 299 lines). The refresh is `+4 / −16` (net −12) and
is still uncommitted (`git status` → ` M docs/design-system/imladris/templates/admin-settings/AdminSettings.dc.html`).
V's "288 lines" is off by one (it counted the trailing empty line); **287** is correct.

**The exact upstream edit, from `git diff`:**

- old :22-28 (7-line sticky 58px topbar: eight-point-star SVG + `Imladris` wordmark + `Back to the
  council` exit link) → **replaced by one line**, new :22, the shared `AdminNav` x-import.
- old :30 content column `padding: 26px 28px 110px` → new :24 `padding: 22px 28px 110px`.
- old :32-38 (the two-column head block: `<span>Operator desk · Settings</span>` at :34, H1 at :35
  `2.4rem` / `margin: 7px 0 0`, and the `Admin mode` pill at :37) → **replaced by the bare H1**,
  new :26, `2.1rem` / `margin: 0`.
- old :40 sub-nav `margin: 22px 0 0` → new :28 `margin: 16px 0 0`.
- Everything from old :40 onward shifts by **−12**. Nothing in the body changed.

**Structural consequence:** the H1 at :26 is the **first child of the content column**, immediately
after the x-import. There is **no eyebrow, no page-level pill and no page-owned topbar anywhere on
this screen.** The design system documents the deletion verbatim at
`docs/design-system/imladris/components/admin/admin.card.html:43`:

> "…this chrome is 10px *shorter*: the redundant "Operator desk · Area" kicker is gone, the mode pill
> moved into the identity row, and the heading drops from 2.4rem to 2.1rem."

Grep confirms it: `"Operator desk"` now appears **exactly once in the entire design mirror** — in the
sentence above, describing its own removal. `"Admin mode"` appears only in `components/admin/AdminNav.jsx`,
`ui_kits/admin/AdminApp.jsx` and `_ds_bundle.js` — in **no screen template**.

---

## 1. Corrected section order and line map

### 1a. Current design order, top to bottom, verbatim headings

| # | Element | Verbatim string | Line |
| --- | --- | --- | --- |
| **D0** | **Shared chrome** — `<x-import … AdminNav area="settings" hint-size="100%,101px">` | *(renders `Imladris` wordmark · `Back to the council` · `Admin mode` pill · the ten-area tier — all owned by the component, none of it on this page)* | **:22** |
| D3 | Page H1 — first child of the content column, `2.1rem`, `margin: 0` | `General & intelligence` | **:26** |
| D5 | Local tab nav, `aria-label="Settings sections"`, `margin: 16px 0 0` | `General & registration` · `Thread Intelligence` | **:28-33** (buttons :29-32) |
| | **Tab A — `sc-if showGeneral`**, grid `repeat(2, 1fr); gap: 16px; padding-top: 24px` | | **:36-78** (grid :37) |
| D6 | `<section>` H2, `1.3rem` | `Identity` | **:39** |
| D7 | intro, `max-width: 48ch` | `The name the council goes by — in the topbar, in every email, and on the sign-in page.` | **:40** |
| D8 | field label (caps skin), input `maxlength="80"` | `Community name` | **:43** (input :44) |
| D9 | submit | `Save name` | **:47** |
| D10 | success chip `role="status"` | `Saved.` | **:48** |
| D11 | error `role="alert"` | `The council needs a name.` | **:49** |
| D12 | `<section>` H2, `1.3rem` | `Registration` | **:55** |
| D13 | intro, `max-width: 48ch` | `Choose whether new members can join directly, need an invitation, or cannot register.` | **:56** |
| D14 | field label (caps skin) | `Registration mode` | **:59** |
| D15 | options | `Open — anyone can register` / `Invite only (invitation required)` / `Closed (no new sign-ups)` | **:61-63** |
| D16 | help | `Existing members can continue signing in in every mode.` | **:65** |
| D17 | conflict banner `role="alert"`, rust wash + 3px left rule | `Registration mode is "invite" but the invitations feature is off — registration is effectively closed.` | **:68** |
| D18 | checkbox | `Invitations feature is enabled` | **:70** |
| D19 | submit | `Save registration mode` | **:72** |
| D20 | success chip | `Saved.` | **:73** |
| | **Tab B — `sc-if showIntel`**, `padding-top: 24px` | | **:81-200** |
| D21 | intro, `max-width: 70ch` | `Automated context for long topics. The council approves; the model proposes. Everything it writes is evidenced below, and the egress brake is one button away.` | **:83** |
| | status rail grid `repeat(3, 1fr); gap: 14px` | | **:85** |
| D22 | status card 1, left rule `--success` | `Provider` / `Healthy` / `No latch set` | **:86-90** (label :87, value :88, detail :89) |
| D23 | status card 2, left rule `--info` | `Heartbeat` / `Nominal` / `Last run 6 minutes ago` | **:91-95** (:92, :93, :94) |
| D24 | status card 3, left rule `--warning` | `Generation` / `{{ generationState }}` / `Global provider egress brake` | **:96-100** (:97, :98, :99) |
| D25 | `<section>` H2, `1.25rem` | `Recovery controls` | **:104** |
| D26 | outline buttons | `{{ pauseAction }}` · `Retry provider configuration` | **:106-107** |
| D27 | status chip `role="status"` | `Health latch cleared.` | **:108** |
| D28 | helper, `max-width: 62ch` | `Provider retry clears only the current health latch. Configure credentials outside this page.` | **:110** |
| D29 | `<section>` H2, `1.25rem` | `Daily budget` | **:114** |
| D30 | meter row 1 | `Calls` / `{{ callsLabel }}` / 8px pill track, `--accent` fill `width: 67%` | **:118-121** (label :118, value :119, track :121) |
| D31 | meter row 2 | `Input tokens` / `{{ tokensLabel }}` / `--gold-500` fill `width: 68%` | **:125-128** (:125, :126, :128) |
| D32 | reset line, mono `.78rem` | `Resets 2026-08-03 00:00 UTC` | **:131** |
| D33 | `<section>` H2 in the caps-eyebrow skin (`.68rem`/`.16em`/uppercase/`--text-faint`) | `Queue states` | **:135** |
| D34 | 5-up queue grid, count-first tiles | `{{ q.count }}` → `{{ q.label }}` → `{{ q.unit }}` | **:136-144** (tile :138-142; count :139, label :140, unit :141) |
| | contract + evidence grid `330px 1fr; gap: 16px; align-items: start` | | **:147** |
| D35 | `<section>` H2, 330px column | `Generation contract` | **:149** |
| D36 | `<dl>` | `Model` / `Reasoning effort` / `Prompt version` | **:150-154** (rows :151, :152, :153) |
| D37 | `<section>` H2, 1fr column, `box-shadow: var(--shadow-sm)`, `padding: 18px 20px 10px` | `Recent generation evidence` | **:157** (card) / **:159** (H2) |
| D38 | segmented filter | `All` · `Failed only` | **:160-165** |
| D39 | run count, right-aligned | `{{ evidenceLabel }}` | **:166** |
| D40 | table head | `When` · `Topic` · `Outcome` · `Input tokens` (right) · `Digest` | **:168-175** (`<th>`s :170-174) |
| | body rows | | **:177-188** (outcome pills :182 done / :183 rust) |
| D41 | filtered empty state, centred `34px 20px` | H3 `Nothing has failed` · P `Every generation in the retained window completed.` | **:191-196** (H3 :193, P :194) |

**Struck from the order table: D1 (topbar), D2 (eyebrow), D4 (head pill).** They are not on this
screen in any form. D0 replaces them.

### 1b. Every D citation → corrected line

Uniform rule: **old ≥ 40 ⇒ new = old − 12.** Head citations do not map — they are deleted.

| D cites | Corrected | | D cites | Corrected |
| --- | --- | --- | --- | --- |
| :22-28 topbar | **struck** | | :143 | **:131** |
| :24 star SVG | **struck** | | :147 | **:135** |
| :25 `Imladris` | **struck** | | :148-156 | **:136-144** |
| :27 `Back to the council` | **struck** | | :151-153 | **:139-141** |
| :30 content column | **:24** | | :159 | **:147** |
| :34 `Operator desk · Settings` | **struck** | | :161 | **:149** |
| :35 H1 | **:26** | | :163-165 | **:151-153** |
| :37 `Admin mode` | **struck** | | :171 | **:159** |
| :40-45 nav | **:28-33** | | :172-177 | **:160-165** |
| :41-44 tab buttons | **:29-32** | | :178 | **:166** |
| :48-90 Tab A | **:36-78** | | :181-187 | **:169-175** |
| :49 grid | **:37** | | :186 `Digest` th | **:174** |
| :51 / :52 | **:39** / **:40** | | :191 | **:179** |
| :53 form | **:41** | | :192 | **:180** |
| :55 | **:43** | | :194-195 | **:182-183** |
| :59 / :60 / :61 | **:47** / **:48** / **:49** | | :197 | **:185** |
| :67 / :68 | **:55** / **:56** | | :198 | **:186** |
| :69 form | **:57** | | :203-208 | **:191-196** |
| :71 | **:59** | | **x-dc :219-226** | **:207-214** |
| :73-75 | **:61-63** | | x-dc :243 | **:231** |
| :77 | **:65** | | x-dc :257 / :258 | **:245** / **:246** |
| :80 | **:68** | | x-dc :266 / :267 | **:254** / **:255** |
| :82 | **:70** | | x-dc :270 / :271 | **:258** / **:259** |
| :84 / :85 | **:72** / **:73** | | x-dc :273 | **:261** |
| :93-212 Tab B | **:81-200** | | x-dc :276-277 | **:264-265** |
| :95 | **:83** | | x-dc :279-285 | **:267-273** |
| :98-112 rail | **:86-100** | | x-dc :287-290 | **:275-278** |
| :98 / :103 / :108 rules | **:86** / **:91** / **:96** | | x-dc :292 / :293 | **:280** / **:281** |
| :100-101 / :105 / :106 | **:88-89** / **:93** / **:94** | | :13-17 helmet | **:13-17** *(unchanged)* |
| :116-122 | **:104-110** | | | |
| :118-119 / :120 / :122 | **:106-107** / **:108** / **:110** | | | |
| :126 | **:114** | | | |
| :130-133 / :137-140 | **:118-121** / **:125-128** | | | |
| :131 / :138 labels | **:119** / **:126** | | | |

---

## 2. Rows whose ACTION is INVERTED by the chrome change

### I1 — Row 4 (page-head eyebrow). **Fully inverted. Highest-impact correction on this screen.**

- **D said (copy):** *"Adopt `Operator desk · Settings` on settings.php. Leave TI's `Operations` (or
  promote to `Operator desk · Thread Intelligence`). Also add the missing eyebrow skin: design is
  `.68rem` in `var(--gold-ink)` at `.18em`."*
- **Now:** the design has **no eyebrow**, and its own component spec calls the kicker *"redundant"*
  (`admin.card.html:43`). The `.68rem`/`--gold-ink`/`.18em` spec D quotes existed only inside the
  deleted block — **there is no eyebrow spec left in this screen to adopt.**
- **Corrected action:** **DELETE** `<span class="eyebrow">Operator desk</span>`
  (`templates/admin/settings.php:14`) and `<span class="eyebrow">Operations</span>`
  (`templates/admin/thread_intelligence.php:6`). The H1 becomes the first thing in the pane.
  Do **not** author a gold-ink eyebrow class. Keep classification **copy**; the direction reverses.
- Blast radius beyond this screen: `.eyebrow` (`app.css:37-43`) and `.admin-head .eyebrow`
  (`app.css:2822-2824`) are shared by every admin page — the deletion is per-template markup, not a
  CSS removal, until all six admin screens land.

### I2 — Row 5 (head pill). **Was scored "— (match) / None." Now a real difference.**

- **Now:** the design has no page-level pill. `Admin mode` survives only as
  `AdminNav.jsx:47 modeLabel = 'Admin mode'`, rendered once in the shared identity row
  (`.admin-bar-mode`, `components.css:333`), and `admin.card.html:36-38` demonstrates it being
  nulled (`modeLabel={null}`) — it is chrome configuration, not page content.
- **Production:** `<span class="pill pill-admin">Admin mode</span>` is repeated in the `<header
  class="admin-head">` of **every** admin template (`settings.php:17`, `thread_intelligence.php:9`),
  pushed right by `app.css:2832-2834`.
- **Corrected action:** the mode indicator belongs to the shared admin chrome, not the page head.
  Production's shared chrome is the ADR-0023-locked **vertical** rail (`templates/admin/_nav.php`),
  which has no identity row to receive it — so this cannot be executed as a straight move.
  **Decision required (log in the ADR):** either (a) render `Admin mode` once, at the top of
  `admin/_nav.php`, and delete the per-page pills, or (b) keep the per-page pill and record it as a
  deliberate divergence from the refreshed chrome. Do **not** score it a match.
- **Reclassified: match → feature-changed** (same concept, different owner).

### I3 — Row 1 (topbar) and design-order rows D1/D2/D4. **Premise and rationale both inverted.**

- **D said (constraint):** *"Do not port the topbar. The design's bar is the prototype's chrome, not
  a page section."* V refuted the rationale (R2); the refresh refutes the premise too.
- **Now:** there is no per-screen topbar to decline. The chrome is a **first-class, versioned system
  component**: `components/admin/AdminNav.jsx` (`ADMIN_AREAS` :8-19, identity row :50-58, tier
  :59-74), canonical CSS `components.css:328-342`, spec card `components/admin/admin.card.html:1`.
- **Corrected action, split in two:**
  - **1a (constraint, keep):** the identity row — star mark, `Imladris` wordmark, `Back to the
    council` exit — is not portable. `templates/layout.php:27,37-40` already renders the operator's
    own `$brand['name']` / `$brand['logo_path']`, and the app shell already provides the way out.
    Do not build an admin-specific exit link.
  - **1b:** the **ten-area pill tier** is portable design and D never mentions it. That is V's **M2**
    — carried into §5 below as a real `feature-changed` row, not a non-difference.

### I4 — §3 fiction rows F1, F2, F3. **Citations dead; entries survive re-sourced.**

| | D cites | Corrected source |
| --- | --- | --- |
| F1 `Imladris` wordmark | `AdminSettings.dc.html:25` | **`components/admin/AdminNav.jsx:53`** — not on this screen |
| F2 eight-point star SVG | `:24` | **`AdminNav.jsx:26-30`** (`Mark()` → `EightPointStar`) — not on this screen |
| F3 `Back to the council` | `:27` | **`AdminNav.jsx:46`** (`backLabel` default) — not on this screen |

The fiction is real and must still never ship; it is simply **the shared chrome's fiction, not this
screen's**, and it must be de-fictionalised once in the AdminNav port rather than three times per
screen. F1's own word *does* still appear in this file — see F17 below.

### I5 — Row 52 and slice S5. **Inverted by V R1 (verified independently).**

- **D said:** *"Does the design represent Feature flags? **NO.**"* → row 52 `feature-added`, and S5
  proposes inventing an idiom for `/admin/features`.
- **Verified false.** `docs/design-system/imladris/templates/admin-features/AdminFeatures.dc.html`
  exists — **492 lines**, `x-import … AdminNav **area="features"**` at **:22**, H1 `Features & badges`
  at **:26**, three inner tabs `Feature flags` · `Badge rules` · `Custom emoji` at **:29-34**,
  read-only intro at **:47**. `AdminNav.jsx:17` lists `features` as a **sibling top-level area**, not
  a Settings sub-tab. D inferred the gap from this screen having two inner tabs — an invalid
  inference about a ten-area console.
- **Corrected action:** **row 52 is out of scope for admin-settings** and is removed from this
  screen's table; `/admin/features` gets its own diff against `AdminFeatures.dc.html` (which also
  models Badge rules and Custom emoji as sibling tabs — a further IA difference production splits
  across three routes). **S5 is struck.**

---

## 3. Fabricated / no-longer-present quoted strings

Every quoted design string in D was re-checked with a literal `grep -F` against the current file.

| D row | Quoted as design content | Verdict |
| --- | --- | --- |
| D1 / row 1 / F1 / F2 / F3 | `Imladris` wordmark, star SVG, `Back to the council` @ :22-28 | **Absent.** Zero hits in `AdminSettings.dc.html`. Lives in `AdminNav.jsx`. |
| D2 / row 4 | `Operator desk · Settings` @ :34 | **Absent — from the entire design mirror.** The only mirror hit for `Operator desk` is `admin.card.html:43` documenting its deletion. |
| D4 / row 5 | `Admin mode` @ :37 | **Absent from this file.** Hits only in `AdminNav.jsx`, `ui_kits/admin/AdminApp.jsx`, `_ds_bundle.js`. |
| row 4 | eyebrow skin `.68rem` / `var(--gold-ink)` / `.18em` | **Absent.** Only existed inside the deleted head block. |
| D3 / row 3 | H1 at `2.4rem`, `margin: 7px 0 0` | **Stale.** Now `2.1rem`, `margin: 0` @ :26. |
| header | "299 lines; markup ends :216; script :217-297" | **Stale.** 287 / :204 / :205-285. |
| row 55 | "~200 inline `style=` / `style-hover=` attributes" | **Overstated.** Actual in the markup block: **113** `style="` + **8** `style-hover="` + **2** `style-focus="` = **123**. The `<helmet><style>` at :13-17 is real. |
| V §0 | "288 lines" | **Off by one.** 287. |
| V M7 | "the two General cards" as an example of *absent* elevation | **Wrong example.** `box-shadow: var(--shadow-sm)` is present at :38, :54, :157, :161, :163. Corrected statement in §5 (M7). |

Everything else D quotes — all of Tab A, all of Tab B, all fourteen x-dc sample values — **verified
present** at the corrected lines. This was staleness, not invention.

---

## 4. V-report findings folded in

| V | Finding | Disposition |
| --- | --- | --- |
| R1 | `/admin/features` is modelled (`AdminFeatures.dc.html`, 492 lines) | **Accepted, verified.** → I5. Row 52 out of scope; S5 struck. |
| R2 | The topbar is a first-class component, not prototype chrome | **Accepted, verified.** → I3. |
| R3 | Row 2's rationale ("per-screen elision") is false; the two-level IA is specified (`admin.card.html:30`) | **Accepted.** Row 2's *conclusion* stands (keep the ADR-0023 rail; the design's `onClick` tab state is PE-illegal). Its *reason* is replaced: this is an honest **feature-changed** against a fully specified design IA. PE forbids client-state tabs, **not** a server-rendered strip of links. |
| R4 | `human_datetime()` is absolute (`helpers.php:64-76` → `gmdate('M j, Y \a\t H:i')`), cannot yield `Last run 6 minutes ago`; no relative-time helper exists | **Accepted.** Row 23 / F12 must budget a new `human_relative()` helper (+ unit tests) or drop the relative phrasing and render the absolute `human_datetime($dashboard['heartbeat']['completed_at'])`. **Recommend: absolute** — operators diff against logs. |
| R5 | Row 52's "ADR 0021 deferral #7" is a miscite (that deferral is the `link_previews` console) | **Accepted.** Cite `AdminFeatureController.php:54-63` + `docs/runbooks/operations.md` §2 + `AdminFeatures.dc.html:47`. Must not reach ADR 0024. |
| R6 | The structured summary narrowed `Needs attention` to "only surface for flags_corrupt and configuration_warnings" | **Accepted.** Binding text is row 20's body: **keep all 13 literal warnings + the `configuration_warnings` passthrough** (`ThreadIntelligenceAdminService.php:149-190`). Correct count: **13 + passthrough** (not "10", not "14"). |
| R7 | Counts wrong (copy 19 not 24; constraint 13 not 11) | **Accepted** as the baseline; re-derived in §6. |
| MC1 | Row 25 `copy` → feature-added / owned defect fix | **Accepted.** The design's rules are **static identity colours** (`:86` `--success`, `:91` `--info`, `:96` `--warning`) with no state logic in the x-dc script. Copying verbatim would leave a green rule under `Not ready` *and* pin Generation to `--warning` forever. State-driven modifiers are a production improvement **beyond** the design. |
| MC2 | Row 37 `constraint` → match | **Accepted.** `thread_intelligence.php:93-95` already echoes `$dashboard['model'] / ['reasoning_effort'] / ['prompt_version']`. `claude-sonnet-4-6` / `medium` / `ti.summary.v7` are prototype sample data. Reduce to a "match — do not regress" note; same for F7-F9, F15, F16. |
| MC3 | Row 51 (loading state) is a non-difference | **Accepted. Dropped.** |
| MC4 | Row 52 out of scope | **Accepted.** → I5. |
| MC5 | Row 43's three-register pill exceeds the design's two (`:182` done, `:183` rust) | **Accepted.** Keep the three registers (`dead` and `rejected` rendering as `state-pending` today is misleading) but record it explicitly as an **added deviation on top of** `feature-changed`, not silent fidelity. |
| M1-M10 | Missed differences | **Accepted, with M7 corrected** — see §5. |
| fiction | x-dc `siteName: 'Imladris'` missed | **Accepted** as **F17**, `AdminSettings.dc.html:220`. |
| V §4 | ~30 production citations re-verified | No action. One nit V itself flagged: the `General & registration` assert is `AppAdminDashboardRemediationTest:219`, the validation-string assert `:220` (D said `:221`). |

---

## 5. Added rows

**M1** — production's orphan `.pane-intro` (`settings.php:23`, *"Manage the community name and who can
create an account. Each form saves only its own setting."*). The design's General tab has **no**
pane-level intro (per-card intros only, :40 / :56); a pane intro exists only on the TI tab (:83).
D listed it as P5 and never classified it. → **copy** (delete, or justify keeping it).

**M2** — the **ten-area pill tier**. Design: `AdminNav.jsx:8-19` (Overview · Content · People ·
Members · Appearance · Notifications · Integrations · Packages · Features · Settings), horizontal
pills in a sticky block, `aria-label="Admin areas"` (:59), `aria-current="page"` on the active item,
CSS `components.css:337-342`, tier scrolls horizontally below ~900px by design.
Production: 8 groups / 26 destinations in a 224px vertical rail (`templates/admin/_nav.php:9-49`),
locked by ADR 0023 item 6. → **feature-changed** (ADR-locked IA; production's IA wins, and the ADR
must say so explicitly rather than by omission).

**M3** — field-label skin: `Community name` (:43) and `Registration mode` (:59) are lapidary caps
(`.68rem`, `letter-spacing: .1em`, `text-transform: uppercase`, `--text-faint`). Production ships a
default `.field > span`. → **copy**.

**M4** — case treatment of card labels: design status labels (:87, :92, :97) and queue-tile labels
(:140) are **not** uppercase; production `.queue-card-head` sets `text-transform: uppercase`
(`app.css:3002-3008`). D covered the count-first *order* (row 34) but not the case. → **copy**.

**M5** — H1 scale: design `2.1rem` / font-display 500 / `-0.01em` / `margin: 0` (:26); production
`.admin-head h1 { font-size: 1.9rem }` (`app.css:2825-2827`). → **copy**.

**M6** — H2 scale split: `1.3rem` for the two General cards (:39, :55); `1.25rem` for every TI
section (:104, :114, :149, :159); `1.15rem` for the empty-state H3 (:193). → **copy**.

**M7 (corrected)** — elevation split. `box-shadow: var(--shadow-sm)` is carried by exactly five
elements: both General cards (**:38, :54**), the evidence card (**:157**), and the two *active*
segmented-filter pills (**:161, :163**). It is **absent** from the three TI section cards (:103,
:113, :148), the three status tiles (:86, :91, :96) and the queue tiles (:138). So the rule is
"forms and the primary evidence surface are raised; status/telemetry surfaces are flat" — not
V's "the General cards have none". → **copy**.

**M8** — measure caps: card intros `48ch` (:40, :56), TI intro `70ch` (:83), recovery helper `62ch`
(:110), evidence-empty block centred at `padding: 34px 20px` (:192). → **copy**.

**M9** — the recovery controls are bare `<button onClick>` with **no form** (:106-107); production
ships two `<form method="post">` + `csrfField()` (`thread_intelligence.php:49-60`). D scored row 26 a
pure match and never recorded the constraint. → **constraint** (CSRF + PE; buttons stay in forms).

**M10** — the design's queue unit is a flat `threads` for every tile (x-dc :268-272); production
pluralises (`thread_intelligence.php:85`). → **feature-added** (keep production's).

**N1** *(new here)* — the design sets `html { scrollbar-gutter: stable; }` in `<helmet>` (**:14**),
killing the horizontal jump when a pane grows past the viewport. Production sets `scrollbar-gutter`
in exactly one place, `.thread-scroll` (`app.css:1880`), never on `html`. → **copy**.

**N2** *(new here)* — page-head divider. Production `.admin-head` carries `margin-bottom: 20px;
padding-bottom: 16px; border-bottom: 1px solid var(--border-hair)` (`app.css:2813-2821`). The
refreshed design has **no head divider at all**: the bare H1 (:26) sits 16px above the sub-nav, whose
own `border-bottom` (:28) is the only hairline in the head region. Deleting the eyebrow (I1) without
also dropping the `.admin-head` rule leaves two stacked hairlines. → **copy**.

**N3** *(new here)* — content-column geometry. Design: one centred column, `max-width: 1100px;
margin: 0 auto; padding: 22px 28px 110px` (:24) beneath a full-bleed sticky bar of `101px`
(x-import `hint-size`). Production: `.admin` is a `224px minmax(0, 1fr)` grid, `max-width: 1260px;
padding: 24px 28px 64px` (`app.css:2800-2812`). The measure and the generous 110px bottom rail cannot
be adopted without M2's IA change. → **constraint** (blocked by the ADR-0023 rail).

**F17** *(new fiction)* — x-dc `siteName: 'Imladris'` (**:220**) is the Community-name field's sample
value. Same word as F1 and the one most likely to be transcribed straight into a placeholder or a
test fixture. Production must seed from `$site_name`.

**Note on row 55** — the `<helmet><style>` (:13-17) declares `@keyframes asRise` which is
**referenced nowhere** in the markup: dead prototype CSS. Do not port it, and do not invent an
entrance animation to justify it.

---

## 6. Corrected classification counts

Starting from D's 56 rows (V's recount: copy 19 · feature-added 13 · feature-removed 2 ·
feature-changed 3 · constraint 13 · match 6).

| Change | Effect |
| --- | --- |
| Row 51 dropped (MC3) | constraint −1, rows −1 |
| Row 52 out of scope (R1/MC4) | feature-added −1, rows −1 |
| Row 5 match → feature-changed (I2) | match −1, feature-changed +1 |
| Row 25 copy → feature-added (MC1) | copy −1, feature-added +1 |
| Row 37 constraint → match (MC2) | constraint −1, match +1 |
| Rows 1, 4 rewritten, bucket unchanged (I1, I3) | — |
| M1, M3, M4, M5, M6, M7, M8 added | copy +7 |
| N1, N2 added | copy +2 |
| M2 added | feature-changed +1 |
| M9 added | constraint +1 |
| N3 added | constraint +1 |
| M10 added | feature-added +1 |

| bucket | D claimed | V recount | **corrected** |
| --- | --- | --- | --- |
| copy | 24 | 19 | **27** |
| feature-added | 13 | 13 | **14** |
| feature-removed | 2 | 2 | **2** |
| feature-changed | 3 | 3 | **5** |
| constraint | 11 | 13 | **13** |
| match (not a deviation) | 6 | 6 | **6** |
| **total rows** | 56 | 56 | **67** |

27 + 14 + 2 + 5 + 13 + 6 = 67 ✓  (56 − 2 removed + 13 added = 67 ✓)

`feature-removed` is unchanged and still exactly two — the `Invitations feature is enabled` checkbox
(row 16, design :70) and the `All` / `Failed only` evidence filter (row 39, design :160-165). Both
refusals stand; neither may be built in the adoption pass.

---

## 7. Slice corrections

- **S1** — add: delete the `Operator desk` eyebrow (I1) and drop the `.admin-head` divider (N2);
  add the caps field-label skin (M3), the `2.1rem` H1 (M5), the `1.3rem` card H2 (M6), the 48ch
  measure (M8), `scrollbar-gutter` (N1). **Remove** "adopt `Operator desk · Settings`" and "author
  the gold-ink eyebrow skin" — both now do the opposite of what the design says. Resolve the
  `Admin mode` pill question (I2) before styling the head.
- **S2** — add: delete the `Operations` eyebrow from `thread_intelligence.php:6` (I1). Row 23/F12
  must either budget a relative-time helper or ship the absolute `human_datetime()` value (R4).
  Row 25's state-driven left rules must be recorded as **beyond** the design (MC1).
- **S3** — unchanged. Add the label-case fix (M4) and the flat-vs-pluralised unit note (M10).
- **S4** — unchanged in substance. Row 37 becomes a "do not regress" assertion, not a change (MC2).
  Record MC5 (three registers vs the design's two) in the ADR.
- **S5 — STRUCK.** `/admin/features` has a finished design screen (`AdminFeatures.dc.html`, 492
  lines) and belongs to its own diff pass. Do not invent an idiom for it here.
- **S6 (ADR 0024)** — corrections: drop the ADR 0021 #7 miscite (R5); cite
  `AdminFeatureController.php:54-63` + `docs/runbooks/operations.md` §2 + `AdminFeatures.dc.html:47`.
  Add two new records: (g) the refreshed chrome deletes the per-screen eyebrow and moves the mode
  pill into shared admin chrome — production follows, and the `Admin mode` placement decision (I2)
  is recorded either way; (h) the design's ten-area horizontal tier (M2) is refused in favour of the
  ADR-0023 8-group vertical rail, recorded as a deliberate IA divergence rather than an omission.
- **New S0 (blocking, cheap)** — the eyebrow deletion touches all six refreshed admin screens.
  Land it as one cross-screen slice with a single runtime-digest refresh rather than six times, and
  pin it with a test asserting no `.admin-head .eyebrow` renders on the admin surface.
