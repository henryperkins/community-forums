# D — admin-content: AdminContent.dc.html vs production

Design source: `C:/Users/htper/community-forums/docs/design-system/imladris/templates/admin-content/AdminContent.dc.html`
(582 lines; markup 1–305, `<script type="text/x-dc">` 306–580)

Production surfaces (all read in full):

| File | Route(s) |
|---|---|
| `templates/admin/structure.php` | `GET /admin/structure` (App.php:2328) |
| `templates/admin/structure_confirm.php` | `GET/POST /admin/categories/{id}/delete`, `GET/POST /admin/boards/{id}/{delete,archive,unarchive}` (App.php:2335–2352) |
| `templates/admin/board_edit.php` | `GET /admin/boards/{id}/edit` (App.php:2337) + 422 re-render of `POST /admin/boards/{id}` and the four roster POSTs |
| `templates/admin/tags.php` | `GET /admin/tags` (App.php:2154) + 422 re-render of `POST /admin/tags` and `POST /admin/tags/{id}` |
| `templates/admin/tag_merge_confirm.php` | `GET /admin/tags/{id}/merge` (App.php:2158) |
| `src/Controller/AdminController.php` | `structure` :80, `createCategory` :87, `updateCategory` :102, `confirmDeleteCategory` :124, `deleteCategory` :131, `editBoard` :147, `createBoard` :159, `updateBoard` :174, `confirmDeleteBoard` :330, `deleteBoard` :337, `moveCategory` :363, `moveBoard` :376, `reorder` :395, `confirmArchiveBoard` :419, `archiveBoard` :426, `confirmUnarchiveBoard` :442, `unarchiveBoard` :449, `boardEditView` :472, `structureView` :495, `confirmCategoryView` :520, `confirmBoardView` :554 |
| `src/Controller/TagController.php` | `admin` :85, `create` :92, `update` :108, `mergeConfirm` :136, `merge` :151, `requireTags` :216, `renderAdminTags` :227, `validateTag` :251 |

Supporting facts established by reading source (not assumed):

- **Categories have no slug.** `database/migrations/0002_categories.php:10-15` — `id, name, position` only. `AdminService::updateCategory` (`src/Service/AdminService.php:88-111`) writes name + position and nothing else.
- **Boards do keep old slugs.** `database/migrations/0007_board_slug_history.php` exists; `templates/admin/board_edit.php:32` states it.
- **Boundary reorder is a deliberate silent no-op**, not an error. `AdminService::moveCategory` :414-416 `return; // boundary (top-up / bottom-down) or unknown id: safe no-op`, pinned by `tests/Integration/Core/AppAdminStructureReorderTest.php:82 test_move_top_category_up_is_a_safe_noop`. `reorder_error` can only be produced by a bad `dir` (`AdminService.php:474`) or a bad bulk id-set (`:515`).
- **There is no drag-and-drop anywhere.** `public/assets/app.js` contains no `data-reorder*`; the only reorder affordances are the six `<form method="post">` up/down buttons at `structure.php:31-40,58-67`. ADR 0021 deferral #8 locks this. The design also shows only up/down buttons — **no PE conflict on reorder**.
- **`tags` has no usage column.** `database/migrations/0048_phase4_gate_a.php:147-161`. `TagRepository::allForAdmin` :103-108 is `SELECT * FROM tags ORDER BY is_enabled DESC, name ASC, id ASC` — no count, no filter, no limit. A per-tag count query shape already exists at `TagRepository::catalogForViewer` :87-95.
- **Merge confirm copy is already verbatim identical** between design (`:198-207`) and production (`tag_merge_confirm.php:12-19`).

---

## 1. Section order comparison

### Design, top to bottom (verbatim headings/eyebrows)

| # | Design section | Verbatim string | Line |
|---|---|---|---|
| D1 | Sticky topbar | elven-star SVG + `Imladris` + `Back to the council` | :22-28 |
| D2 | Page head | eyebrow `Operator desk · Content`; h1 `Boards & tags`; pill `Admin mode` | :32-38 |
| D3 | Section tabs | `nav aria-label="Content sections"` → `Boards & categories` \| `Tags` | :40-45 |
| D4 | *(structure tab)* intro | `Categories order the council's rooms; boards are the rooms themselves. Renaming is safe — the old slug keeps working. Archiving hides a board without losing a word of it.` | :50 |
| D5 | Reorder error alert | `That category is already first in the order.` | :52-54 |
| D6 | Structure empty state | h2 `No categories yet` + `A council needs at least one room. Add a category below, then put a board inside it.` | :56-61 |
| D7 | Category card head | rename input (`Rename this category`) + `Save` + `Move category up` + `Move category down` + `Delete category` | :66-72 |
| D8 | Category saved status | `Saved. The old slug keeps working.` | :73-75 |
| D9 | Category name error | `A category needs a name.` | :76-78 |
| D10 | Board rows | `#{name}` link, `/c/{slug}`, `Hidden`/`Private`/`Archived` chips, `· N threads`, description, actions: up, down, `Edit`, `{archiveLabel}`, `Delete` | :80-103 |
| D11 | `Add a category` | error `Give the category a name before adding it.` + input + `Add category` | :109-118 |
| D12 | `Add a board` (gated `canAddBoard`) | error `Please fix the highlighted fields.` + 2-col grid: Category, Name, Slug, Description, Visibility, Who can post, Edit window (minutes, 0 = no limit), Assignment mode + 4 checkboxes + `Add board` | :121-187 |
| D13 | *(tags tab)* Merge confirmation | h2 `Merge “X” into “Y”?` + impact `<dl>` + `Merge and remove “X”` / `Cancel` | :196-213 |
| D14 | `Add a tag` | 4-col grid: Name, Slug, Description, `Add tag` | :216-236 |
| D15 | `Catalogue` | h2 + `Search the catalogue` + segmented `Most used`/`A–Z` + result count | :239-253 |
| D16 | Tag rows | name, slug, description, visibility select, `Enabled`, `N uses`, `Save`, merge select + `Merge…`, `Saved` | :255-283 |
| D17 | Tag empty state | `{tagEmptyTitle}` / `{tagEmptyBody}` | :285-290 |
| D18 | Tag pager | `Previous` · `Page N of M` · `Next` | :292-298 |

### Production, top to bottom

`GET /admin/structure` (`structure.php`)

| # | Section | Line |
|---|---|---|
| P1 | `.admin-head`: h1 `Boards &amp; categories` + `<span class="pill pill-admin">Admin mode</span>` — **no eyebrow** | :9-12 |
| P2 | `admin/_nav` grouped 8-group rail (`active => 'structure'`) | :13 |
| P3 | `$reorder_error` flash (`flash flash-error`) | :16-18 |
| P4 | `.admin-structure` → per-category `section.card.admin-cat`: rename form + ↑ / ↓ forms + `Delete category` link; `$update_category_error` field error; `ul.admin-board-list` of board rows | :20-81 |
| P5 | `Add a category` card | :83-94 |
| P6 | `Add a board` card, gated `if (!empty($categories))` | :96-158 |

`GET /admin/tags` (`tags.php`)

| # | Section | Line |
|---|---|---|
| P7 | `.admin-head`: h1 `Tags` + pill — **no eyebrow, no intro** | :12-15 |
| P8 | `admin/_nav` (`active => 'tags'`) | :16 |
| P9 | `Add a tag` card (stacked) | :19-30 |
| P10 | `Catalogue` card: `No tags yet.` **or** `ul.admin-board-list` of per-row update forms + merge GET form | :32-90 |

`GET /admin/tags/{id}/merge` (`tag_merge_confirm.php`): P11 head + rail + `section.card.confirm-card` (h2, prose, `dl.impact-list`, POST form with `target_id` + `Merge and remove “X”` / `Cancel`).

`GET /admin/{categories,boards}/{id}/{delete,archive,unarchive}` (`structure_confirm.php`): P12 head + rail + `confirm-card` (heading, intro, `dl.impact-list`, optional blocked banner, optional `move_to_board_id` picker, typed-confirm input, submit + `Cancel`).

`GET /admin/boards/{id}/edit` (`board_edit.php`): P13 head + rail + roster error flash + board settings form card + `Moderators` card + `Members — private & hidden boards` card.

### Order verdict

The design collapses **six production routes into one client-switched screen**. Within the two tabs the ordering is already an exact match:

- Structure: intro → error → empty → category cards → Add a category → Add a board. Production has the same order minus the intro and the empty state (P3→P4→P5→P6 == D5→D7/D10→D11→D12).
- Tags: Add a tag → Catalogue. Production matches (P9→P10 == D14→D15/D16). The design's merge panel (D13) sits above `Add a tag`; production's equivalent is a separate page (P11).
- The **Add-a-board field order is already byte-for-byte identical**: Category, Name, Slug, Description, Visibility, Who can post, Edit window, Assignment mode, allow_anonymous, require_approval, tags_enabled, wiki_enabled, submit (`structure.php:104-155` vs `AdminContent.dc.html:128-184`).

---

## 2. Difference table

| # | Section | Classification | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 1 | Whole screen — rendering | constraint | Every element carries `style="…"` (+ `style-hover`/`style-focus`); behaviour is `onClick=`/`onInput=` bound to a `DCLogic` state machine in `<script type="text/x-dc">` :306-580; `<helmet><style>` with `@keyframes acRise` :13-17 | `templates/**` has zero inline styles; `SecurityHeaders::csp()` emits `style-src 'self'` with no `style-src-attr` | Author every rule as an external class in `public/assets/app.css` (unlayered — see F1). Drop `@keyframes acRise` (or move it into app.css). No `<style>`, no `on*=` | low |
| 2 | Whole screen — interaction model | constraint | All mutations are in-memory `setState` closures; no `<form action>`, no CSRF | Every mutation is a real `POST` with `$this->csrfField()` (`structure.php:26,32,37,59,64,89,103`; `tags.php:22,51`) | Keep every production form verbatim; JS may only decorate. Never introduce a client-held dirty buffer | low |
| 3 | Topbar | constraint | Sticky 58px bar: eight-point elven star SVG + `Imladris` wordmark + back chevron :22-28 | `templates/layout.php` renders the operator's own shell from `$brand['name']`/`$brand['logo_path']` | Do not port. The design's topbar is fiction branding | low |
| 4 | Back link | copy | `Back to the council` chevron link :27 | No back link on any admin page (grepped `templates/admin` — only the ↑/↓ reorder glyphs) | Add one back affordance to `.admin-head` reading **`Back to the forum`** → `/` | low |
| 5 | Page head — eyebrow | copy | `<span>Operator desk · Content</span>`, `.68rem`, `var(--gold-ink)`, `.18em` :34 | `structure.php:9-12` and `tags.php:12-15` have **no** eyebrow. `.admin-head .eyebrow` already exists (`app.css:2822`), `.eyebrow` is `.72rem`/`--text-muted` (`app.css:37-43`) | Add `<span class="eyebrow">Operator desk · Content</span>` to both heads. Eyebrow size/colour is a cross-screen change — coordinate with F1 | low |
| 6 | Page head — h1 | feature-changed | One h1 `Boards & tags` for both tabs, `2.4rem`, `--font-display` :35 | Two routes, two h1s: `Boards &amp; categories` (`structure.php:10`) and `Tags` (`tags.php:13`), `1.9rem` (`app.css:2825`) | Production wins on behaviour (two real URLs keep their own h1/`<title>`); design wins on presentation (adopt the display-font scale). Do **not** merge the routes | low |
| 7 | Page head — Admin-mode pill | copy | `--surface-review` ground / `--on-review` ink, `999px`, `.72rem`, `.08em` :37 | `.pill-admin` is `--accent`/`--accent-contrast` (`app.css:106`), positioned by `app.css:2832` | Re-skin `.pill-admin` to the review pair. **Cross-screen** — every admin template renders this pill | low |
| 8 | Section tabs | constraint | `<button onClick={goStructure}>` / `<button onClick={goTags}>` client view state :41-44 | Grouped rail `Content` group: `Boards & categories` → `/admin/structure`, `Tags` → `/admin/tags` with `'flag' => 'tags'` (`_nav.php:18-21`) | Render the tab strip as real `<a href>`s with `aria-current="page"`. The Tags tab must respect the `tags` flag — `TagController::requireTags` :216-220 throws `NotFoundException` when off, so an unconditional link is a 404 link | low |
| 9 | Admin rail | feature-added | Design has no rail at all; the tab strip is the entire navigation | 8-group sticky 224px rail with feature-aware disabled spans + mobile drawer (`_nav.php:7-92`), locked by ADR 0023 item 6 / ADMIN §9.2 | Keep the rail unchanged. The design's tab strip is a *local* subnav inside `.admin-pane`, not a replacement | low |
| 10 | Structure intro | copy | Intro paragraph :50 (see fiction + factual notes below) | No intro on `structure.php`; `.pane-intro` idiom exists (`app.css:2936`) | Add `<p class="pane-intro">` with the rewritten sentence (§3 fiction table, and drop the false slug/archive claims) | low |
| 11 | Reorder error — boundary behaviour | feature-changed | Boundary move raises `reorderError` → `That category is already first in the order.` (`moveCat` :371-378) | Boundary is a deliberate silent no-op that still redirects with `Order updated.` (`AdminService.php:414-416`), pinned by `AppAdminStructureReorderTest.php:82` | Production wins on behaviour — keep the no-op. Design wins on presentation: restyle the existing `$reorder_error` banner (`structure.php:16-18`) to the rust left-rule alert. Do **not** adopt the boundary string | medium |
| 12 | Structure empty state | copy | Dashed-border centred card, h2 `No categories yet` + body :56-61 | None — `foreach ($categories …)` at `structure.php:21` renders nothing; `Add a board` silently disappears (`:96`) | Add the empty state card with de-fictionalised copy | low |
| 13 | Category card shell | copy | `--surface-raised`, `--border-hair`, `--radius-lg`, `--shadow-sm`, `overflow:hidden`; head has `linear-gradient(180deg, var(--parchment-100), var(--surface-raised))` and a hairline bottom border :65-66 | `section.card.admin-cat`; `.admin-cat {}` is empty (`app.css:601`), `.admin-cat-head` is a bare flex row with `margin-bottom:4px` (`app.css:603`) | Give `.admin-cat-head` the gradient + hairline + 13px/18px padding; card radius/shadow come from `.card` (note `app.css:159` overrides the design `.card` — see F1) | low |
| 14 | Category rename input | copy | `--font-display` 1.15rem, `--radius-md`, focus ring `0 0 0 3px var(--focus-ring)` + `--gold-500` border :67 | `input.input` inside `form.inline-form` (`structure.php:27`), body font | Restyle only. Keep `name`, `maxlength="64"`, `required`, the `aria-label`, and the `$catFailed` old-value round-trip (`:22,27`) | low |
| 15 | Category reorder buttons | copy | 30×30 bordered icon buttons with SVG arrow glyphs, `aria-label="Move category up/down"` :69-70 | `button.linkbtn` with literal `↑`/`↓` text and `aria-label="Move category X up"` (`structure.php:34,39`) | Swap the text glyph for the design's inline SVG (Lucide arrow), keep the two POST forms and the more specific aria-labels | low |
| 16 | Delete category | constraint | `onClick={cat.remove}` deletes immediately, no confirmation, no impact :71 | `<a class="linkbtn danger" href="/admin/categories/{id}/delete">` → `confirmCategoryView` (`AdminController.php:520-543`): board count impact, blocked reason `This category still has N boards. Move or delete them before deleting the category.`, typed **category name** to confirm; `deleteCategory` :135-137 refuses a bare POST with 422 | Production wins entirely (ADMIN §4.5, ADR 0021, no-JS). Keep the link → GET page → typed POST. Style the *link* like the design's row button and the confirm page like the design's merge panel | high |
| 17 | Category saved status | feature-removed | `Saved. The old slug keeps working.` on `--surface-done`/`--on-done` :74 | Categories have **no slug column** (`0002_categories.php:11-14`); `updateCategory` redirects with flash `Category updated.` (`AdminController.php:115`) | Do not build category slugs and do not ship the sentence. If an inline saved chip is wanted, it must read `Saved.` only | low |
| 18 | Category name error | copy | Inline `role="alert"` strip on a rust wash, `A category needs a name.` :77 | `<p class="field-error" role="alert">` inside the card (`structure.php:44-46`) showing `Category name must be 1–64 characters.` (`AdminService.php:683`) | Restyle to the full-bleed rust strip. Keep production's message (it states the real bound) or add the design's phrasing only for the empty case | low |
| 19 | Board row — description | copy | `<p>{{ b.description }}</p>` under the title line :92 | Not rendered. Data is available: `allOrdered()` is `SELECT *` (`BoardRepository.php:16-21`) and `boards.description` exists | Render `$board['description']` escaped, `.88rem`/`--text-muted` | low |
| 20 | Board row — name link | copy | `#{name}` is an `<a>` (hover → `--accent`), `#` in `--gold-ink` :85 | Plain text `<span class="hash">#</span>` + name (`structure.php:51`) | Link to `/c/<?= $e($board['slug']) ?>` | low |
| 21 | Board row — visibility chips | copy | Two distinct pills: `Hidden` on `--surface-pending`/`--on-pending`, `Private` on `--surface-info`/`--on-info` :87-88 | One `<span class="tag">` printing the raw enum lowercased (`structure.php:53`) → renders "hidden"/"private" | Emit two named, capitalised, semantically-toned chips | low |
| 22 | Board row — Archived chip | copy | Pill on `--surface-review`/`--on-review` :89 | `<span class="tag tag-archived">Archived</span>` (`structure.php:54`) | Restyle to the review pair | low |
| 23 | Board row — thread count | copy | `--font-mono` `.76rem` `--text-faint`, `· N threads` :90 | `<span class="muted">· N thread(s)</span>` (`structure.php:55`) — same pluralisation | Restyle to mono/faint | low |
| 24 | Board row — separator | copy | Each `<li>` has `border-bottom: 1px solid var(--border-hair)`, 12/18px padding :82 | `.admin-board-row` uses `border-top: 1px solid var(--border)` and 8px 0 padding (`app.css:606`) | Flip to bottom hairline + the design's padding | low |
| 25 | Board archive / delete | constraint | `onClick={b.archive}` toggles instantly; `onClick={b.remove}` deletes instantly :98-99 | Links to `GET /admin/boards/{id}/{archive,unarchive,delete}` (`structure.php:69-74`) → `confirmBoardView` (`AdminController.php:554-630`): impact rows (Board, Visibility, Threads incl. hidden/held/deleted, Posts), typed **board slug**, and for delete a `move_to_board_id` destination picker + blocked reason. POST without the typed slug is a 422 re-render (`:341-343,430-432,453-455`) | Production wins. Style the links as the design's ghost row-buttons. `{{ b.archiveLabel }}` already matches production's Archive/Unarchive swap | high |
| 26 | Board delete — move-threads picker & blocked states | feature-added | Not modelled at all | `structure_confirm.php:35-45` picker; `AdminController.php:600-621` `Move its N threads to`, `move_options`, blocked reason; flash `Moved N threads and deleted the board.` (`:352-357`) | Keep. Style the `<select>` and the blocked banner in the design idiom (rust wash for blocked) | medium |
| 27 | Board row — Edit link | copy | `<a>Edit</a>` styled as a ghost row-button :97 | `<a class="linkbtn" href="/admin/boards/{id}/edit">` (`structure.php:68`) — target exists (`AdminController::editBoard` :147) | Restyle only. (The design draws the affordance but models no edit screen; production's is the source of truth) | low |
| 28 | `Add a category` card | copy | Error banner `Give the category a name before adding it.` :112 + flex row :114-117 | Card + `flash flash-error` + `form.inline-form` with sr-only label, `required`, old-value refill (`structure.php:83-94`) | Restyle. Keep the sr-only `<label for>` (better than the design's `aria-label`) and the `create_category_old` refill | low |
| 29 | `Add a board` layout | copy | `display:grid; grid-template-columns:1fr 1fr; gap:14px 18px`, uppercase `.68rem` micro-labels, checkbox block spanning both columns :127-184 | Single-column `form.stacked` with plain `<span>` labels (`structure.php:102-156`) | Adopt the two-column grid + micro-label skin. Field set and order already match exactly | low |
| 30 | `Add a board` slug hint | copy | `placeholder="{{ bSlugHint }}"` — live-slugified name, else `derived from the name` :142,526 | Hint lives in the label text `Slug (optional — derived from name)` (`structure.php:115`); no placeholder | Add a static `placeholder="derived from the name"`. The *live* slugify preview is optional PE (`public/assets/app.js`), never required | low |
| 31 | `Add a board` per-field errors | feature-added | Only the banner `Please fix the highlighted fields.` | Banner (`structure.php:100`, identical string) **plus** `field_error()`/`field_attrs()` per field (`:110-141`) giving `id="err-board-*"`, `aria-describedby`, `aria-invalid`, autofocus — ADR 0023 item 5 | Keep and style `.field-error` in the idiom. Restructuring must not break the ids | medium |
| 32 | Tag merge confirmation | constraint | Inline armed panel above `Add a tag`, `animation: acRise` :196-213 | Separate `GET /admin/tags/{id}/merge` page (`TagController::mergeConfirm` :136-148, `tag_merge_confirm.php`) reached from a `method="get"` form (`tags.php:74-84`) | Production wins on mechanism (no-JS). The **copy is already verbatim identical** — port only the anatomy (rust 3px left rule, `--shadow-sm`, the `<dl>` grid, danger + ghost button pair) onto `.confirm-card` | medium |
| 33 | Merge impact `<dl>` | copy | `Source tag` / `Merges into` / `Impact` with `.7rem` uppercase `<dt>` and mono `<code>` slugs :200-207 | `dl.impact-list` with identical labels and values (`tag_merge_confirm.php:15-19`); `.impact-list dt` is `.88rem`/`--text-muted` (`app.css:616`) | Restyle `.impact-list` to the design's uppercase micro-`<dt>` + mono `<code>`. **Cross-screen** — `structure_confirm.php:16-21` shares the class | low |
| 34 | `Add a tag` layout | copy | 4-column grid `1fr 1fr 1.4fr auto`, `align-items:end` :221-235 | Stacked `form.stacked` (`tags.php:21-29`) | Adopt the grid. Keep `required` on name and the `field_error($createErrors,'name')` wiring — `AppFieldErrorA11yTest.php:24-35` asserts `id="err-name"`, `aria-invalid`, `aria-describedby`, `autofocus` | medium |
| 35 | Duplicate-slug message | copy | `A tag with the slug “eval” already exists. Merge into it rather than adding a twin.` :429 | `That tag slug is already in use.` (`TagController.php:102,124`) | Adopt the design's phrasing (it names the remedy). Requires interpolating the slug — check no test pins the old string before changing | medium |
| 36 | Catalogue head row | copy | h2 + pill search input `Search the catalogue` + segmented `Most used` / `A–Z` + right-aligned `N tags` :240-253 | Bare `<h2>Catalogue</h2>` (`tags.php:33`) | Add a server-rendered `<form method="get" action="/admin/tags">` with `q` + `sort`; segmented control = two links/radio-submits. **Needs `TagRepository::allForAdmin` to take q/sort/limit/offset** | medium |
| 37 | Per-tag uses count | copy | `{{ t.uses }} uses`, mono, right-aligned, min-width 62px :266 | Not rendered; `allForAdmin` (`TagRepository.php:103-108`) selects no count | Add a `LEFT JOIN (SELECT tag_id, COUNT(*) …)` — the exact shape already exists at `catalogForViewer` :87-95. Decide admin scope (all threads, not viewer-gated) and say so in the column header | medium |
| 38 | Catalogue pager | copy | `Previous` · `Page N of M` · `Next`, disabled at the ends, shown only when `> 8` rows :292-298 | None — every tag renders | Server-rendered `?page=N` pager with `aria-label`s (ADR 0023 item 5). Page size 8 is a design choice; production may pick its own and record it | medium |
| 39 | Tag empty states | copy | `No tags yet` + `Add the first tag above. Fewer, sharper tags beat many vague ones.`; search variant `Nothing matches “x”` + `Try a shorter phrase, or add it as a new tag above.` :285-290,555-556 | `<p class="muted empty">No tags yet.</p>` (`tags.php:35`) — title only, no body, no search variant | Adopt both states verbatim (they carry no fiction) | low |
| 40 | Per-tag saved status | feature-changed | Inline `role="status"` `Saved` chip in `--on-done` :278-280 | Redirect + global flash `Tag updated.` (`TagController.php:126`) | Production wins on behaviour (PRG). Optionally carry a `saved_id` through the flash so the row can show the chip; otherwise keep the flash and skip the chip rather than faking it | low |
| 41 | Tag row layout | copy | One wrapping flex row: name / slug (mono) / description / visibility / `Enabled` / uses / `Save` / merge select + `Merge…` :257-277 | Same order inside `li.admin-board-row` (`tags.php:49-86`), two sibling forms | Restyle: mono slug input, `--radius-md`, the design's 8px gap and 11px vertical padding | low |
| 42 | Per-tag update errors | copy | No per-field error state modelled (only the row `Saved` chip) | Errors dumped in a `<div class="error-list" role="alert">` of `<p class="field-error">` after the form (`tags.php:66-72`) — no `aria-describedby`, unlike the create form | Keep the 422 round-trip (`$isOldRow`/`$row` at `:44-47` — `AppTagAdminTest.php:79-81` pins the typed values surviving) and upgrade to `field_error`/`field_attrs` while restyling | medium |
| 43 | Merge affordance gate | copy | `canMerge: t.enabled && enabledCount > 1` :493 | Identical gate with an explanatory comment (`tags.php:37-40,73`) | No behaviour change; restyle the select + `Merge…` link-button | low |
| 44 | Tags feature flag | constraint | No flags exist in the design; the Tags tab always renders | `TagController::requireTags` :216-220 → 404 when `tags` is off; `_nav.php:20` gates the rail entry | The local tab strip must hide or disable the Tags tab when the flag is off, mirroring `_nav.php:80-84` (`Disabled until the feature flag is enabled` is pinned copy) | low |
| 45 | Board edit + rosters | feature-added | Not modelled — the design draws an `Edit` link to nothing | `board_edit.php`: settings form (`:14-94`), `Moderators` card (`:96-121`), `Members — private & hidden boards` card (`:123-148`), roster error flash (`:11-13`), 422 re-render via `boardEditView` (`AdminController.php:472-486`) | Keep every affordance. Style in the design idiom: reuse the category-card shell for the roster cards and the board-row anatomy for roster rows | medium |
| 46 | Category-delete blocked state | feature-added | Not modelled | `confirmCategoryView` :537-540 renders the blocked banner *instead of* the form, with only `Back to structure` | Keep. Style as the rust callout | low |
| 47 | Responsive / mobile | feature-added | Fixed 1100px desktop composition (`max-width:1100px` :30; 2-col grids with no breakpoint) | `.admin` collapses to one column ≤860px (`app.css` responsive block) + `data-admin-nav-*` drawer | Every new grid needs a ≤860px single-column fallback; the tag row must wrap and the tab strip must scroll | medium |

**Counts:** copy 30 · feature-added 5 · feature-removed 1 · feature-changed 3 · constraint 8 (47 rows).

---

## 3. Fiction strings

| # | Design string (verbatim) | Line | Proposed production string |
|---|---|---|---|
| 1 | `Imladris` (topbar wordmark) + eight-point elven-star SVG | :24-25 | Do not port — production renders `$brand['name']`/`$brand['logo_path']` in `templates/layout.php` |
| 2 | `Back to the council` | :27 | `Back to the forum` |
| 3 | `Categories order the council's rooms; boards are the rooms themselves.` | :50 | `Categories order the forum's rooms; boards are the rooms themselves.` |
| 4 | `A council needs at least one room. Add a category below, then put a board inside it.` | :59 | `A forum needs at least one room. Add a category below, then put a board inside it.` |
| 5 | `The council` (seed category) | :308 | Sample/demo data only — never ship. Use neutral names (`General`) |
| 6 | `The archive`, `Wardens` (seed categories) | :312,317 | `Archive`, `Staff` |
| 7 | `Counsel` (board), `counsel` slug, `Decisions that bind us, and the reasoning behind them.` | :309 | `Announcements` / `announcements` |
| 8 | `The warden's table. Staff only.` (board description) | :318 | `The moderation queue. Staff only.` |
| 9 | `Lore` / `Vocabulary, naming, and keeping the lexicon honest.` | :314 | Neutral sample text |
| 10 | `Vocabulary and the council lexicon.` (tag `naming` description) | :329 | `Vocabulary and the tag catalogue.` |
| 11 | `Queues, holds, and the warden's table.` (tag `moderation` description) | :331 | `Queues, holds, and moderation.` |
| 12 | `Tokens, registers, and the twilight theme.` (tag `theming` description) | :338 | Sample data — `Tokens, registers, and the dark theme.` |
| 13 | `Older twin of #eval — merge candidate.` | :339 | Sample data only; harmless register, but it is seed content, not chrome |

**Not fiction, but factually wrong and equally unshippable** (record as copy fixes, rows 10 / 17 / 25 above):

| Design string | Line | Why wrong | Proposed |
|---|---|---|---|
| `Renaming is safe — the old slug keeps working.` | :50 | True for **boards** (`board_slug_history`, migration 0007) but categories have no slug at all (`0002_categories.php:11-14`) | `Renaming a board is safe — its old link keeps working.` |
| `Archiving hides a board without losing a word of it.` | :50 | Archive is **read-only, still visible and searchable** — `AdminController.php:581` "its content stays visible, but nobody … can post, reply, react, or moderate"; flash `Board archived — it is now read-only.` (`:434`) | `Archiving makes a board read-only — its content stays visible.` |
| `Saved. The old slug keeps working.` | :74 | Category-level claim about a slug that does not exist | `Saved.` (or omit the chip) |
| `That category is already first in the order.` | :53 | Production's boundary move is a tested no-op, not an error | Do not ship (row 11) |

---

## 4. State inventory

| Design state | Verbatim design string | Production equivalent | Verdict |
|---|---|---|---|
| `reorderError` | `That category is already first in the order.` | `reorder_error` renders only for a bad `dir` (`Direction must be "up" or "down".` `AdminService.php:474`) or a bad id-set (`The submitted order must contain exactly the existing items.` :515); boundary is a no-op | **feature-changed** — adopt the banner skin, not the trigger |
| `structureEmpty` | `No categories yet` / `A council needs at least one room…` | none | **gap → copy** |
| `cat.saved` | `Saved. The old slug keeps working.` | flash `Category updated.` (`AdminController.php:115`) | **feature-removed** (no category slug) |
| `cat.nameError` | `A category needs a name.` | `Category name must be 1–64 characters.` at `structure.php:44-46`, 422 via `structureView(['update_category_id'…], 422)` :109-113 | present, restyle |
| `categoryError` | `Give the category a name before adding it.` | same message, `structure.php:85-87`, 422 via :93-96 | present, restyle |
| `boardError` | `Please fix the highlighted fields.` | **identical string** `structure.php:100` + per-field errors | match |
| `canAddBoard` | section hidden when no categories | `if (!empty($categories))` `structure.php:96` | match |
| `bSlugHint` | `derived from the name` / live slugified value | label text only (`structure.php:115`) | **gap → copy** (static placeholder; live preview optional PE) |
| `tagError` (empty) | `Give the tag a name before adding it.` | `Tag name must be 1-80 characters.` (`TagController.php:255`) | present, reword |
| `tagError` (over-length slug) | not modelled | `Tag slug must be 64 characters or fewer.` (`TagController.php:261`), pinned by `AppTagAdminTest.php:40` | production-only, keep |
| `tagError` (duplicate) | `A tag with the slug “x” already exists. Merge into it rather than adding a twin.` | `That tag slug is already in use.` (`TagController.php:102,124`) | reword (copy) |
| `t.saved` | `Saved` | flash `Tag updated.` (`TagController.php:126`) | **feature-changed** |
| `noTags` (no query) | `No tags yet` / `Add the first tag above. Fewer, sharper tags beat many vague ones.` | `No tags yet.` (`tags.php:35`), no body | partial → copy |
| `noTags` (query) | `Nothing matches “x”` / `Try a shorter phrase, or add it as a new tag above.` | none (no search) | **gap → copy** |
| `tagResultLabel` | `N tags` / `1 tag` | none | **gap → copy** |
| `showTagPager` / `pageLabel` / `atFirstPage` / `atLastPage` | `Page N of M`, `Previous`, `Next`, disabled ends | none | **gap → copy** |
| `t.canMerge` | select + `Merge…` only when enabled and >1 enabled tag | identical gate `tags.php:40,73` | match |
| `mergeArmed` | inline panel | `GET /admin/tags/{id}/merge` page | **constraint** (mechanism), copy identical |
| `mergeImpact` | `N tag associations (includes hidden, held, and deleted threads)` | **identical** `tag_merge_confirm.php:18` | match |
| `b.archiveLabel` | `Archive` / `Unarchive` | two links `structure.php:69-73` | match |
| loading state | **none modelled** | n/a (server-rendered) | no gap |
| — | — | **Production-only states with no design counterpart:** category-delete blocked (`This category still has N boards…` :539); board-delete blocked (`…there is no other unarchived board to move them to.` :616); typed-confirm failures (`Enter the category name exactly to confirm deletion.` :136, `Enter the board slug exactly to confirm deletion.` :342, `Enter the board slug exactly to confirm.` :431,454); move-destination errors (`The destination board is archived (read-only)…` :311); roster errors (`@x already moderates this board.` :556, `No member found with the username “x”.` :671); success flashes `Board archived — it is now read-only.` :434, `Board restored — posting re-enabled.` :457, `Moved N threads and deleted the board.` :355 | **feature-added** — keep and style |

---

## 5. Slice proposal

Each slice is independently shippable, independently testable, and leaves the console green on its own.

### S1 — Content-screen register (structure + tags heads, tab strip, empty states)
**Touches:** `templates/admin/structure.php`, `templates/admin/tags.php`, a new `templates/admin/_content_tabs.php` partial, `public/assets/app.css`.
**Does:** eyebrow `Operator desk · Content` on both heads; `.pane-intro` on structure with the rewritten (de-fictionalised, factually corrected) sentence; the real-link tab strip (`Boards & categories` / `Tags`) with `aria-current` and flag-aware Tags; structure empty state; tag empty states incl. the search variant placeholder; `.admin-head h1` display scale; `.pill-admin` → review pair.
**Tests:** new `AppAdminContentRegisterTest` asserting the eyebrow/intro strings, the empty-state copy at zero categories, `aria-current="page"` on the active tab, and that the Tags tab renders disabled (never as a link) with `tags` off — extend `tests/Integration/Core/AppFeatureFlagTest.php`. Browser: a `content-console.spec.ts` desktop+mobile screenshot pair plus an axe scan; a `javaScriptEnabled:false` context proving both tabs navigate.
**Risk:** low. **Does not touch any 422 path.**

### S2 — Structure body anatomy (category cards + board rows)
**Touches:** `templates/admin/structure.php`, `public/assets/app.css`.
**Does:** gradient card head + hairline; display-font rename input with the gold focus ring; SVG arrow icon-buttons replacing `↑`/`↓`; board row gets the description line, the `#name` → `/c/{slug}` link, separate `Hidden`/`Private`/`Archived` chips, mono thread count, bottom hairline; row actions restyled as ghost buttons; ≤860px single-column fallback.
**Tests:** extend `AppAdminTest`/`AppAdminArchiveTest` to assert the board description renders escaped, the `/c/{slug}` href, and that the visibility chip prints `Hidden`/`Private` (capitalised) rather than the raw enum. Re-run `AppAdminStructureReorderTest` unchanged (proves the up/down POSTs and the boundary no-op survive). Browser: no-JS reorder + screenshot.
**Risk:** low–medium (the rename form's `$catFailed` old-value round-trip at `:22,27` must survive — assert it).

### S3 — Confirmation-page idiom (structure_confirm + tag_merge_confirm)
**Touches:** `templates/admin/structure_confirm.php`, `templates/admin/tag_merge_confirm.php`, `public/assets/app.css` (`.confirm-card`, `.impact-list`).
**Does:** ports the design's merge-panel anatomy — rust 3px left rule, `--shadow-sm`, uppercase micro-`<dt>` + mono `<code>` `<dl>`, danger + ghost button pair — onto both confirmation templates; rust wash for the blocked banner. **No route, no copy, no behaviour change.**
**Tests:** `AppAdminArchiveTest` + `AppTagMergeTest` re-run untouched; add assertions that the typed-confirm input and the `move_to_board_id` picker are still present and that a bare POST still 422s. Browser: no-JS walk of category-delete (blocked and unblocked), board-delete-with-move, archive, unarchive, tag-merge; axe on each.
**Risk:** medium — these are the destructive flows; the diff must be CSS + markup shell only.

### S4 — Tag catalogue substrate (uses, search, sort, pager)
**Touches:** `src/Repository/TagRepository.php` (`allForAdmin` → filtered/sorted/paged + `usage_count`), `src/Controller/TagController.php` (`admin`, `renderAdminTags` must carry `q`/`sort`/`page` through the 422 re-renders), `templates/admin/tags.php`, `public/assets/app.css`.
**Does:** the catalogue head row (GET search form, segmented sort, `N tags`), the per-row uses column, the server-rendered pager, the search-empty state.
**Tests:** unit/integration on the new repository method (count excludes nothing at admin scope — state the rule); `AppTagAdminTest` extended so a 422 from a row edit **preserves `q`/`sort`/`page`** (this is the anti-draft-loss risk in this slice); `AppFieldErrorA11yTest` re-run to prove `id="err-name"` survives the `Add a tag` grid rewrite. Browser: pager `aria-label`s + axe.
**Risk:** medium — the only slice with a query change and the only one that can silently break the 422 round-trip.

### S5 — Board edit + rosters in the idiom (feature-added surface)
**Touches:** `templates/admin/board_edit.php`, `public/assets/app.css`.
**Does:** two-column grid for the settings form matching S1/S2's `Add a board` skin; roster cards reuse the category-card shell; roster rows reuse the board-row anatomy; adds the missing back link to `/admin/structure` alongside the existing `Cancel`.
**Tests:** `AdminBoardSettingsTest` re-run; add an assertion that the roster 422 re-render still refills the offending username (`board_edit.php:118,145` / `AdminController::boardEditView` :472-486). Browser: screenshot + axe; no-JS roster add/remove.
**Risk:** medium (four roster POST paths re-render through this template).

### Cross-cutting, required before any slice is called done
`composer verify:imladris` will fail on the first template/CSS edit — refresh **only** `application_surface.sha256` in `config/imladris-runtime-baseline.json` from `php bin/build-imladris-assets.php --print-application-digest`. CSP scan `rg -n "<script|<style| on[a-z]+=" templates/admin -S` must stay clean. Evidence filing per F2: `docs/evidence/<slice>/{desktop,mobile,comparisons}` + `docs/evidence/<slice>.md`.

### Sequencing note
S1 → S2 → S3 → S5 are pure presentation and can land in any order after S1 (which creates the tab partial and the shared head skin). S4 should land last: it is the only slice that changes a repository signature and a 422 payload, and it depends on S1's tag-screen head for its search row.
