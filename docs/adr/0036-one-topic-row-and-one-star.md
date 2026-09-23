# ADR 0036: One topic row, and one star

**Date:** 2026-09-23
**Status:** Implemented; verification results below.
**Relates to:** the component-reuse critique archived at
`.impeccable/critique/2026-09-23T12-17-10Z__templates.md` (priority issue "Two
topic rows, and three stars"); `docs/design-system/imladris/components/forum/thread-row.card.html`;
ADR 0029 (the forum inbox: its geometry is kept here, its separate partial is
not); ADR 0030 (the thread view's "one esteem glyph"); ADR 0034 (the pill
exceptions, which this does not touch); root `DESIGN.md` "Signature: the thread
row" and the new "The star"; PRODUCT_DESIGN §13.

## Context

The design system's own card for the row says it plainly: *"The row is one
structure with a `presentation` axis, exactly as the app's
`partials/thread_row.php` has it."* It draws the board and the personal queue as
two presentations of that one row, and sorts its facts into three classes —
topic-intrinsic facts that must read identically everywhere, viewer-relative
facts that render everywhere (loud in the queue, quiet on the index), and
surface-specific facts.

Production had drifted from that into two row objects and three stars:

- The member-surfaces transfer (`bdd27482`) added
  `templates/partials/inbox_thread_row.php`, a second row with its own class
  vocabulary (`.inbox-thread-row`, `.inbox-row-*`), its own star and its own
  status chips. The board and the tag list kept `thread_row.php`.
- The topic head drew the four-point commend star (ADR 0030 had already called
  it "one esteem glyph in the system"). The queue drew Lucide's five-point
  `star` / `star-filled`. The board row printed a literal `★` character, which
  a screen reader announces as "black star" and which the design system bans
  from chrome.
- Topic facts disagreed across the two rows. The tag list said **Decision Made**
  where the queue and the board said **Decision**. It printed last activity as
  an absolute instant where both others print elapsed time on a `<time>`. It
  printed a snooze to the minute where both others print the day. The queue
  dropped **Archived** entirely.

The before-capture for this work also found a live defect the second row had
hidden. The generic compact-density rules belong to the default list, but they
reached the board row too. They turned its copy column into a flex row, and in
compact density a long meta line squeezed a long title into a column of single
clipped words ("A… a de… aft…"). `DESIGN.md` says a board title wraps and the
row grows, never a crop.

## Decisions

### 1. One row partial with a presentation axis

`templates/partials/thread_row.php` renders every topic row: `default` (a list
inside a page — tags), `board` (the canonical index) and `inbox` (the personal
queue). `inbox_thread_row.php` is deleted. The queue's selection box, star
toggle and row menu are slots on the row, not a second component. The partial
documents its parameters in its header.

Topic facts are computed once. The status word comes from one map (the
`DESIGN.md` ledger: Solved, Needs answer, **Decision**, Archived). Last activity
is elapsed time inside `<time datetime title>` in all three presentations. A
snooze is a date in all three.

### 2. The queue speaks the row's vocabulary, and keeps ADR 0029's geometry

The queue presentation is `.thread-row.thread-row-inbox`, with
`.thread-title-line`, `.thread-title`, `.thread-row-chips`, `.thread-snippet`,
`.thread-meta`, `.thread-meta-commends`, `.thread-row-select`,
`.thread-row-star`, `.thread-row-menu` and `.thread-row-menu-panel`. Every value
ADR 0029 measured is unchanged. Because the row now carries `.thread-row`, the
queue restates what the generic row and its compact register would otherwise
paint on it: the status `::before` rule, the `overflow: hidden` that would cut
the row menu when JavaScript is off, the flex copy column, and the one-line
clipped title. The root selector carries two classes so these hold without
`!important`.

The rules sit in the 2026-08-27 production-transfer block, identical in
`public/assets/app.css` and `docs/design-system/imladris/components.css`
(`AppImladrisFidelityTest` pins the two), and the generated `imladris.css` was
rebuilt from the mirror (see `LOCAL_RECONCILIATION.md`, 2026-09-23). `app.js`
now finds a row's title by its `data-inbox-preview-url` hook rather than a class,
and reads the shared `thread-unread` state.

### 3. One star: one control in two sizes, one glyph

`templates/partials/star_toggle.php` is the star — a personal bookmark — as a
plain form post in two sizes: the labelled pill in a topic's head, and a 28px
icon toggle in a queue row. The toggle's accessible name carries the topic's
title, because a list holds many of them, and its state is `aria-pressed`.

The glyph is the commend star everywhere. The toggle draws it **in outline
until the topic is starred**, and filled once it is. `--text-faint` and
`--gold-ink` measure within a hair of the same lightness, so ink alone would
not carry the state for a reader who cannot tell the hues apart. The board
index's marker is the same glyph in `--star` with `role="img"` and the name
"Starred". That is the card's rule: loud in the queue, quiet on the index. The
board favourite on `/settings/boards` uses the same outline/filled grammar.

**The word stays "Star".** The critique suggested "Commend" for the label, and
that was wrong. The star is a personal bookmark and commend is the reaction.
The lexicon turns *like* into *commend*, not *star*. Renaming the control would
merge two features.

### 4. ★ and ☆ are retired

No template prints either character. `AppTopicRowConsolidationTest` scans
`templates/` and holds the count at zero, as `AppImladrisFidelityTest` does for
the chamfer's `clip-path`.

### 5. Presentations are insulated from density

The board's copy column stacks at every density. Its title never clips, and its
meta wraps as it does in comfortable density. This fixes the compact crop
above.

## Kept

- `.star-btn` stays a pill. It is one of ADR 0034's open exceptions and this
  work does not rule on it.
- The queue's title keeps one weight when unread, as `ForumInbox.dc.html` draws
  it, rather than taking the default list's semibold.

## Deferred

Recorded so they are not lost. None is fixed here.

1. **The tag list carries no viewer state.** Its query selects no star, unread
   or snooze for the viewer, so a starred topic reads as unstarred on a tag
   page. The card says viewer facts render on every surface; this is a
   repository change, not a template one.
2. **Two treatments of one citation.** The default list sets its board
   reference in gold ink with an underline, while the queue spends
   `--artifact-link` on it (ADR 0029). They are the same fact.
3. **The design sources still draw ★** in five places (listed in
   `LOCAL_RECONCILIATION.md`, 2026-09-23). They sit inside the design digest or
   under `RETIRED.md`, so they are left alone, and a bundle that offers one back
   should have that hunk refused.
4. **The card names the queue presentation `default`.** Production names it
   `inbox` because the shipped queue row follows `ForumInbox.dc.html`'s triage
   geometry. It is a naming difference to settle upstream.

## Verification

- **Computed-style parity.** A role-by-role fingerprint of every queue-row
  element was taken before and after across eight registers: 1100px parchment
  and twilight in both densities, 1440px, 700px, and 390px in both densities.
  It also covered the Starred scope, the Commended order, hover, the open row
  menu, and the keyboard cursor with an open preview. Titles, chips, meta,
  snippet, selection box, unread slot, monogram, row menu and panel geometry are
  identical. The only moved values are the star glyph (the commend star at
  18px, outlined when off, in the same 28px button with the same centre) and
  computed values on the unrendered `::before` and on non-flex children.
- **Browser.** `forum-inbox-remediation.spec.ts` (ADR 0029's twelve measured
  pins) passes on the converged row. `topic-row-consolidation.spec.ts` covers
  the glyph states, a star round trip, the board marker, the compact board
  title, the default list's `<time>`, the star-character sweep and the phone
  touch target. Screenshots are in `docs/evidence/topic-row-consolidation/`.
- **PHPUnit.** `AppTopicRowConsolidationTest` (6 tests) plus the updated
  `AppInboxRemediationTest` and `AppInboxMemberSurfaceTest`.
