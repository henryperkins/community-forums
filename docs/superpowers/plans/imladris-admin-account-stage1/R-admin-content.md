# R — admin-content: correction addendum to D-admin-content.md

**This file supersedes `D-admin-content.md` and `V-admin-content.md` wherever they disagree with it.**
It corrects, it does not re-derive. Rows not named here stand as written in D (with the design line
anchors re-mapped in §1.3).

Design file re-read in full, markup portion, at 2026-08-03:
`docs/design-system/imladris/templates/admin-content/AdminContent.dc.html`
— **570 lines total. Markup 1–293 (`</x-dc>` closes at :293). `<script type="text/x-dc">` opens at
:294 and closes at :568; `</body></html>` 569–570.**

D's header ("582 lines; markup 1–305, script 306–580") is stale by the mid-pass mirror refresh.
V's line figures are correct.

---

## 0. What changed upstream, and the one consequence that inverts actions

The mirror refresh replaced ~16 lines of per-screen chrome with a single import:

```
:22  <x-import component-from-global-scope="ImladrisDesignSystem_c3e027.AdminNav" area="content" hint-size="100%,101px"></x-import>
```

Deleted from this page: the sticky 58px topbar (eight-point star + `Imladris` wordmark +
`Back to the council`), and the two-column head block (the gold `Operator desk · Content` eyebrow and
the `Admin mode` pill). Page padding `26px 28px 110px` → `22px 28px 110px`; h1 `2.4rem`/`margin 7px 0 0`
→ `2.1rem`/`margin: 0`; sub-nav top margin `22px` → `16px`.

**Consequence: there is no eyebrow, no wordmark, no back link and no mode pill anywhere in
`AdminContent.dc.html`.** The `<h1>` at :26 is the first child of the canvas, immediately after the
x-import. Verified by literal grep against the current file:

```
grep -n "Operator desk"       → (no hit)
grep -n "Back to the council" → (no hit)
grep -n "Admin mode"          → (no hit)
grep -n "eyebrow"             → (no hit)
grep -n "2.4rem"              → (no hit)
grep -n "Imladris"            → :22 only (the x-import component path)
```

The design system documents the removal verbatim at
`components/admin/admin.card.html:43`: *"…this chrome is 10px shorter: the redundant “Operator
desk · Area” kicker is gone, the mode pill moved into the identity row, and the heading drops from
2.4rem to 2.1rem."*

Every D row that told production to **add** one of those to `.admin-head` is inverted or struck (§2).

---

## 1. Corrected anchors

### 1.1 Corrected section order, top to bottom, verbatim

| # | Section | Verbatim string in the current file | Line |
|---|---|---|---|
| D1 | Shared admin chrome — **imported, not authored on this page** | `<x-import component-from-global-scope="ImladrisDesignSystem_c3e027.AdminNav" area="content" hint-size="100%,101px">` | :22 |
| D2 | Page canvas | `max-width: 1100px; margin: 0 auto; padding: 22px 28px 110px;` | :24 |
| D3 | Page heading (first child of the canvas; **no eyebrow above it**) | `Boards &amp; tags` — `font-size: 2.1rem; margin: 0;` | :26 |
| D4 | Section tabs | `<nav aria-label="Content sections">` → `Boards &amp; categories` \| `Tags` | :28–33 |
| D5 | *(structure tab)* intro | `Categories order the council's rooms; boards are the rooms themselves. Renaming is safe — the old slug keeps working. Archiving hides a board without losing a word of it.` | :38 |
| D6 | Reorder error alert | `That category is already first in the order.` | :40–42 |
| D7 | Structure empty state | `No categories yet` + `A council needs at least one room. Add a category below, then put a board inside it.` | :44–49 |
| D8 | Category card head | `Rename this category` input + `Save` + `Move category up` + `Move category down` + `Delete category` | :53–60 |
| D9 | Category saved status | `Saved. The old slug keeps working.` | :61–63 |
| D10 | Category name error | `A category needs a name.` | :64–66 |
| D11 | Board rows | `#{{ b.name }}` link, `/c/{{ b.slug }}`, `Hidden`/`Private`/`Archived`, `· {{ b.threadLabel }}`, `{{ b.description }}`, then `Move board up`, `Move board down`, `Edit`, `{{ b.archiveLabel }}`, `Delete` | :68–91 |
| D12 | `Add a category` | error `Give the category a name before adding it.` (:100) + `Category name` input + `Add category` | :96–106 |
| D13 | `Add a board` (gated `{{ canAddBoard }}`) | error `Please fix the highlighted fields.` (:113) + 2-col grid (form :115–173) | :108–175 |
| D14 | *(tags tab)* Merge confirmation | `Merge “{{ mergeSourceName }}” into “{{ mergeTargetName }}”?` + impact `<dl>` (:188–195) + `Merge and remove “…”` / `Cancel` | :184–201 |
| D15 | `Add a tag` | 4-col grid `1fr 1fr 1.4fr auto` (form :209–223) | :203–224 |
| D16 | `Catalogue` | h2 + `Search the catalogue` + `Most used` / `A–Z` + `{{ tagResultLabel }}` | :226–241 |
| D17 | Tag rows | name/slug/description/visibility/`Enabled`/`{{ t.uses }} uses` (:254)/`Save`/merge select + `Merge…`/`Saved` (:266–268) | :243–271 |
| D18 | Tag empty state | `{{ tagEmptyTitle }}` / `{{ tagEmptyBody }}` | :273–278 |
| D19 | Tag pager | `Previous` · `{{ pageLabel }}` · `Next` | :280–286 |

### 1.2 Production order — unchanged from D §1

D's production tables (P1–P13) and its "Order verdict" survive verification untouched, **except**:
P1 and P7 are described as "**no eyebrow**" — correct, and now the *design* has none either, so this
is no longer a gap (see struck row 5). Verified today: `structure.php:9–12` and `tags.php:12–15` are
`<h1>` + `<span class="pill pill-admin">Admin mode</span>` only.

### 1.3 Design line-anchor remap (use these, discard D's)

D's design citations are uniformly ~12 lines high. Complete replacement table:

| D cites | Actually at | Section |
|---|---|---|
| :13-17 | :13–17 ✔ | `<helmet><style>` incl. `@keyframes acRise` (:16) |
| :22-28 | — **deleted** | per-screen topbar (now `AdminNav`, `components/admin/AdminNav.jsx:50–60`) |
| :30 | :24 | 1100px canvas |
| :32-38 | — **deleted** | two-column head block |
| :34 | — **never existed** | eyebrow `Operator desk · Content` (fabricated, see row 5) |
| :35 | :26 | h1 `Boards &amp; tags` |
| :37 | — **deleted** | `Admin mode` pill (now `components.css:334 .admin-bar-mode`) |
| :40-45 / :41-44 | :28–33 | section tabs |
| :50 | :38 | structure intro |
| :52-54 | :40–42 | reorder error alert |
| :56-61 / :59 | :44–49 / :47 | structure empty state |
| :65-66 | :53–54 | category card + head gradient |
| :67 | :55 | rename input |
| :69-70 | :57–58 | category up/down |
| :71 | :59 | `Delete category` |
| :73-75 / :74 | :61–63 / :62 | category saved chip |
| :76-78 / :77 | :64–66 / :65 | category name error |
| :80-103 | :68–91 | board rows |
| :82 | :70 | `<li>` separator |
| :85 | :73 | `#{{ b.name }}` link |
| :87-88 | :75–76 | Hidden / Private chips |
| :89 | :77 | Archived chip |
| :90 | :78 | thread count |
| :92 | :80 | board description |
| — | :83–84 | **board** up/down buttons (unbooked by D — new row 54) |
| :97-99 | :85–87 | Edit / Archive / Delete |
| :109-118 / :112 | :96–106 / :100 | `Add a category` |
| :121-187 / :127-184 | :108–175 / :115–173 | `Add a board` |
| :142 | :130 | board slug placeholder |
| :196-213 | :184–201 | merge panel |
| :200-207 | :188–195 | merge impact `<dl>` |
| :216-236 / :221-235 | :203–224 / :209–223 | `Add a tag` |
| — | :216 | Add-a-tag slug placeholder (new row 50) |
| :239-253 | :226–241 | catalogue head |
| :255-283 / :257-277 | :243–271 / :245–268 | tag rows |
| — | :248 | tag-row `placeholder="Description"` (new row 51) |
| :266 | :254 | `{{ t.uses }} uses` |
| :278-280 | :266–268 | per-tag `Saved` chip |
| :285-290 | :273–278 | tag empty state |
| :292-298 | :280–286 | tag pager |
| :306-580 | :294–568 | `<script type="text/x-dc">` |
| — | :334 | `const TAG_PAGE = 8;` |
| :371-378 | :359–366 | `moveCat` (boundary → `reorderError`) |
| :429 | :417 | duplicate-slug message |
| :493 | :481 | `canMerge` |
| :526 | :514 | `bSlugHint` |
| — | :529 | `newTagSlugHint` |
| — | :540 | `tagResultLabel` |
| :555-556 | :543–544 | `tagEmptyTitle` / `tagEmptyBody` |
| :308 / :309 | :296 / :297 | seed `The council` / board `Counsel` |
| :312 / :314 | :300 / :302 | seed `The archive` / board `Lore` |
| :317 / :318 | :305 / :306 | seed `Wardens` category / `Wardens` board |
| :329 / :331 | :317 / :319 | tag `naming` / `moderation` |
| :338 / :339 | :326 / :327 | tag `theming` / `evaluation` |

### 1.4 Production anchors — D was right, V's "corrections" were not

V §4 claimed D's `app.css` citations were off by ±2 and that `AdminService::moveCategory` is
`:413-415`. Re-verified against the working tree today; **D is exact and V is wrong on all of these**:

| Claim | D said | V "corrected" to | Actual |
|---|---|---|---|
| `.eyebrow` | 37–43 | 39 | **37**–43 ✔ D |
| `.admin-cat { }` | 601 | 603 | **601** ✔ D |
| `.admin-cat-head` | 603 | 605 | **603** ✔ D |
| `.admin-board-row` | 606 | 608 | **606** ✔ D |
| `.impact-list dt` | 616 | 617 | **616** ✔ D |
| `.admin-head h1` `1.9rem` | 2825 | 2826 | selector **2825**, declaration **2826** — both fine |
| boundary no-op | `AdminService.php:414-416` | 413–415 | **414** `if ($reordered === null) {` / **415** `return; // boundary … safe no-op` / **416** `}` ✔ D |

Exact today, from D and confirmed: `.pill-admin` 106, `.card` 159, `.admin` 2800–2812,
`.admin-head` 2813–2821, `.admin-head .eyebrow` 2822, `.admin-head .pill-admin` 2832,
`.pane-intro` 2936; `AdminService.php` :474, :515, :683.

---

## 2. Rows whose ACTION is INVERTED or STRUCK by the chrome change

### Row 5 — eyebrow `Operator desk · Content` — **STRUCK. The quoted string is fabricated.**

D: *"`<span>Operator desk · Content</span>` … :34"*, action *"Add `<span class="eyebrow">Operator
desk · Content</span>` to both heads."*

No such string exists in the file (grep in §0). `:34` is blank-adjacent markup inside the structure
pane. The design system records the kicker's **deletion** (`admin.card.html:43`).

**Corrected action: none. Do not add an eyebrow to `structure.php` or `tags.php`.** Both templates
correctly have none today. Nothing to delete on this screen either — the surviving production
`Operator desk` eyebrows are on `dashboard.php:6`, `branding.php:11`, `settings.php:14`, which belong
to the admin-overview / admin-appearance / admin-settings reports, not this one. (`.eyebrow` is still
used legitimately as a *section* kicker elsewhere, e.g. `dashboard.php:20,41,66,86` — the retirement
is of the page-head "Operator desk · Area" kicker specifically.)

Slice **S1 must drop its eyebrow item and the test asserting eyebrow copy.** As written S1 would ship,
labelled "verbatim design adoption", chrome Imladris deliberately removed.

### Row 3 — per-screen topbar — **SPLIT; page-level action struck.**

The bar is no longer on this page. Only its fiction half stays a constraint.

- **3 (constraint):** the eight-point star + `Imladris` wordmark (`AdminNav.jsx:52`) is fiction
  branding — do not port. Unchanged verdict.
- **The rest of the bar is not a page element** and moves into new row 4.
- D's citation `AdminContent.dc.html:22-28` is dead; cite `AdminNav.jsx:50–60` +
  `components.css:328–334` instead.

### Row 4 — `Back to the council` back link — **INVERTED. Do not add a back link to `.admin-head`.**

The string is not in this file; it is `AdminNav.jsx:44` `backLabel`. And per V R7, production already
gives every admin page a route back to the forum: admin templates set no `variant`, so
`templates/layout.php` renders `partials/topbar` + `partials/sidebar` around the console, and
`partials/topbar.php:11` is `<a class="brand" href="/">`. The design's exit link **replaces** that
chrome; it does not sit alongside it. Adding a second back link into `.admin-head` matches neither
side. Folded into new row 4 as part of the unadjudicated chrome question.

### Row 7 — `Admin mode` pill in the page head — **INVERTED. The design removed the pill from the page.**

There is no pill in `AdminContent.dc.html`. It is `components.css:334 .admin-bar-mode`, positioned
`margin-left: auto` inside the **nav bar's identity row**. `admin.card.html:43`: *"the mode pill moved
into the identity row."*

D's colour/size figures were read out of `components.css` and mis-attributed to `:37`. They are right
as far as they go but **D dropped `text-transform: uppercase`** and `border-radius: var(--radius-pill)`
(V N6) — production's `.pill-admin` (`app.css:106`) has neither.

**Corrected action: "re-skin `.pill-admin` in place" is not a design-sanctioned change.** The design's
proposal is *relocation*, which is a console-chrome decision (row 4), not a five-template colour swap.
Do not restyle `.pill-admin` on the strength of this screen alone.

### Row 9 — "the design has no rail at all" — **FALSE. Reclassified feature-added → feature-changed, folded into row 4.**

`:22` imports `AdminNav`, which declares ten admin areas (`AdminNav.jsx:8–19`) and renders
`<nav className="admin-tier" aria-label="Admin areas">` with `aria-current="page"` on the active area
(`:60–75`), styled `components.css:337–342` (pill items, `overflow-x: auto`, `scrollbar-width: thin`).
`admin.card.html:43` states the two-rank intent outright. The design has a *competing* proposal for
console navigation; it is not silent. As written, row 9 licenses "keep the rail, it's ours" and
forecloses an unadjudicated question.

### **New row 4 (replaces old rows 3-anatomy, 4, 7, 9 and V N6/N7) — feature-changed — Console chrome**

**Design:** one imported `AdminNav` (`:22`) = sticky `.admin-bar` (`components.css:328`) with an
identity row (mark / `Back to the council` exit / uppercase `Admin mode` pill at `margin-left:auto`)
plus a ten-area pill tier that scrolls horizontally on narrow viewports — sitting over a bare 1100px
centred canvas with **no forum sidebar**.
**Production:** the console renders *inside* the ordinary three-pane app shell (`layout.php` →
`partials/topbar` + `partials/sidebar`), with an 8-group 224px vertical rail (`_nav.php:7–92`, ADR 0023
item 6 / ADMIN §9.2) and a per-template `.admin-head` carrying the h1 + `pill pill-admin` on five
templates in this screen's scope (`structure.php:9–12`, `tags.php:12–15`, `structure_confirm.php:4–8`,
`tag_merge_confirm.php:4–8`, `board_edit.php:4–8`).
**Action:** this is the single largest structural difference on the screen and D booked it nowhere.
It is a **console-wide decision, to be taken once in the admin-overview report, not screen-by-screen**.
Until it is taken: change nothing about the head, the pill, the rail, or the shell on this screen. ADR
0023 item 6 remains binding, so retaining the rail is the default — but retaining it is now an explicit
decision to record, not a settled fact.

### Row 6 — h1 — **AMENDED (2.4rem is the discarded value) and RECLASSIFIED feature-changed → copy.**

Design is `font-size: 2.1rem; margin: 0` (`:26`); `2.4rem` appears nowhere in the file and
`admin.card.html:43` names it as the value Imladris replaced. Anyone implementing D overshoots by
0.5rem and reinstates discarded chrome.
Reclassified per V M5: the "one h1 vs two h1s" half is the *same* progressive-enhancement fact already
booked as constraint in row 8 (two real routes vs one client-switched view). What remains is a type
scale: production `1.9rem` (`app.css:2825–2826`) → design `2.1rem`, `--font-display`,
`letter-spacing: -0.01em`, `margin: 0`. That is **copy**. Keep two routes and two h1s.

---

## 3. Rows whose quoted design string is fabricated or no longer present

Every quoted string in D was re-checked with a literal grep against the current file.

| D row | Quoted string | Status |
|---|---|---|
| 3 | `Imladris` wordmark, eight-point star, `:22-28` | **Not on this page.** Lives in `AdminNav.jsx:52`; the `Imladris` token survives at `:22` only as a component namespace |
| 4 | `Back to the council` `:27` | **Not on this page.** `AdminNav.jsx:44` |
| 5 | `Operator desk · Content` `:34` | **FABRICATED — exists nowhere in the design system except as a record of its deletion** (`admin.card.html:43`) |
| 6 | h1 `2.4rem` `:35` | **Superseded value.** Current file is `2.1rem` (:26) |
| 7 | `Admin mode` pill `:37` | **Not on this page.** `components.css:334`; D also dropped `text-transform: uppercase` and `--radius-pill` |
| 9 | "Design has no rail at all" | **False premise.** `:22` + `AdminNav.jsx:8–19,60–75` |
| 47 | "Fixed 1100px desktop composition … 2-col grids with no breakpoint" | **Overstated.** `components.css:337` scrolls the tier deliberately; `:28`, `:228`, `:245` all `flex-wrap: wrap`. Only `:115` and `:209` are unbreakpointed |

All other quoted design strings verified present and verbatim, at the remapped lines in §1.3 —
including `That category is already first in the order.` (:41), `No categories yet` (:46),
`A council needs at least one room…` (:47), `Saved. The old slug keeps working.` (:62),
`A category needs a name.` (:65), `Give the category a name before adding it.` (:100),
`Please fix the highlighted fields.` (:113), `Search the catalogue` (:232),
`Give the tag a name before adding it.` (:414),
`A tag with the slug “…” already exists. Merge into it rather than adding a twin.` (:417),
`Nothing matches “…”` / `Try a shorter phrase, or add it as a new tag above.` /
`Add the first tag above. Fewer, sharper tags beat many vague ones.` (:543–544),
`derived from the name` (:514, :529), `Page N of M` (:546), `1 tag` / `N tags` (:540),
`Merge and remove “…”` (:197), and the merge-impact copy (:187–194).

Fiction table (D §3) — all strings still present, at the corrected seed lines in §1.3.
**Additions:** the seed **board** `Wardens` / slug `wardens` at `:306` is a second instance of the
same fiction token (D listed only the category) — new row 53. `The warden’s table. Staff only.` is at
`:306`, and a second warden reference sits in the `moderation` tag description at `:319`. All use a
typographic apostrophe (`’`), not `'` — grep accordingly.

---

## 4. V-report findings folded in (this addendum is the single corrected source)

| V item | Disposition |
|---|---|
| R1 fabricated eyebrow | **Accepted** → row 5 struck (§2) |
| R2 h1 2.4rem → 2.1rem | **Accepted** → row 6 amended (§2) |
| R3 design has a rail | **Accepted** → row 9 folded into new row 4 (§2) |
| R4 design lines off by ~12; file is 570/293/294–568 | **Accepted** → full remap in §1.3 |
| R5 topbar/back/pill cited to the wrong file | **Accepted** → §2 rows 3, 4, 7 |
| R6 pill was relocated, not recoloured | **Accepted** → row 7 inverted |
| R7 production already has a back-to-forum affordance | **Accepted** → row 4 inverted |
| R8 responsive premise overstated | **Accepted** → row 47 reclassified feature-added → **copy** |
| M1 row 9 → feature-changed | **Accepted** (as new row 4) |
| M2 row 42 copy → feature-added | **Accepted.** Production's row-scoped `error-list` + 422 typed-value round-trip (`tags.php:44–47,66–72`, pinned `AppTagAdminTest.php:79–81`) is behaviour the design never models |
| M3 row 17 conflates chip with claim | **Accepted → split.** 17a *feature-removed*: the sentence "The old slug keeps working." (categories are `id, name, position`, `0002_categories.php`). 17b *feature-changed*: the inline `role="status"` chip itself — identical mechanics to row 40, production uses PRG + flash `Category updated.` (`AdminController.php:115`). Adjudicate 17b **with** row 40, not against it |
| M4 row 3 over-applies the fiction exemption | **Accepted → split** (§2 row 3 / new row 4) |
| M5 row 6 double-books PE | **Accepted** → row 6 becomes copy (type scale only) |
| N1 canvas metrics | **Accepted** → new row 48 |
| N2 the head hairline moves to the tab strip | **Accepted** → new row 49 |
| N3 tab-strip anatomy | **Accepted → folded into row 8** (no new row): `<nav aria-label="Content sections">` (:28), `gap: 2px`, `flex-wrap: wrap`, strip `border-bottom: 1px solid var(--border-hair)`, **`margin: 16px 0 0`** (upstream reduced from 22px), items `--font-label` `.84rem`/`.03em`, active `border-bottom: 2px solid var(--gold-500)` + `aria-current="page"` + `margin-bottom: -1px`, inactive `--text-muted` → `--text-strong` on hover, pane `padding-top: 24px` (:37, :181) |
| N4 Add-a-tag slug placeholder | **Accepted** → new row 50 |
| N5 tag-row description placeholder | **Accepted** → new row 51 |
| N6 mode-pill casing | **Accepted → folded into row 4** |
| N7 chrome scope (no forum sidebar) | **Accepted → folded into row 4** |
| N8 two button registers | **Accepted** → new row 52 |
| N9 second `Wardens` fiction instance | **Accepted** → new row 53 |
| N10 slice scoping is five templates, not two | **Accepted.** Any head/pill/rail decision touches `structure.php`, `tags.php`, `structure_confirm.php`, `tag_merge_confirm.php`, `board_edit.php`. But per row 4 that decision is deferred to admin-overview, so **S1's scope shrinks rather than grows** — see §6 |
| V §4 "app.css citations off by ±2" and "boundary is :413-415" | **REJECTED.** D was exact on all of them; see §1.4. Do not apply V's shifts |
| V §4 everything else (CSP, CSRF, ADMIN §4.5 lock, ADR 0021/0023, controller lines, repositories, merge copy identical, test pins, `board_edit.php` lines, rows 26/31/46) | **Accepted as confirmed — do not re-litigate** |

---

## 5. Rows added because the current file contains something no report booked

| # | Section | Class | Design | Production | Action |
|---|---|---|---|---|---|
| 48 | Page canvas | copy | `max-width: 1100px; margin: 0 auto; padding: 22px 28px 110px` (:24) | `.admin` `max-width: 1260px; padding: 24px 28px 64px` on `224px minmax(0,1fr)` (`app.css:2800–2812`) | Record the width/padding difference; it is downstream of the row-4 chrome decision |
| 49 | Head rule placement | copy | h1 is bare (`margin: 0`, :26); the hairline belongs to the **tab strip** (:28) | `.admin-head` owns `border-bottom` + `padding-bottom:16px` + `margin-bottom:20px` (`app.css:2813–2821`) | If the tab strip is adopted, the `.admin-head` rule must be removed on these templates or the screen double-rules |
| 50 | Add-a-tag slug hint | copy | `placeholder="{{ newTagSlugHint }}"` → `derived from the name` (:216, :529) | `tags.php:25` has neither placeholder nor the hint text `structure.php:115` gives the board slug | Add `placeholder="derived from the name"`. D booked only `bSlugHint` |
| 51 | Tag-row description placeholder | copy | `placeholder="Description"` (:248) | `tags.php:57` has none | Add it |
| 52 | Two button registers | copy | `Delete category` is a **bordered** ghost (`1.5px solid var(--border-soft)`, `--rust`, :59); board-row `Edit`/`Archive`/`Delete` are **borderless** (:85–87) | Both are `linkbtn` / `linkbtn danger` | D flattened both into "ghost row-buttons". Two registers, not one |
| 53 | Fiction — seed board `Wardens` | constraint | `{ id: 31, name: 'Wardens', slug: 'wardens', description: 'The warden’s table. Staff only.' }` (:306) | n/a (seed data) | Second instance of the same token; never ship. `Staff` / `staff` |
| 54 | **Board** reorder buttons | copy | 27×27 bordered icon buttons with Lucide arrow SVGs, `aria-label="Move board up"` / `"Move board down"` (:83–84) | `button.linkbtn` with literal `↑`/`↓` and the more specific `aria-label="Move {name} up"` (`structure.php:58–67`) | Swap the glyph for the SVG; **keep production's name-scoped aria-labels** (better than the design's generic ones) and both POST forms (ADR 0021 #8). D booked only the 30×30 *category* pair (row 15) |

Nothing else in the current markup (1–293) is unaccounted for. Two production affordances the design
also lacks are already booked: per-field errors (row 31 / 42) and the move-threads picker (row 26).
Production's sr-only `<label for>` on every tag-row control (`tags.php:52,54,56,58,75`) is *better*
than the design's `aria-label`s (:246–249, :258) — keep production's, note it inside row 41.

---

## 6. Corrected classification counts

D's own arithmetic was also off: over its 47 rows the true tally was **copy 29 · feature-added 6**,
not "copy 30 · feature-added 5".

Corrected row set: **52 rows** (47 original − 3 struck/folded (5, 7, 9) − 1 folded (old 3-anatomy into
new 4) + 1 split (17 → 17a/17b) + 7 new (48–54), with old rows 3 and 4 collapsing into the
constraint/feature-changed pair 3 + 4).

| Class | Count | Rows |
|---|---|---|
| **copy** | **33** | 6, 10, 12, 13, 14, 15, 18, 19, 20, 21, 22, 23, 24, 27, 28, 29, 30, 33, 34, 35, 36, 37, 38, 39, 41, 43, 47, 48, 49, 50, 51, 52, 54 |
| **feature-added** | **5** | 26, 31, 42, 45, 46 |
| **feature-removed** | **1** | 17a |
| **feature-changed** | **4** | 4, 11, 17b, 40 |
| **constraint** | **9** | 1, 2, 3, 8, 16, 25, 32, 44, 53 |
| **struck** | 3 | 5 (fabricated), 7 (folded → 4), 9 (false premise, folded → 4) |

Movement from D: copy 29 → 33 (+6 new, +row 6, +row 47, −row 5, −row 42); feature-added 6 → 5
(+row 42, −row 9, −row 47); feature-changed 3 → 4 (+row 4, +row 17b, −row 6); feature-removed 1 → 1
(row 17 narrowed to the slug sentence); constraint 8 → 9 (+row 53).

---

## 7. Consequences for D §5 (slice proposal)

- **S1 loses its two headline items.** No eyebrow (row 5 struck) and no page-head pill re-skin
  (row 7 inverted). What survives: the real-link tab strip with `aria-current` and flag-aware Tags
  (rows 8, 44 + N3 anatomy), the structure `.pane-intro` with de-fictionalised, factually corrected
  copy (row 10), the structure empty state (row 12), both tag empty states (row 39), and the 2.1rem
  display scale (row 6). Its test list must drop the eyebrow assertion and add the head-hairline
  question (row 49).
- **S1's touch list does not need to grow to five templates** (V N10) — because the head/pill/rail
  decision is deferred to admin-overview (row 4), S1 touches only `structure.php`, `tags.php`, the new
  `_content_tabs.php` partial, and `app.css`.
- **A new pre-slice item:** the console-chrome adjudication (row 4) is a blocking, console-wide
  decision. It belongs in the admin-overview report and, once taken, in an ADR — not in this screen's
  slices.
- S2–S5 are unaffected: every row they rest on (13–31, 33–43, 45, 46) verified clean, with only the
  design line anchors remapped per §1.3, plus rows 50–52 and 54 folded into S2/S4.
- D §5's cross-cutting note (`composer verify:imladris`, `application_surface.sha256`, the CSP scan,
  evidence filing) stands unchanged.
