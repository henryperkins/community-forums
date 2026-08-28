# ADR 0028: Imladris board index — fidelity audit, remediation, and deferrals

**Date:** 2026-08-27
**Status:** Accepted and implemented on `main` (baseline `7c66e2fd`).
**Relates to:** `templates/board-index/BoardIndex.dc.html` in the Claude Design
project `c3e02753-607c-40b6-994c-9ba1a65bb367` (the design this records against);
commit `0d2fe9b3` and the merge `2b272e5c`, which transferred the surface;
`docs/superpowers/plans/2026-08-27-member-surfaces-production-transfer.md`;
ADR 0027 (the board-page adoption that set this pattern); ADR 0024 (the first
design-project adoption). CLAUDE.md's rule that deferrals and reversals are
recorded in an ADR and never silently dropped.

## Context

The member-surfaces transfer implemented the board index — `/` — from the
Imladris design in commit `0d2fe9b3`. The transfer is substantially faithful: a
string-for-string comparison confirms all six order labels, all six order notes,
the hero, the lede, the guest note and the three peek empty labels are verbatim
from the design; every board rank key reproduces the design's `rankKey`; every
peek topic order reproduces `pickTopics`, including the `unanswered` and `solved`
filters; and the ranked-vs-grouped switch dissolves categories into one
headingless list exactly as the design demands.

A six-dimension adversarial audit of the landed surface against the design found
**78 findings, one of which was refuted on verification**. Two were P0, and both
were invisible to the transfer's own review because neither PHPUnit nor the
committed screenshots could see them:

1. **The pre-rewrite stylesheet block was never deleted.** `app.css:9181-9208`
   still carried the `.board-index .forum-directory__*` rules the transfer
   replaced. Its `.board-index`-scoped selectors outranked the new ones, and
   `display: grid; grid-template-columns: minmax(0, 1fr) auto` on the board
   `<article>` put the row in column 1 and the **entire peek list in column 2** —
   so the topic previews rendered *beside* the board they belong under, on the
   default view of the forum's front page, for every visitor at any width from
   681px up. Below 680px a legacy media rule re-declared a single column, so the
   phone screenshot looked right and hid the defect. The same block also stole
   the totals line's colour, size and top margin, added a stray hairline under
   every category heading, and pinned a `min-height: 68px` floor that made
   compact density not compact.

2. **Three of the four panes shipped with no CSS at all.** Eleven classes the
   Tags, Notices and Connections panes emit — `.directory-light-pane`,
   `.directory-pane-heading`, `.directory-pane-actions`, `.directory-signin-state`,
   `.directory-tag-list`, `.directory-notice-list`, `.directory-connection-tabs`,
   `.directory-people-list` and their kin — had zero rules in `app.css` or
   `imladris.css`. Under strict CSP there are no inline styles and no bare `ul`
   or `button` rule to fall back on, so those panes rendered as UA disc-bulleted,
   40px-indented lists containing native grey system-ui push buttons inside a
   serif Imladris shell. The Boards pane in the same commit was fully styled.

Both are the same class of miss: **the transfer's evidence could not see them.**
The committed desktop capture is a 924×540 slice that stops above the first
complete board row, and the approved reference PNGs are the same size, so the
comparison method was structurally blind below the fold. No Playwright spec
touches the three panes.

## Decisions

### Fixed

| # | Change | Why |
|---|---|---|
| 1 | Delete the superseded `.board-index .forum-directory__*` block (`app.css:9181-9208`) and its `@media (max-width: 680px)` companions | Every selector in it either had no markup left (`__categories`, `__category-heading`, `__copy`, `__counts` — zero hits across `templates/`, `tests/` and the JS) or outranked its own replacement. Deleting restores the design's block layout: the peek list sits beneath its row. |
| 2 | Write the eleven light-pane classes into `app.css` | Reuses the surface's own type and rules rather than a second visual language, per the design's "light reads that need no shell of their own". Every colour is a semantic token, so the register flips with the theme. |
| 3 | Compact density drops the **description only**; the peek keeps the reader's choice | The design is explicit that compact is the triage register and "the description is the first thing to go" (`BoardIndex.dc.html:607`). Hiding the peek made the Viewing bar's Peek control a silent no-op for every member on compact — a control that still rendered as On. Compact now only tightens the peek's padding, matching the design's `[data-bpeek]` rule. |
| 4 | The Notices tab's unread count is resolved for **every** pane | The dot exists so a member reading another pane learns something is waiting (`BoardIndex.dc.html:620`). It was computed only inside the `pane === 'notices'` branch, which put the signal in the one place it could say nothing new. |
| 5 | A notice names its topic, and unread states itself in text | `NotificationRepository::recent()` already selected `thread_title`; the pane discarded it, so every notice of a kind read identically. The topic is now its own element — only it is quoted, and only it changes weight when unread. The unread mark carries an `sr-only` word so the state never rests on colour alone. |
| 6 | "Mark all read" is disabled when nothing is unread | `BoardIndex.dc.html:244`. Offering it with nothing to mark states a queue that is not there. |
| 7 | The phone Viewing sheet can be closed | Without JavaScript a `<details>` can only be closed by its own `<summary>`, and the sheet's scrim — a child of that `<details>` — outranked it. The open disclosure now raises its own stacking context and lifts the summary above both scrim and panel. The sheet was previously a trap with no way back. |
| 8 | The anonymity join no longer fails open | `directorySignals` joined the OP with `is_deleted = 0 AND is_pending = 0`, so a thread whose OP was soft-deleted lost the row carrying `is_anonymous = 1`, `COALESCE(…, 0)` defaulted it to "not anonymous", and the peek printed the **real author of an anonymously posted topic**. The join now matches the canonical one in `listBoardRows` (`op.is_op = 1` alone): anonymity is a property of the post that survives its moderation state. |
| 9 | `/search` keeps a route into it on phones | The rail rewrite dropped the mobile-only Search link and `app.css` already hid the topbar entry below 861px, so `/search` was unreachable from the shell on every phone. The entry collapses to its icon instead, the move New topic makes beside it. |
| 10 | Guest Viewing controls state their selection with `aria-current`, not `aria-pressed` | `aria-pressed` is a toggle-button attribute and is not valid on a link. A guest — whose controls are links, because there is no preference to write — was never told which order or peek was active. A member's control is a real `<button>` and keeps `aria-pressed`. |
| 11 | Bulk notice actions return to the pane they were invoked from | Notices is a pane of this surface as well as a standalone page; `read-all` and `clear` always redirected to `/notifications`, throwing the member off the board index. The return target reuses `SettingsController`'s vetted guard (`#^/(?![/\\])#`), so it can never be pointed off-site. |
| 12 | The order note pluralises its own board count | It read "1 boards" on a one-board directory. |
| 13 | The Notices tab dot paints `--gold-ink`, not `--gold-500` | `--gold-500` is a single numbered primitive the twilight register never remaps; at 6px it is a non-text cue that must clear 3:1 against the page in both registers. `--gold-ink` flips (`#7E5F22` → `--gold-400`) and clears it in each. Same class of bug ADR 0027 found in four pills. |
| 14 | The reading column centres in its pane | `.main > .read-main { margin: -24px }` exists to cancel `.main`'s own 24px padding. `body[data-route="boards"] .main { padding: 0 }` removed that padding but left the negative margin, which dragged the column 24px left and — as a `margin` shorthand at (0,2,0) — also overrode `.board-index { margin: 0 auto }` at (0,1,0), so the surface could never centre. Measured against the design's own render at 1440px: `h1` at x=361 against the design's 469. Now 469. |
| 15 | The board row is **flex**, not a three-track grid | The design sets the name to its own width and lets the description follow 16px later. `grid-template-columns: minmax(150px, .55fr) minmax(180px, 1fr) auto` padded every short board name out to a 218px track and pushed its description ~90px clear of it, so name and description read as two columns instead of one phrase. Measured gap now 16px against the design's 15px. `margin-left: auto` on the facts group also fixes the case where a board has no description. |
| 16 | The density statement wraps to the left of the second line | The design's bar is `VIEWING · orders · [spacer] · PEEK`, with the density statement simply wrapping. `margin-left: auto` pinned it to the right edge of that second line, stranded from the bar it qualifies. The spacer role moves to the Peek group; scoped to the desk bar so the inbox and the phone sheet keep theirs. |

### Kept against the design

| # | Production keeps | Why |
|---|---|---|
| 14 | Three `data-forum-total` spans with pluralised nouns, rather than the design's single `toLocaleString()` mono string | The attributes predate this transfer and are asserted directly; the three-span shape is a deliberate keep. The missing thousands separators are recorded as a deferral below, not as part of the shape. |
| 15 | Pluralised board counts on the row (`1 topic · 1 post`) where the design hardcodes `topics`/`posts` | A strict improvement, and consistent with the rest of the product. |
| 16 | A deterministic `category_id, position, id` final tie-break on every ranked order | The design has none, so equal signals could reorder between requests. |
| 17 | The rail's own quick-filter routes stay out of the rail | The design's "boards and nothing else" premise holds only because the topbar carries cross-surface travel. It does — with the one exception fixed at #9. |

### Deferred

None of these are fixed here. They are recorded so they are not lost.

1. **The directory query scans the whole live corpus.** `directorySignals`'s
   `base` CTE materialises every non-deleted, non-pending thread in every listed
   board before ranking, on every front-page render. It is one round trip and it
   is correct, but it does not bound the work by the peek size. Measured on a
   synthetic 200k-topic forum it is seconds, not milliseconds. The fix is a
   bounded per-board pre-filter feeding the window function; it is an
   architectural change to the query, not a patch, and wants its own branch and
   its own before/after measurement.
2. **`threads.status` is read with no `topic_workflow` gate.** The `unanswered`
   and `solved` orders read the status column unconditionally, so rolling the
   flag back leaves the retained subsystem visible on the front page. Every other
   consumer gates it (`ThreadRepository::listBoardRows`).
3. **A settled topic with zero replies counts as unanswered.** `is_unanswered` is
   `status = 'needs_answer' OR reply_count = 0`, which matches the design's
   comparator exactly — but a topic explicitly marked Solved or Decision made
   with no replies is counted in the board's "N unanswered" signal and labelled
   "no answer" in its peek. The design has the same flaw; production should
   probably not.
4. **The peek's ordering ignores `is_pinned`**, so the topics it previews are not
   the topics at the top of the board page it hands off to.
5. **The pane tabs and the connection tabs drop `sort` and `peek` from the URL**,
   so leaving Boards and coming back loses a guest's view entirely and breaks the
   design's addressability contract.
6. **The Connections counts and the rendered list come from different
   populations** — the counts are unfiltered totals, the list is filtered by
   blocks and capped at 100 — so blocking a follower makes the header contradict
   the rows beneath it.
7. **Totals lose the design's thousands separators.**
8. **`/tags/{slug}` does not carry the design's tagshow affordances** (the follow
   control, "Showing N of M topics") and offers no return trip to the pane.
9. **Browser evidence for this surface is thin.** The desktop capture stops above
   the first complete board row and nothing exercises the three panes. Captures
   should be re-taken at a height that includes at least two complete board rows
   with their peek lists, in both registers, plus one capture per pane.
10. **The app shell is `max-width: 1280px` and centred; the design is
    full-bleed.** In the design the rail is `flex: 0 0 var(--sidebar-w)` at x=0
    against the viewport edge, and the panes fill the window. Production centres
    the whole shell in `--maxw`, so at 1440px the rail begins at x=73 with dead
    space either side. This is the one structural difference left between the
    two renders, and it is **not** a board-index decision: `.app-shell` is the
    frame for every member surface — thread view, board page, inbox, search,
    compose — so changing it is a product-wide call that wants its own change
    and its own evidence across all of them, not a side effect of this one.

## A note on method

The first pass of this work compared the design *source* to the implementation
and concluded the surface already matched. It did not: rendering both and
measuring them is what exposed items 14–16, and they were the differences a
reader actually notices. Source parity is not visual parity. The comparison
harness — render `BoardIndex.html`, render production, measure the same
elements in both — is the check worth keeping, and its numbers are recorded in
`docs/evidence/imladris-board-index-remediation/README.md`.

## Consequences

The forum's front page renders its primary list correctly for the first time
since the transfer, and its three secondary panes render as designed rather than
as unstyled markup. One anonymity leak is closed. `tests/Integration/Core/AppForumIndexRemediationTest.php`
pins each fix; the anonymity test was confirmed to fail against the pre-fix join,
printing the real author's name into a peek row.

The audit's most transferable lesson is about evidence, not CSS: **a screenshot
cropped above the fold is not evidence of a list.** Both P0s lived below 540px on
the desktop capture. A stylesheet assertion — the pattern already used by
`AppImladrisFidelityTest` — would have caught the second one at merge time for
almost nothing, and is now in place for every class this surface emits.
