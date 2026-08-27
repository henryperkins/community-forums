# Handoff: the member surfaces — board index, forum inbox, search, compose

> **Reviewed source mirror.** Imported from `CommunityForumDesignSystem.zip`
> (SHA-256 `8683122937E85111F76E8A29579D284A314845DD4E33956DE0E9BA10054090EC`)
> on 2026-08-27. These files are reference only: the `.dc.html` markup and its
> prototype loaders must not ship in the PHP application. Production ownership
> and reconciliation decisions are recorded in
> `docs/superpowers/specs/2026-08-27-member-surfaces-production-transfer-design.md`.

## Mirror reconciliation

The archive's `fonts.css`, `spacing.css`, `typography.css`, and `styles.css` are
byte-equivalent to the current mirror. Its `colors.css` and `components.css`
predate newer local reconciliations, so they were reviewed but not copied over
the canonical files. The current source deliberately retains the semantic
twilight-safe staff/tier contrast, scoped field hints, complete operator-bar
cluster, hash/presence treatment, and link-preview form anatomy documented in
`LOCAL_RECONCILIATION.md`. Member-surface additions are made in the current
source and generated with `composer build:imladris` rather than by restoring
those older snapshots.

## Overview

This bundle settles a division of labour that `henryperkins/community-forums` currently answers inconsistently, and specifies the four member reading/writing surfaces that come out of it.

The problem it fixes: route `/` and route `/inbox` had drifted into near-duplicates. Both carried a rail, a list, an optional third pane, ordering controls and per-board unread markers. A member could not tell from the layout which one answered which question, and two surfaces disagreed about the same numbers.

The resolution, in one line: **`/` is the place; `/inbox` is your attention.** Everything that reads differently for different members lives in the inbox and nowhere else.

Four surfaces are specified here:

| Route | Surface | Panes |
|---|---|---|
| `/` | Board index | rail + directory |
| `/inbox` | Forum inbox | rail + queue + reading pane |
| `/search` | Search | rail + results |
| `/compose` | Compose | rail + editor |

Search and compose are **new top-level surfaces**. They previously rode inside the board index's shell as panes and are split out here.

> **This supersedes `design_handoff_board_index/`** in the same project. That bundle specified a three-pane board index with a board-preview column and a Digest (`/feed`) pane. Both are removed by this pass — see *Removals* below. Where the two documents disagree, this one is current.

## About the design files

The files in `design/` are **design references authored in HTML** — prototypes showing intended look and behaviour. They are **not production code to copy**.

They are Design Components (`.dc.html`): a template of markup plus a small JavaScript logic class, rendered by `support.js`. Constructs like `<sc-if>`, `<sc-for>`, `{{ hole }}` and `<x-import>` belong to that runtime and have no meaning outside it. **Do not port them.** Read them for structure, values and behaviour; write the real thing in the target environment's idiom.

The target codebase is **PHP 8 server-rendered templates** (a hand-rolled `View` class with `$this->layout(...)` / `$this->partial(...)`), progressively enhanced with vanilla JS, styled by one stylesheet at `public/assets/app.css`. There is no build step and no component library. `PRODUCTION.md` in the design system holds the runtime contract (strict CSP, no inline handlers, must work without JS).

If you are implementing this somewhere other than that codebase, recreate the designs in whatever environment already exists there — React, Vue, SwiftUI, native — using its established patterns. If no environment exists yet, choose the framework that suits the project and implement the designs in it. The HTML is the specification, never the artifact to ship.

Two consequences that shape the whole task:

1. **Every pane must work with JavaScript off.** The rail, the lists and the reading pane are all server-renderable. Scope, order and peek are query parameters, not client state. JS upgrades the reading pane to an in-place swap; it is not required for it to function.
2. **Panel visibility is a preference, not component state.** ⌘B is a convenience over a persisted user preference, so the server can render the correct pane configuration on first paint with no flash.

## Fidelity

**High-fidelity.** Colours, typography, spacing and interaction states are final and are given as exact token names below. Reproduce them precisely.

One qualification: use the **token names**, never resolved hex values. The app ships this token set as custom properties, and the dark register (`[data-theme="dark"]`) is already wired. A literal hex anywhere in this feature is a bug — it will not flip with the theme. Hex values appear in this document only so you can verify you have the right token.

---

## The decision this implements

### One object, one owner

| Route | The question | Must not become |
|---|---|---|
| `/` | "What boards exist?" | a feed, or a queue |
| `/c/{slug}` | "What is in this board?" | personally filtered or re-sortable |
| `/inbox` | "What needs me?" | a place to create topics |
| `/t/{id}-{slug}` | "What was said?" | rendered in full anywhere else |
| `/search` | "Where is the thing I can't name?" | a browsing surface |
| `/compose` | "I want to open a topic" | a modal over another surface |

`templates/home.php` already says this in words — *"Browse the listed boards and pick one to see its topics. Use Forum inbox for your personal cross-board queue."* This design makes the layout say it too.

### The line is personalisation of reading

Every signal that exists only in relation to a member — **unread, mentions, replies to you, watching, assigned, snoozed, starred** — lives in the inbox. The board index carries no pinning, no muting, and no unread on its rows. Its ordering is derived only from board-level facts (last reply, topic age, reply count, commend count, workflow status), so every member sees the same order.

The one personal number on the index is the **rail's unread pill**, and that belongs to the rail rather than to the surface (see below).

### The three panes, one grammar

| Pane | Answers | Contains | Never |
|---|---|---|---|
| **Rail** | *where* | The community's boards, in the community's order, plus the public presence roster | A list of items; a set of personal scopes; anything the reader can reorder |
| **Centre** | *what* | The surface's own set — the only pane that differs between routes | — |
| **Third** | *glance* | Reading an **item** without losing your place in the list | A place — a board gets a handoff, not a preview |

Centre-pane controls follow one grammar — **scope** (which) · **order** (in what sequence) · **peek** (how much of each row) — and each surface uses only the axes it has:

| Surface | scope | order | peek |
|---|---|---|---|
| Board index | — | ✓ (6) | ✓ (0/3/5) |
| Forum inbox | ✓ (12) | ✓ (3) | — |
| Search | ✓ (4) | ✓ (2) | — |

**Density is never a pane control.** It is an account preference that rules every list in the product. Each list *states what it inherited* and links to the one place that owns it (`/account/appearance`). The inbox previously had its own toggle; that is removed, because two places could disagree.

### The rail is chrome, not content

The rail is byte-for-byte identical on every app route: the same eight boards, in the same categories, with the same unread pills, followed by the same presence roster and "See everyone" link. Only one row affordance varies — on compose the rows are destination-picker buttons rather than links, and the warden-only board is shown disabled rather than omitted.

**This is a hard invariant.** In the design files the board list is duplicated across four `.dc.html` files with a comment in each naming the other three; in the real codebase it must be **one partial reading one query** (`templates/partials/sidebar.php` already exists for this). If the same member sees a different unread total on two surfaces, the invariant is broken.

---

## Screens / views

Common chrome first, then each surface's centre pane.

### Topbar — every surface

Fixed row, `flex: 0 0 var(--topbar-h)` (62px), `z-index: 20`, `padding: 0 22px`, `box-sizing: border-box`, `display: flex; align-items: center; gap: 18px`.

- Background `color-mix(in srgb, var(--surface-raised) 92%, transparent)` with `backdrop-filter: blur(10px)`; `border-bottom: 1px solid var(--border-hair)`. This is one of only two deliberate uses of transparency in the system.
- **Wordmark** — 24px eight-point star (`assets/elven-star.svg`, `fill: var(--accent)`, outer ring at `opacity: .2`) + "Imladris" in `var(--font-display)` 600 / 1.25rem / `var(--text-strong)` / `letter-spacing: .01em`. Gap 10px.
- **Primary nav** (`<nav aria-label="Primary">`, `gap: 4px`) — **Boards · Inbox · Messages**. Each: `padding: 6px 12px`, `border-radius: var(--radius-md)`, `var(--font-label)` .82rem, `letter-spacing: .02em`, `text-decoration: none`. Current: `background: var(--brand-subtle)`, `color: var(--on-brand-subtle)`, `aria-current="page"`. Others: transparent / `var(--text-muted)`, hover `background: var(--surface-sunken)`, `color: var(--text-body)`.
  - Inbox carries an unread count pill when non-zero: `padding: 0 6px`, `border-radius: 999px`, `background: var(--gold-soft)`, `color: var(--gold-ink)`, `var(--font-mono)` .64rem. **This is the same number as the sum of the rail's pills.**
  - Cross-surface travel lives here *because* the rail is boards-only. Do not put routes in the rail.
- **Search entry** — a link, not a field, on every surface except `/search` itself: `flex: 0 1 300px; min-width: 180px`, height 34px, `padding: 0 14px`, `background: var(--surface-sunken)`, `1px solid var(--border-hair)`, `border-radius: 999px`. Magnifier 13px stroke-2 `var(--text-faint)`; placeholder text "Search the council…" in `var(--font-label)` .76rem `var(--text-faint)`; `⌘K` hint right-aligned in `var(--font-mono)` .64rem. Hover: `border-color: var(--border-soft)`.
- **Rail toggle** — 30×30, `border-radius: var(--radius-sm)`, `title="Hide/Show the board rail (⌘B)"`, `aria-pressed`. On: `background: var(--brand-subtle)`, `color: var(--on-brand-subtle)`. Off: transparent / `var(--text-faint)`. Icon is a 16px rounded rect outline with a filled left column at `opacity: .5` when open. The inbox has a second identical toggle for the reading pane (⌘J).
- **Divider** — 1px × 22px `var(--border-hair)`.
- **New topic** — design-system `Button` `size="sm"`, `href="/compose"`. Absent on compose itself.
- **Identity** — 30px circle `background: var(--gold-100)`, `color: var(--gold-700)`, initials in `var(--font-label)` .72rem; name in `var(--font-label)` .8rem `var(--text-body)`. Gap 9px.

### Rail — every surface

`<nav aria-label="Boards">`, `flex: 0 0 var(--sidebar-w)` (272px), `padding: 20px 12px 32px`, `overflow-y: auto`, `box-sizing: border-box`, `background: var(--surface-sunken)`, `border-right: 1px solid var(--border-hair)`, `display: flex; flex-direction: column; gap: 2px`. Fades in at 160ms.

- **Category heading** — `padding: 16px 0 6px 12px`, `var(--font-label)` .62rem, `letter-spacing: .18em`, `text-transform: uppercase`, `color: var(--text-faint)`.
- **Board row** — `padding: 7px 12px`, `border-left: 2px solid transparent`, `border-radius: 0 var(--radius-md) var(--radius-md) 0`, `var(--font-label)` .84rem, `letter-spacing: .02em`, `color: var(--text-muted)`, `text-decoration: none`. Name truncates with ellipsis. Hover: `background: var(--surface-page)`, `color: var(--text-body)`.
  - Active (compose only): `border-left-color: var(--gold-500)`, `background: var(--brand-subtle)`, `color: var(--on-brand-subtle)`. The inset gold rule is the system's active marker everywhere.
  - **Unread pill**, when > 0: `padding: 0 6px`, `border-radius: 999px`, `background: var(--gold-soft)`, `color: var(--gold-ink)`, `var(--font-mono)` .66rem, with `title`/`aria-label` "N unread in {board}".
- **Presence block** — `margin-top: 24px`, `padding-top: 16px`, `border-top: 1px solid var(--border-hair)`; the design system's `PresenceList` (`layout="list"`, `max=5`, live count) followed by a "See everyone" link → `/users-online`, `var(--font-label)` .74rem `var(--text-muted)`, hover `var(--accent)`.
- Presence is public and says nothing about the reader, which is why it is rail furniture rather than a personal signal.

The eight boards, in order: **The Commons** — announcements, introductions, the-archive, the-valley. **Vilya · Expose** — interpretability, evaluations, audit-trails, capability-disclosure.

---

### 1. Board index (`/`) — `screenshots/01-board-index.png`

**Purpose.** Read the shape of the community and choose where to go.

**Layout.** Rail + one scrolling column. `flex: 1 1 0; min-width: 0; overflow-y: auto; padding: 26px 40px 90px`, inner wrapper `max-width: 760px; margin: 0 auto`. **No third pane** — the base `.app-shell` grid, nothing nested.

**Pane tabs.** A ruled row above the content: **Boards · Tags · Notices · Connections**. `display: flex; gap: 22px; margin-bottom: 30px; border-bottom: 1px solid var(--border-hair)`. Each `padding: 0 0 9px`, `var(--font-label)` .88rem, `letter-spacing: .03em`. Current: `border-bottom: 2px solid var(--gold-500)`, `color: var(--text-strong)`. Others: transparent bottom border, `color: var(--text-faint)`, hover `var(--text-body)`; Notices carries a 6px `var(--gold-500)` dot when unread. These are *this page's* tabs, distinct from the topbar's primary nav.

**Hero.**
- `<h1>` "Every board in the valley" — `var(--font-display)` 500, `clamp(2rem, 4vw, 2.7rem)`, `line-height: 1.06`, `letter-spacing: -0.015em`, `var(--text-strong)`.
- Lede, `max-width: 50ch`, 1.05rem / 1.6 / `var(--text-muted)`, `text-wrap: pretty`: "Browse the boards the council keeps and pick one to see its topics. Your own cross-board queue is the **inbox**." (inbox is a link to `/inbox` in `var(--accent)`, underline on hover).
- **Totals row**, `var(--font-mono)` .76rem `var(--text-faint)`: `8 boards · 370 topics · 2,741 posts`. These are the three numbers `home.php` already computes by summing `$sections`; keep that computation.

**Viewing bar (desk, > 760px).** `display: flex; flex-wrap: wrap; gap: 12px 22px; margin-top: 30px; padding: 11px 0`, hairline top and bottom.
- Eyebrow "VIEWING" — `var(--font-label)` .64rem, `letter-spacing: .2em`, uppercase, `var(--text-faint)`.
- **Order** pills (6): By category · Active · Newest · Unanswered · Top · Solved. `padding: 5px 12px`, `border-radius: 999px`, `var(--font-label)` .78rem, `letter-spacing: .03em`. On: `background: var(--brand-subtle)`, `box-shadow: var(--gilt)`, `color: var(--on-brand-subtle)`. Off: transparent, `var(--text-muted)`, hover `background: var(--surface-sunken)` / `color: var(--text-body)`, `transition: background 140ms ease, color 140ms ease`.
- **Peek** group — label + segmented control `Off / 3 / 5` inside a 999px `var(--surface-sunken)` well with a `1px solid var(--border-hair)` border and 2px padding. Selected: `background: var(--surface-raised)`, `box-shadow: var(--shadow-xs)`, `var(--text-strong)`, `var(--font-mono)` .72rem, `min-width: 34px`.
- **Density statement** — "{Comfortable|Compact} rows" in `var(--font-label)` .74rem `var(--text-faint)` + a "change" link to `/account/appearance` in `var(--accent)`.

**Viewing bar (phone, ≤ 760px).** The desk row is `display: none`; a summary button replaces it — "VIEWING" eyebrow + a one-line summary ("By category · peek 3") + chevron — opening a bottom sheet. Sheet: `position: fixed`, full width, `border-radius: var(--radius-xl) var(--radius-xl) 0 0`, `background: var(--surface-raised)`, `box-shadow: var(--shadow-xl)`, scrim `rgba(27, 35, 29, .42)`, rises 220ms. Every sheet target is ≥ 44px. **The sheet renders at the screen root**, not inside the scrolling column — an ancestor there establishes a containing block and a `position: fixed` child lands mid-document.

**Order note.** `var(--font-mono)` .72rem `var(--text-faint)`: "{order note} · 8 boards · the same order every member sees."

**The directory.** `margin-top: 34px`, `display: flex; flex-direction: column; gap: 36px`.
- With `sort=category`: one section per category, heading in `var(--font-label)` .72rem `letter-spacing: .2em` uppercase `var(--gold-ink)` followed by a `var(--rule-gold)` 1px rule at `opacity: .45` filling the remaining width.
- With any other sort: **the categories dissolve into one ranked list with no headings.** A ranking that restarts per category is not a ranking.
- **Board row** — `border-bottom: 1px solid var(--border-hair)`; padding 14px vertical (comfortable) / 7px (compact). One link containing: board name in `var(--font-display)` 500 / 1.4rem / `var(--text-strong)`, nowrap; description at .92rem `var(--text-faint)`, truncated with ellipsis, hidden entirely in compact; then right-aligned, a per-sort signal (`var(--font-label)` .68rem uppercase `var(--gold-ink)` — e.g. "3 unanswered", "5h ago", "54 commends") and the absolute counts "N topics · N posts" in `var(--font-mono)` .74rem `var(--text-faint)`. Hover shifts the link 6px right over 160ms.
- **Peek list** — up to N topic titles under each board, `padding-left: 16px`, each row a 4px `var(--border-strong)` bullet + title (.99rem `var(--text-body)`, hover `var(--accent)`, ellipsis) + meta (`var(--font-mono)` .7rem `var(--text-faint)`). The meta text changes with the sort (`by X · 5h`, `by X · opened 2d ago · no answer`, `by X · 31 commends`…). Empty peek prints an italic line ("Every topic here has an answer.").
- **No star, no mute control, no unread dot on any row.**

**Guest state.** When signed out, a note above the directory with a 2px `var(--rule-gold)` left rule on `var(--surface-sunken)`: order and peek still work, held in the URL; logging in remembers them and gives you a queue.

**Other panes.** *Tags* — a wrap of pill buttons (name in `var(--font-label)` .92rem **with `white-space: nowrap`** — hyphenated names must not break — plus a count in `var(--font-mono)` .7rem), hover `border-color: var(--gold-400)` / `background: var(--gold-soft)`; selecting one shows that tag's topics as `ThreadRow`s with `show-board`. *Notices* — a ruled list of account facts with a 6px gold dot for unread, "Mark all read" and "Clear"; the lede states that the topics themselves wait in the inbox. *Connections* — followers/following tabs, `Monogram` + name + `@handle · N rep`, Remove on your own followers only.

### 2. Forum inbox (`/inbox`) — `screenshots/02-forum-inbox.png`

**Purpose.** Work through what wants you, and leave with less of it.

**Layout.** Three columns nested inside `.main`: rail, queue `flex: 0 1 660px; min-width: 430px` with `border-right: 1px solid var(--border-hair)`, reading pane `flex: 1 1 0`. Queue `padding: 26px 30px 40px`; reading pane `background: var(--surface-raised)`, inner `max-width: 760px; padding: 26px 40px 72px`.

**Responsive.** Below **1280px** the reading pane is hidden and the queue takes the full width. The app's own `.inbox-shell` collapses at 860px, which leaves a band where the queue's `minmax` cannot yield because the reading pane has no floor — the secondary column then outranks the primary. Adopt the 1280px floor in the app.

**Header.** Eyebrow "YOUR PERSONAL FORUM VIEW" in `var(--font-label)` .68rem `letter-spacing: .18em` uppercase `var(--gold-ink)`; `<h1>` "Forum inbox" `var(--font-display)` 500 / 2.15rem; an unread chip beside it (`padding: 3px 11px`, `border-radius: 999px`, `1px solid var(--gold-200)`, `background: var(--gold-soft)`, `color: var(--gold-ink)`, `var(--font-label)` .72rem); lede at .98rem `var(--text-muted)` pointing at Boards for the directory and stating that topics are started from their board.

**Viewing bar.** Same eyebrow and same pills as the index, different axes.
- **Scope** — a menu button (not pills; there are twelve) showing the current scope name plus its count in `var(--font-mono)` .68rem `var(--gold-ink)` and a chevron. `padding: 5px 12px`, `border-radius: 999px`, `1px solid var(--border-hair)`. The menu is 244px wide, `max-height: min(44vh, 380px)`, `background: var(--surface-raised)`, `box-shadow: var(--shadow-lg)`, grouped under three uppercase labels:
  - *Your queue* — For You, Unread, Mentions, Replies, Watching
  - *Yours* — Assigned, Starred, Mine, Snoozed
  - *Topic state* — Needs Answer, Decisions, Solved
  - Each item shows its count; the current one takes the 2px gold left rule and `var(--brand-subtle)`.
  - **The menu is positioned against the viewport from the trigger's measured rect**, because the queue is an `overflow: auto` scroller and would clip it.
- **Order** — three pills, identical styling to the index's: Activity · Newest · Commended.
- **"Mark all read"** button, then the **density statement** (same wording and link as the index).
- A full-width mono line beneath: "Ordered by {order} — j/k move · enter open · e read · s star · # snooze".

**Two axes, two parameters.** `scope` says *which* topics, `order` says *in what sequence*. Endpoint contract `/inbox?scope=<scope>&order=<order>`. They must not be collapsed into one enum: "Unread, newest first" is one of the 36 combinations a single enum cannot express. Pinned topics lead in every order — pinning belongs to the board, not to your ordering.

**Rows.** `display: flex; align-items: flex-start; gap: 10px; padding: 8px 12px` (compact: 4px, gap 9px), `border-bottom: 1px solid var(--border-hair)`, `border-radius: var(--radius-md)`, hover `background: var(--surface-raised)`. Left to right: 16px checkbox · 7px gold unread dot · 28px `Monogram` with presence · the body · star toggle · overflow menu.
- Body: a wrapping head where the **title comes first in source but `order: 1`** and the chips `order: 2`, so chips wrap after the title. Title `var(--font-display)` 500 / 1.06rem (compact .99rem) `var(--text-strong)`, hover `var(--accent)`.
- Chips: a gold four-point-star "reason" chip (For You scope only — status scopes are already cued by their status chip), then Pinned / ✓ Solved / Needs answer / Decision / Locked, each `padding: 1px 7px`, `border-radius: 999px`, `var(--font-label)` .56rem, `letter-spacing: .14em`, uppercase.
- Snippet: one clamped line at .88rem `var(--text-muted)`, hidden in compact.
- Meta in `var(--font-mono)` .7rem `var(--text-faint)`: board (as `var(--artifact-link)`) · by author · N replies · commends (only when ordering by commended) · time · "assigned to @x" · "snoozed until X".
- Active row: absolute overlay with `border-left: 2px solid var(--gold-500)` and `background: var(--brand-subtle)`, `pointer-events: none`.

**Selection and sweep.** Shift-click extends from the last checkbox touched over the rows on screen. A sweep bar appears only while a selection exists — sticky, `background: var(--brand-subtle)`, `1px solid var(--green-200)` — offering Mark read · Mark unread · Star · Snooze until Monday · Deselect. **Selection is scoped to the current view**: changing scope drops rows that are no longer visible rather than acting on them invisibly.

**Per-row menu.** Mark read/unread · Snooze… (Later today / Tomorrow / Monday / Next week) · Clear snooze · Assign… · Unassign. Same positioning technique as the scope menu. Any scroll closes it.

**Keyboard.** `j`/`k` move the cursor, `enter`/`o` open, `e` toggles read, `s` toggles star, `#` snoozes to Monday, `Escape` closes menus, `⌘B`/`⌘J` toggle the panes. All suppressed while focus is in an input, textarea, select or contenteditable. **The selection *is* the cursor** and the reading pane follows it.

**Reading pane.** Breadcrumb (board link `var(--artifact-link)` / time) · `<h2>` at `var(--font-display)` 500 / 1.9rem · author row with `Monogram`, name, tier chip and reply count · the opening post at 1.03rem / 1.68 · replies separated by hairlines with an "✓ Accepted" chip where applicable · the design system's `Composer` under an "ADD YOUR COUNSEL" eyebrow · a locked notice instead of the composer when the topic is closed. Empty state: faint gold four-point star, "Choose a topic", and the line that without JavaScript topics open as their own page. On mobile the pane replaces the queue and gains a "Back to topics" button.

**The reading pane shows a preview, not the topic.** `/t/{id}-{slug}` owns the full render. Do not let the pane grow into a second thread view.

### 3. Search (`/search`) — `screenshots/03-search.png`

**Purpose.** Find something whose location you cannot name.

**Layout.** Rail + one column, same measurements as the board index. No third pane.

- `<h1>` "Search the council", `var(--font-display)` 500 / 2.2rem.
- **Query field** — the system's engraved well: `border: 0; outline: 0`, `background: var(--surface-raised)`, `box-shadow: var(--shadow-inset), inset 0 0 0 1.5px var(--gold-200)`, and a chamfered `clip-path: polygon(8px 0, calc(100% - 8px) 0, 100% 8px, 100% calc(100% - 8px), calc(100% - 8px) 100%, 8px 100%, 0 calc(100% - 8px), 0 8px)`. Hover thickens to `var(--gold-400)`; focus is `inset 0 0 0 2px var(--gold-500)` plus a `inset 0 0 0 5px color-mix(in srgb, var(--gold-100) 60%, transparent)` halo. `padding: 12px 15px`, 1.05rem, `caret-color: var(--gold-600)`. Beside it, a primary `Button`.
  - **Because the well sets `border: 0` and draws its edge as background layers, a `border-color` rule for the error state is invisible.** Restate the inset layers in danger ink for `:user-invalid` / `[aria-invalid]`.
- **Viewing bar** — same chrome, this surface's axes: **scope** pills (Everything · Topics · Replies · Mine) on the left, **order** pills (Relevance · Newest) on the right. No peek, no density; results are not a list you live in.
- **Count line** — `var(--font-mono)` .72rem `var(--text-faint)`: "3 results for "rollback" · by relevance".
- **Result** — `padding: 17px 0`, hairline bottom. Kind + board byline in `var(--font-label)` .74rem `var(--text-faint)`; title in `var(--font-display)` 500 / 1.28rem, hover `var(--accent)`; snippet at .96rem / 1.6 `var(--text-muted)`, `max-width: 62ch`.
- **Empty state** — faint gold star, "Nothing matches that.", "Try a shorter phrase, or widen the scope above."

### 4. Compose (`/compose`) — `screenshots/04-compose.png`

**Purpose.** Open a topic. Its own surface because writing is a task with a draft, a destination and a cost to abandoning it — and because it must be reachable from anywhere, not only from the directory.

**Layout.** Rail + one column, inner wrapper `max-width: 700px`. No third pane, no "New topic" button in the topbar.

- **The rail doubles as the destination picker.** Rows are buttons, not links; the selected board takes the active treatment plus a "posting here" label in `var(--font-mono)` .62rem. The warden-only board (`announcements`) renders as a non-interactive `var(--text-faint)` row with a 11px padlock and `title="Only wardens may open a topic here"` — **shown and disabled, never omitted**, so the rail's board list stays identical to the other surfaces.
- Eyebrow "POSTING TO {board}" in `var(--font-label)` .68rem uppercase `var(--gold-ink)`; `<h1>` "Open a topic"; lede "Say what you want the council to consider, and what would change your mind."
- **Title** — engraved well as above, but `var(--font-display)` 1.05rem, `maxlength="160"`.
- **Board** — engraved `<select>`, `max-width: 280px`, native chevron replaced by an inline SVG data-URI arrow in gold-600 at `right 13px center`, `appearance: none`. Kept in sync with the rail in both directions.
- **Body** — the design system's `Composer` in `context="new_thread"`, toolbar open, anonymous posting allowed with its disclosure, `max-length` 20,000, live counter appearing at 90% of the limit.
- **Validation** — a title under 3 characters blocks submission with "Give the topic a title before you open it." shown through the composer's own error slot. Colour is never the only signal; the message restates the fault in words.
- **Footer** — a Cancel link back to `/`, and "Draft kept on this device." in `var(--font-mono)` .71rem once anything has been typed.
- **Success** — a pill toast at `bottom: 34px`, centred, `background: var(--green-800)`, `color: var(--parchment-50)`, gold four-point star, "Topic opened in {board}.", `role="status"`, rising 220ms and clearing after 2.6s.

---

## Interactions & behaviour

| Trigger | Result |
|---|---|
| `⌘B` / `Ctrl+B` | Toggle the rail. All four surfaces. |
| `⌘J` / `Ctrl+J` | Toggle the reading pane. Inbox only. |
| `j` / `k` | Move the inbox cursor. Reading pane follows. |
| `enter` / `o` | Open the cursor row; marks it read. |
| `e` / `s` / `#` | Toggle read / toggle star / snooze to Monday. |
| `Escape` | Close any open menu. |
| Order or peek change (index) | Writes `?sort=` and `?peek=` via `history.replaceState`, and persists to storage for a signed-in member. **Density is deliberately excluded** — the account owns it. |
| Scope or order change (inbox) | `/inbox?scope=&order=`. Resets pagination and clears the selection. |
| Shift-click a checkbox | Extends the selection from the last one touched, over the rows on screen. |
| Scroll, anywhere | Closes open popovers. |

**Motion.** One easing, `--ease-calm` `cubic-bezier(.22,.61,.36,1)`; three durations, `--dur-fast` 140ms / `--dur-base` 240ms / `--dur-slow` 420ms. Content panes rise 6–7px over ~200ms on change; panes fade in at 160ms; the sheet rises at 220ms. Nothing bounces, nothing loops. All of it respects `prefers-reduced-motion`.

**States.** *Hover* — surfaces warm to `--surface-sunken`; rows gain `--surface-raised`. *Focus* — an `--accent` outline **plus** the 3px gold halo (`--focus-ring`). *Active* — `--brand-subtle` wash + inset 2–3px gold rule. *On/"mine"* — `--gold-soft` background with `--gold-ink` text.

**Empty states.** Every list has one, and each names the scope it is empty *for* rather than printing a generic line — the inbox's also restates that order changes sequence, never inclusion, and offers a route back to For You.

## State management

**Server-owned (must render correctly with JS off).**

| State | Where it lives |
|---|---|
| `sort`, `peek` | Query string on `/`, mirrored to per-member storage |
| `scope`, `order`, `page` | Query string on `/inbox` |
| `q`, `scope`, `order` | Query string on `/search` |
| Selected topic | Query string / route on `/inbox`; the reading pane is an enhancement over a real page |
| Rail open, reading pane open | Persisted user preference, rendered server-side on first paint |
| Density | Account appearance preference — global, one owner |

**Client-owned (ephemeral).** Sweep selection and its shift-anchor; open menu and its measured position; keyboard cursor; unsent draft text; toast visibility.

**Data required.**
- Rail: the eight boards with category, name, slug, and the member's unread count per board — **one query, one partial, every surface.**
- Index: per board, topic and post counts plus enough per-topic signal to rank and to peek (last-reply age, opened age, reply count, commend count, workflow status).
- Inbox: topics across all readable boards with the member's relation to each — unread, mentioned, replied-to-you, watching, assigned, snoozed, starred — plus status, pinned, locked, commends, board.

## Design tokens

Use the names. Values shown only for verification.

**Layout** — `--maxw` 1280px · `--sidebar-w` 272px · `--list-w` 410px · `--pane-w` 320px · `--pane-min` 260px · `--topbar-h` 62px.

**Surfaces** — `--surface-page` (parchment-100) · `--surface-raised` (parchment-50) · `--surface-sunken` (parchment-200) · `--border-hair` (parchment-300) · `--border-soft` (mist-200) · `--border-strong` (ink-300).

**Brand & accent** — `--accent` / `--brand` green-700 `#2E4A3A` · `--brand-subtle` green-050 · `--on-brand-subtle` green-800 · `--gold-500` `#C29A44` · `--gold-400` `#D2B062` · `--gold-200` `#EAD9A8` · `--gold-100` `#F4EBCF` · `--gold-soft` (= gold-100) · `--gold-ink` `#7E5F22` (the AA-safe gold for small text — never gold-500 on parchment for body-size text) · `--rule-gold` · `--artifact-link` river-500.

**Ink** — `--text-strong` ink-900 · `--text-body` ink-700 · `--text-muted` ink-500 · `--text-faint` ink-400.

**Type** — `--font-display` Cormorant Garamond 500 (headings, thread titles) · `--font-label` Marcellus (eyebrows, buttons, chips, meta; uppercase with generous tracking) · `--font-body` EB Garamond (prose, 17px / 1.62, measure ≈ 64ch) · `--font-mono` JetBrains Mono (counts, timestamps, routes; tabular numerals).

**Radius** — `--radius-sm` 4px · `--radius-md` 7px · `--radius-lg` 12px · `--radius-xl` 20px · `--radius-pill` 999px.

**Shadow** — `--shadow-xs` … `--shadow-xl`, warm ink `rgba(27,35,29,…)`, never pure black · `--shadow-inset` `inset 0 1px 2px rgba(27,35,29,.07)` · `--gilt` `inset 0 0 0 1px rgba(194,154,68,.38)` (the selected-pill ring and the "precious avatar" ring).

**Motion** — `--ease-calm` · `--dur-fast` 140ms · `--dur-base` 240ms · `--dur-slow` 420ms.

**Twilight.** `[data-theme="dark"]` is already wired: parchment becomes ink, **gold becomes the actionable colour** (`--accent` → gold-400) and evergreen the quiet brand. Every surface here inherits it for free provided no hex is written by hand.

## Removals

Things the previous design carried that this one deliberately deletes. Each was a place where the two surfaces blurred.

| Removed | Why |
|---|---|
| The board-preview third pane on `/` (⌘J) | A board is a destination. Previewing it duplicates the board page badly and gave `/` an inbox's shape. |
| The Digest (`/feed`) pane | Personalised reading, on the impersonal surface. Its replacement is the inbox's `for_you` scope. **`templates/feed.php` still ships upstream — decide whether to retire it or build it as its own personalised surface beside the inbox. It must not return to the index.** |
| Pinning boards ("Your boards") | Reader-specific ordering on a surface whose whole claim is that everyone sees the same order. |
| Muting boards | Same. A muted board changed which rows existed. |
| Unread dots on directory rows | The only personal number on `/` is the rail's, and the rail is shared chrome. |
| Routes in the rail | The rail is boards. Cross-surface travel moved to the topbar. |
| The inbox rail's "Your queue" scopes | A scope is a lens on *that* list, so it belongs in the list's header. In the rail it made the rail look like part of the queue. |
| The inbox's own density toggle | Two owners for one preference. It now states what it inherited. |
| Search and compose as index panes | Neither is a place or a state. Both are their own surfaces. |

Kept as panes of the index: **Tags, Notices, Connections** — light reads that need no shell of their own.

## Assets

- **Eight-pointed elven star** — the house mark. `assets/elven-star.svg`; solid in the wordmark, faint (7–12% gold) as a watermark. Inline copy in the topbar of each design file.
- **Four-point commend star** — the esteem mark. `assets/commend-star.svg` (✦); used for commends, the "reason" chip, and the success toast.
- **Lucide** line icons, stroke 1.75–2, round caps, for everything else (chevrons, magnifier, padlock, panel toggles).
- **Avatars** are `Monogram` — initials on a ground tinted deterministically from the username; real images replace them when present.
- **Fonts** ship as self-hosted WOFF2 in `assets/fonts/` with OFL licences, declared as plain `@font-face` — no CDN, matching the app's `style-src 'self'` CSP. The app itself currently ships no webfonts and falls back to a system serif stack.
- **Do not redraw the brand marks.** Use the SVGs.

## Files

**`design/`** — the prototypes. Open any `.dc.html` directly in a browser; `ds-base.js` resolves the design system from the project root two levels up.
- `BoardIndex.dc.html` · `ForumInbox.dc.html` · `Search.dc.html` · `Compose.dc.html`
- `app-shell.card.html` — **the decision document.** The three shell shapes drawn to their real 1280px proportions, the pane grammar, and the board-index-vs-forum-inbox table. Read this first.
- `thread-row.card.html` — row anatomy, which the inbox's rows follow.
- `support.js`, `ds-base.js` — the prototype runtime. Not for porting.

**`tokens/`** — `styles.css` (the entry point) and the closure it imports: `colors.css`, `fonts.css`, `typography.css`, `spacing.css`, plus `components.css` (the primitives' CSS, values transcribed unchanged from the live app).

**`screenshots/`**
- `01-board-index.png` · `02-forum-inbox.png` · `03-search.png` · `04-compose.png`
- `05-pane-doctrine.png`, `06-pane-doctrine-detail.png` — the app-shell decision card, top and bottom.

**In the design system, not bundled:** `PRODUCTION.md` (runtime contract and parity matrix), `github.md` (provenance, the screen-to-source map, and the open drift including the `/feed` question), `imladris-spec.md` (status taxonomy, button and monogram anatomy).
