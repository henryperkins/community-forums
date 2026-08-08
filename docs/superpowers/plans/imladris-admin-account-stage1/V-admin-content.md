# V — admin-content: adversarial verification of D-admin-content.md

Verified against files opened in full:

- `docs/design-system/imladris/templates/admin-content/AdminContent.dc.html` (**570 lines**, markup 1–293, `<script type="text/x-dc">` 294–568)
- `docs/design-system/imladris/components/admin/AdminNav.jsx`, `components/admin/admin.card.html`, `components.css`, `styles.css`
- `templates/admin/{structure,tags,structure_confirm,tag_merge_confirm,board_edit,_nav}.php`, `templates/layout.php`, `templates/partials/topbar.php`
- `src/Controller/{AdminController,TagController}.php`, `src/Service/AdminService.php`, `src/Repository/{TagRepository,BoardRepository}.php`, `src/Security/SecurityHeaders.php`
- `public/assets/app.css`, `database/migrations/{0002,0007,0048}`, `ADMIN.md` §4.5, `docs/adr/0021`, `docs/adr/0023`
- `tests/Integration/Core/{AppAdminStructureReorderTest,AppTagAdminTest,AppAdminTest}.php`, `tests/Integration/Admin/AppFieldErrorA11yTest.php`

**Verdict: the report is materially unsound at the top of the screen and reliable from the body down.**
Rows 10–46 (structure body, confirmations, tags, rosters) are accurate, well-cited and correctly
classified — I confirmed essentially every production `path:line` in that range. Rows 3–9 (the whole
page-head/chrome block) rest on a design file the peer did not actually read: one string is
**fabricated**, one measurement is the **superseded value**, one classification inverts what the
design models, and **every design line number from :24 onward is off by ~12**.

---

## 1. Refuted claims

### R1 — FABRICATED design string: the eyebrow `Operator desk · Content` (report row 5, state table, slice S1)

The report: *"`<span>Operator desk · Content</span>`, `.68rem`, `var(--gold-ink)`, `.18em` **:34**"*, action
*"Add `<span class="eyebrow">Operator desk · Content</span>` to both heads."*

`AdminContent.dc.html:34` is inside the `<sc-if value="{{ structureEmpty }}">` empty state. There is no
eyebrow anywhere in the file — line 24 opens the canvas `<div>` and line **26** is the bare `<h1>`:

```html
26  <h1 style="…font-size: 2.1rem;…">Boards &amp; tags</h1>
```

`grep -rn "Operator desk"` across the entire design system returns **one** hit, and it documents the
string's **deletion**:

```
components/admin/admin.card.html:43
  …this chrome is 10px shorter: the redundant "Operator desk · Area" kicker is gone,
  the mode pill moved into the identity row, and the heading drops from 2.4rem to 2.1rem.
```

Neither `components.css` nor `styles.css` defines any `.eyebrow`/kicker rule. No sibling
`admin-*.dc.html` has one (`AdminOverview.dc.html:24` x-import → `:29` bare `<h1>`, same shape).

Consequence: slice S1 would ship, as a "verbatim design adoption", chrome that Imladris explicitly
removed. Worse, verbatim adoption points the **opposite** way — production already ships this eyebrow
on other admin heads (`templates/admin/dashboard.php:6`, `templates/admin/branding.php:11` both render
`<span class="eyebrow">Operator desk</span>`), and `app.css:2822 .admin-head .eyebrow { display:block }`
exists to serve them. The real finding is "production has an eyebrow register the design retired",
not "production is missing an eyebrow".

### R2 — WRONG measurement: design h1 is 2.1rem, not 2.4rem (report row 6)

Report: *"One h1 `Boards & tags` for both tabs, **`2.4rem`**, `--font-display` :35"*, action *"adopt the
display-font scale"*. Actual `AdminContent.dc.html:26` → `font-size: 2.1rem`. `admin.card.html:12`
(`h1 { … font-size: 2.1rem … }`) and `:43` ("the heading drops from **2.4rem to 2.1rem**") confirm
2.4rem is the value Imladris *replaced*. Production is `1.9rem` (`app.css:2826`). Anyone implementing
from the report overshoots by 0.5rem and reinstates the discarded scale.

### R3 — FALSE feature-added: "the design has no rail at all" (report row 9)

Report: *"Admin rail — **feature-added** — Design has no rail at all; the tab strip is the entire navigation."*

`AdminContent.dc.html:22`:

```html
<x-import component-from-global-scope="ImladrisDesignSystem_c3e027.AdminNav" area="content" hint-size="100%,101px"></x-import>
```

`components/admin/AdminNav.jsx:8-19` declares **ten admin areas** (`overview, content, people, members,
appearance, notifications, integrations, packages, features, settings`) and `:40-76` renders a sticky
two-row bar: an identity row (`admin-bar-brand` / `admin-bar-exit` / `admin-bar-mode`) plus
`<nav className="admin-tier" aria-label="Admin areas">` with `aria-current="page"` on the active area.
All ten `admin-*.dc.html` templates import it. `admin.card.html:43` states the design intent outright:
*"The tier is a pill row, the page's own sections are underline tabs, and the page heading sits between
them — three signals keeping the two ranks apart."*

So the design models console navigation **in full**, and the two underline tabs are explicitly the
*second* rank beneath it. The difference against production's 8-group / 224px vertical rail
(`_nav.php:7-50`, `app.css:2800-2805`) is shape, not existence: **feature-changed** (or copy for the
anatomy), never feature-added. As written the row licenses "keep the rail, it's ours" — when the design
has an opinion about the rail that nobody has adjudicated.

### R4 — Design line citations are systematically wrong (~+12) and the file length is misstated

Report header: *"582 lines; markup 1–305, `<script type="text/x-dc">` 306–580"*. Actual: **570 lines**,
markup ends **:293**, script `:294`–`:568`.

| Report cites | Actually at | Section |
|---|---|---|
| :30 | :24 | 1100px canvas |
| :34 / :35 / :37 | — / :26 / — | eyebrow / h1 / pill |
| :41-44 | :28-33 | section tabs |
| :50 | :38 | structure intro |
| :52-54 | :40-42 | reorder error |
| :56-61 | :44-49 | structure empty state |
| :65-66 | :53-54 | category card + head gradient |
| :67 / :69-70 / :71 | :55 / :57-58 / :59 | rename input / arrows / delete |
| :74 / :77 | :62 / :65 | saved chip / name error |
| :80-103 | :68-91 | board rows |
| :82 / :85 / :87-88 / :89 / :90 / :92 | :70 / :73 / :75-76 / :77 / :78 / :80 | li / link / chips / archived / count / description |
| :97-99 | :85-87 | Edit / Archive / Delete |
| :109-118 / :112 | :96-106 / :100 | Add a category |
| :121-187 / :127-184 / :142 | :108-175 / :115-172 / :130 | Add a board |
| :196-213 / :200-207 | :184-201 / :188-195 | merge panel / impact dl |
| :216-236 / :221-235 | :203-224 / :209-223 | Add a tag |
| :239-253 | :226-241 | catalogue head |
| :255-283 / :266 / :278-280 | :243-270 / :254 / :266-268 | tag rows / uses / saved |
| :285-290 / :292-298 | :273-278 / :280-286 | tag empty / pager |
| :371-378 / :429 / :493 / :526 / :555-556 | :359-366 / :417 / :481 / :514 / :543-544 | x-dc logic |
| :308 / :312 / :317 | :296 / :300 / :305 | seed categories |

Only `@keyframes acRise :13-17` is correct. **Every design line reference in D must be re-derived
before it is used to drive an edit.** The report's own instruction was "quote strings verbatim, cite
path:line"; production citations survive this check, design citations do not.

### R5 — Topbar / back link / pill cited to the wrong file entirely (rows 3, 4, 7)

The anatomy is real but lives in the component, not the screen:

- `AdminNav.jsx:51-59` — mark, `backLabel = 'Back to the council'` (`:44`), `modeLabel = 'Admin mode'` (`:45`).
- `components.css:328-334` — `.admin-bar` sticky 58px block; `.admin-bar-mode { margin-left:auto; … border-radius: var(--radius-pill); background: var(--surface-review); color: var(--on-review); font-family: var(--font-label); font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; }`.

The report's colour/size figures for the pill are right — which proves it read `components.css` — yet it
attributed them to `AdminContent.dc.html:37`. It also **dropped `text-transform: uppercase`**, which
production's `.pill-admin` (`app.css:106`) does not have.

### R6 — "Re-skin `.pill-admin` to the review pair" preserves a placement the design abandoned (row 7)

The design has **no pill in the page body at all**: `AdminContent.dc.html` goes x-import (`:22`) →
canvas (`:24`) → `<h1>` (`:26`) → tabs (`:28`). The pill is `margin-left:auto` inside the *nav bar's*
identity row (`components.css:334`), and `admin.card.html:43` says so: *"the mode pill moved into the
identity row."* Production renders it in the page head on five templates
(`structure.php:11`, `tags.php:14`, `structure_confirm.php:6`, `tag_merge_confirm.php:6`,
`board_edit.php:6`) positioned by `app.css:2832 .admin-head .pill-admin { margin-left:auto }`.
"Re-skin in place" is a colour change to a component the design relocated — that is a copy difference
the report does not book.

### R7 — "No back link on any admin page" is scoped-to-`templates/admin` and misleading (row 4)

Admin templates set no `variant`, so `templates/layout.php:3` defaults to `app` and `:50-63` render
`partials/topbar` **and** `partials/sidebar` around the console. `templates/partials/topbar.php:11` is
`<a class="brand" href="/" …>` — a live route back to the forum on every admin page, plus the whole
forum sidebar. The design's exit link *replaces* that chrome; it does not add to it. Adding a second
back link into `.admin-head` (the report's action) matches neither the design's placement nor
production's existing affordance.

### R8 — "Design is a fixed 1100px desktop composition with unbreakpointed 2-col grids" is overstated (row 47)

`components.css:335-336` carries an explicit narrow-viewport decision:
*"Overflow stays visible on purpose: below ~900px the tier scrolls, and a thin scrollbar is the only
honest signal that Settings is off-edge"* → `.admin-tier { overflow-x: auto; scrollbar-width: thin; }`.
Within the screen, the tab nav (`:28`), the catalogue head (`:228`) and the tag row (`:245`) all carry
`flex-wrap: wrap`. Only the Add-a-board (`:115`) and Add-a-tag (`:209`) grids are unbreakpointed. The
*action* the row proposes is sound; the premise and the feature-added label are not.

---

## 2. Misclassifications

### M1 — Row 9 `feature-added` → `feature-changed`
Per R3. The design models a ten-area admin tier; production ships an eight-group vertical rail. Same
concept, different mechanics. Filing it feature-added asserts the design is silent and forecloses the
question of whether Imladris' horizontal tier is being adopted.

### M2 — Row 42 `copy` → `feature-added`
Report row 42 itself says *"Design: No per-field error state modelled (only the row `Saved` chip)"*,
then classifies the difference `copy`. Production renders a row-scoped
`<div class="error-list" role="alert">` of `<p class="field-error">` (`tags.php:66-72`) plus the 422
typed-value round-trip (`tags.php:44-47`, pinned by `AppTagAdminTest.php:79-81`
`test_invalid_tag_update_rerenders_422_with_typed_row_values`). Production has behaviour the design
never modelled — that is the feature-added definition verbatim. Labelling it `copy` implies production
must change to match a design that shows nothing, and the "copy" count is inflated by one.

### M3 — Row 17 `feature-removed` conflates an affordance with a claim
Two separate things sit at `AdminContent.dc.html:61-63`:
1. an inline `role="status"` saved chip — the *same* affordance row 40 correctly calls
   **feature-changed** (production uses PRG + flash `Category updated.`, `AdminController.php:115`);
2. the sentence "The old slug keeps working." — genuinely **feature-removed** (categories are
   `id, name, position` only, `database/migrations/0002_categories.php:10-16`).

Booking both as feature-removed means the chip is silently dropped rather than adjudicated against the
PRG constraint, and it contradicts row 40 for identical mechanics.

### M4 — Row 3 `constraint` over-applies the fiction exemption
"Do not port. The design's topbar is fiction branding" is right for the `Imladris` wordmark and the
eight-point star (governing rule 3). It is wrong for the rest of the bar: sticky 58px identity row,
labelled exit link, mode-pill placement, scrolling ten-area tier — none of that is fiction, all of it is
a design decision production does not implement. Filing the whole row under the fiction exemption hides
a copy/feature-changed difference behind a licence to ignore it. Split: wordmark/star = constraint;
bar anatomy = copy/feature-changed (see R3, R6).

### M5 (soft) — Row 6 `feature-changed` double-books the PE constraint
Production has two h1s because it has two real routes, which is the *same* progressive-enhancement
constraint row 8 already books for the tab strip (no client view state). Booking it again as
feature-changed inflates the count; and its presentation half rests on the refuted 2.4rem figure (R2).

---

## 3. Missed differences

| # | Section | Difference | Class |
|---|---|---|---|
| N1 | Page canvas | Design `max-width: 1100px; margin: 0 auto; padding: 22px 28px 110px` (`:24`). Production `.admin` is `max-width: 1260px; padding: 24px 28px 64px` on a `224px minmax(0,1fr)` grid (`app.css:2800-2812`). Not mentioned anywhere. | copy |
| N2 | Page-head rule | Production `.admin-head` owns `border-bottom: 1px solid var(--border-hair); padding-bottom:16px; margin-bottom:20px` (`app.css:2813-2820`). In the design the h1 is bare (`margin:0`, `:26`) and the hairline belongs to the **tab strip** (`border-bottom: 1px solid var(--border-hair); margin: 16px 0 0`, `:28`). The rule *moves*; the report treats the head as re-skin-only. | copy |
| N3 | Tab strip anatomy | `<nav aria-label="Content sections">` (`:28`); items `--font-label` `.84rem`/`.03em`; active = `border-bottom: 2px solid var(--gold-500)` + `aria-current="page"` + `margin-bottom:-1px` so the tab sits *on* the strip hairline; inactive `--text-muted` → `--text-strong` on hover (`:29-32`). The report names only the two labels. | copy |
| N4 | Add-a-tag slug hint | Design `placeholder="{{ newTagSlugHint }}"` → `'derived from the name'` (`:216`, `:529`). `tags.php:25` has neither a placeholder nor the hint text that `structure.php:115` gives the *board* slug. The report booked only `bSlugHint`. | copy |
| N5 | Tag-row description placeholder | Design `placeholder="Description"` (`:248`); `tags.php:57` has none. | copy |
| N6 | Mode-pill casing | `.admin-bar-mode` is `text-transform: uppercase` + `--radius-pill` (`components.css:334`); `.pill-admin` (`app.css:106`) is neither. Dropped from row 7. | copy |
| N7 | **Chrome scope** | The design's admin screens are a self-contained chrome — sticky `.admin-bar` over a 1100px centred canvas with **no forum sidebar** (`components.css:328`, `AdminContent.dc.html:22-24`). Production renders the console *inside* the ordinary three-pane app shell (`layout.php:56-63`: `app-shell` + `partials/sidebar` + `partials/topbar`). This is the single largest structural difference on the screen and the report books it nowhere — it neither adopts Imladris' "admin is its own chrome" posture nor records rejecting it. | feature-changed |
| N8 | Two button registers | Design `Delete category` is a **bordered** ghost (`1.5px solid var(--border-soft)`, `--rust` text, `:59`); board-row `Edit`/`Archive`/`Delete` are **borderless** (`:85-87`). The report flattens both into "ghost row-buttons". | copy |
| N9 | Missed fiction instance | Seed **board** `Wardens` / slug `wardens` (`:306`) — the fiction table lists `Wardens` only as a category. Second instance of the same fiction token. | constraint (fiction) |
| N10 | Slice scoping | Any head/pill/eyebrow/rail decision is a **five**-template change on this screen: `structure.php:9-13`, `tags.php:12-16`, `structure_confirm.php:4-8`, `tag_merge_confirm.php:4-8`, `board_edit.php:4-8` all render `.admin-head` + `_nav`. S1's touch list names only `structure.php` + `tags.php` + a new partial, contradicting its own "cross-screen" note in row 7. | — |

---

## 4. What survives verification (confirmed correct — do not re-litigate)

Every one of these I opened and confirmed:

- **CSP constraint (row 1).** `SecurityHeaders.php:41` → `script-src 'self'; style-src 'self';` with no
  `style-src-attr`. Correct.
- **PE/CSRF constraint (row 2).** All eight `csrfField()` sites cited in `structure.php` and `tags.php` verified.
- **Boundary reorder (row 11).** `AdminService::moveCategory` `:413-415` `return; // boundary … safe no-op`
  (report said 414-416, off by one), pinned by `AppAdminStructureReorderTest.php:82`
  `test_move_top_category_up_is_a_safe_noop`. `Direction must be "up" or "down".` at `:474`;
  `The submitted order must contain exactly the existing items.` at `:515`;
  `Category name must be 1–64 characters.` at `:683`. All exact. The "do not adopt the boundary
  string" call is right.
- **Destructive-flow constraint (rows 16, 25).** `ADMIN.md:352` (§4.5): *"Destructive actions (delete
  board, delete category) require typed confirmation and show impact"* — a spec lock, not preference.
  `confirmCategoryView` `:520-543` and `confirmBoardView` `:554-630` verified line-for-line including
  the blocked reasons, the move picker, and the archive intro at `:581`.
- **ADR citations.** ADR 0023 item 5 = accessible field errors; item 6 = grouped admin nav per ADMIN §9.2
  — both accurate. ADR 0021 deferral #8 = drag-and-drop reorder deferred, ↑/↓ forms retained; confirmed,
  and `grep -rn data-reorder public/assets templates` returns nothing.
- **All AdminController / TagController line numbers** in the report's header table are exact.
- **Repositories.** `BoardRepository::allOrdered` `SELECT *` (`:16-22`) so `description` is available and
  genuinely unrendered in `structure.php` (grep: only the Add-a-board field at `:118`).
  `TagRepository::allForAdmin` `:102-107` has no count/filter/limit; `catalogForViewer` `:82-100` holds
  the reusable `LEFT JOIN … COUNT(*)` shape. `tags` table (`0048_phase4_gate_a.php:154-167`) has no usage column.
- **Merge-confirm copy is verbatim identical** — `tag_merge_confirm.php:12-19` vs design `:186-194`,
  including `(includes hidden, held, and deleted threads)`. Confirmed.
- **Tests.** `AppFieldErrorA11yTest.php:24-35` pins `id="err-name"` / `aria-invalid` / `aria-describedby`
  / `autofocus`. `AppTagAdminTest.php:40` pins `64 characters or fewer`; `:79-81` pins the row 422
  round-trip. All correct.
- **Row 35's caution is warranted and resolvable:** `grep -rn "already in use" tests/**/*.php` returns
  exactly one hit — `AppAdminTest.php:98`, and it is the **board** slug path
  (`test_explicit_taken_board_slug_is_422_not_silently_suffixed`). Changing only the *tag* string
  (`TagController.php:102,124`) breaks no test.
- **app.css citations** are right within ±2 (`.admin-cat` is 603 not 601, `.admin-cat-head` 605 not 603,
  `.admin-board-row` 608 not 606, `.impact-list dt` 617 not 616, `.eyebrow` 39 not 37; `.pill-admin` 106,
  `.card` 159, `.admin-head .eyebrow` 2822, `.admin-head h1` 1.9rem 2826, `.pill-admin` position 2832,
  `.pane-intro` 2936 all exact).
- **`board_edit.php` citations** (`:11-13`, `:14-94`, `:96-121`, `:123-148`, `:118`, `:145`) all exact;
  row 45's feature-added call is correct (design `:85` is `<a href="#">Edit</a>` to nothing).
- **Rows 26, 31, 46** feature-added calls verified against the x-dc script: `b.remove` (`:456-458`) is a
  bare array filter — no move picker, no blocked state; the Add-a-board error state (`:112-114`) is the
  banner only, no per-field errors.
- **Fiction table** is otherwise thorough and the proposed plain-English replacements are sound.

## 5. Binding-decision check

No proposed action reverts an ADR 0021 or ADR 0023 deferral. S2 keeps the ↑/↓ forms (ADR 0021 #8);
S3 keeps the typed-confirm flows (ADMIN §4.5 / ADR 0021); S4 preserves `field_error`/`field_attrs`
(ADR 0023 item 5); the rail is retained (ADR 0023 item 6) — though R3/M1 mean *retaining* it is now an
open decision rather than a settled one, since the design does have a competing proposal.

The one governance risk is R1: slice S1 would introduce a string the design system documents as
deliberately deleted, presented in the plan as verbatim design adoption.
