# ADR 0029: Imladris forum inbox — fidelity remediation and deferrals

**Date:** 2026-08-28
**Status:** Accepted and implemented on `main` (baseline `e818fb0d`).
**Relates to:** `templates/forum-inbox/ForumInbox.dc.html` in the Claude Design
project `c3e02753-607c-40b6-994c-9ba1a65bb367` — confirmed byte-identical to the
repository mirror at `docs/design-system/imladris/templates/forum-inbox/` before
this work began; commit `bdd27482` and the merge `2b272e5c`, which transferred
the surface; ADR 0028 (the board-index remediation, whose method this follows);
CLAUDE.md's rule that deferrals and reversals are recorded in an ADR and never
silently dropped.

## Context

The member-surfaces transfer implemented `/inbox` in commit `bdd27482`. The
transfer is faithful in the places a source comparison can reach: all twelve
scope keys and labels, the three order keys with both their short and full
labels, the three menu groups and their membership, the eight For You reason
strings, the empty-state copy, the keyboard hint, the sweep verbs and the quiet
state's two paragraphs are verbatim from the design; `InboxView` reproduces the
design's `SCOPES`/`ORDERS`/`MENU_GROUPS` exactly, and
`ThreadUserRepository::filterFragment` reproduces its `matches()`.

What a source comparison cannot reach is what the surface looks like. Rendering
the design's own compiled runtime beside production at 1440×1200, against the
design's own dataset, and measuring the same elements in both is what produced
the list below. Numbers are in
`docs/evidence/imladris-forum-inbox-remediation/README.md`.

Two findings are the same class as ADR 0028's second P0 — **a class the template
emits with no rules behind it** — and one is the same class as its first: **a
superseded rule left upstream of its own replacement.**

## Decisions

### Fixed

| # | Change | Why |
|---|---|---|
| 1 | `.chip-reason` is written | The class had **no CSS at all**. The inclusion cue — the row's answer to "why am I being shown this?" — fell through to the base `.chip` and rendered as an evergreen *status* pill shouting `REPLIES TO YOUR TOPIC` in caps. The design sets it in gold, in sentence case, a shade larger and looser than the uppercase status pills beside it (`ForumInbox.dc.html:176`). Every For You row carries one, so this was the most-repeated element on the surface. |
| 2 | `.inbox-empty-state` is written | The queue's own empty state — every scope that has nothing in it — had **no CSS at all**. Under strict CSP an unstyled `div` is a star, a heading and a paragraph stacked against the left gutter. It is now the design's centred, composed state (`ForumInbox.dc.html:234`). |
| 3 | The superseded pre-Imladris inbox block is removed from `app.css` | It sat 13,000 lines upstream of the block that replaced it. Most of it was inert, but `.inbox-empty-star { width: 56px; height: 56px }` was not: the replacement re-coloured the mark and never resized it, so the reading pane's quiet mark printed at nearly twice the size the design asks for. Rules a rewrite leaves upstream of their replacements are how a rewritten surface silently keeps a slice of the surface it replaced. What is still live — the full-bleed shell, the pill tabs `/feed` and `/leaderboard` still wear, the injected thread view's measure — stays, with a pointer to where the rest went. |
| 4 | Both empty-state marks are inline SVG, not `<img>` | `.inbox-empty-star { color: var(--gold-ink) }` was inert: an `<img>` cannot take `currentColor`, so the mark rendered in the SVG file's own stroke. The design uses the quiet four-point star, filled, at 30px — already in the icon map as `eight-point-star`. |
| 5 | The reading pane states who opened the topic | The design's pane names the author, their standing and the size of the conversation on one ruled line before it prints a word of the post (`ForumInbox.dc.html:268-276`). The transfer had no byline at all: a topic could be read here without ever learning whose it was. |
| 6 | The opening post is the topic's lede, not the first of its replies | `ForumInbox.dc.html:277-288`. It is identified by `is_op`, not by position: an opening post a moderator has soft-deleted is absent from the page entirely, and the first row would otherwise be somebody else's reply wearing the topic's byline. An anonymous opening post is masked, and states no rank either — a standing beside a masked name narrows the field the mask exists to widen. |
| 7 | Commends print in the Commended order and nowhere else | `showCommends: s.order === 'commended'` (`ForumInbox.dc.html:190`). Printed in every order they were a fourth statistic competing for the meta line, which then wrapped to two — turning a compact triage row into a three-line one. |
| 8 | The board reference is `--artifact-link` | A board reference in a topic's meta is a citation of the record, and the design gives it Bruinen where every other link wears evergreen. It is the one place the token is spent, and both `ForumInbox.dc.html:187/268` and `Search.dc.html:187` spend it there. |
| 9 | `--artifact-link` climbs in the twilight register | `river-500` is **3.08:1** on the twilight page and 2.74:1 on a raised surface, against 4.5:1 for the `.7rem` text that carries it. The dark block already promotes `--info` to `river-400` and `--on-info` to `river-200` for exactly this reason; `--artifact-link` now follows to `river-200` (10.8:1 / 9.6:1). Adopting a token without checking it in both registers is how ADR 0027 and ADR 0028 each found a sub-AA cue. |
| 10 | The unread pill is sentence case | `.badge` is the product's uppercase micro-label; here it states a count in words. The design sets `6 unread` beside the h1 in sentence case (`ForumInbox.dc.html:90`). Shouting it made the pill the loudest thing on a queue whose whole job is ranking what matters. |
| 11 | The Viewing bar carries one rule, beneath it | `ForumInbox.dc.html:101`. A second hairline above turned the header into a boxed band; the lede above it is already separated by its own whitespace. |
| 12 | The density statement names the register the reader has | It read "Rows follow your appearance preference · change". The design says `Compact rows` / `change` (`ForumInbox.dc.html:135-138`) — and the board index, remediated one commit earlier against the same statement in its own design, already says exactly that. Two surfaces sharing one CSS class were saying different things. It also stops forcing its own line: the design keeps it inline and lets flex wrapping decide. |
| 13 | The scope control leads the bar | Scope and order are two axes, not two peers: scope decides *which* topics and wears the larger outlined control, order decides the sequence and wears the smaller pills. Raising the scope button onto `--surface-raised` at the pills' own `.78rem` flattened the distinction the two-axis model exists to make. |
| 14 | One hairline separates two rows | `.inbox-thread-list` was a grid with `gap: 1px` **on top of** each row's own bottom hairline, drawing a second, wider channel the design does not have. |
| 15 | The filled brand stars inside chips are filled | `.chip svg { fill: none; stroke: currentColor; stroke-width: 2 }` outranks the presentation attributes the brand stars carry (CSS beats presentation attributes), so the commend star inside the inclusion cue was drawn hollow at a stroke width of 2 in a 0–100 viewBox — sub-pixel, and the wrong shape. |
| 16 | The unread dot survives on a phone | `@media (max-width: 520px)` hid `.unread-slot` to buy 17px. `.inbox-thread-row.is-unread` styles nothing, so the dot is the row's **only** unread cue: below 520px an unread-triage surface had no way to show what was unread. |
| 17 | Measures and metrics follow the design | Reading column `760px` not `840px`; lede `68ch` / `1.55`; select-all row 22px and indented by the row's own 12px instead of a 38px band; row heading on the baseline at `2px 9px`; row title `letter-spacing: -0.005em`; meta gap `10px`; key hint `.68rem`; shown-count `.74rem` at 22px; Mark all read transparent at `.76rem`. |

### Kept against the design

| # | Production keeps | Why |
|---|---|---|
| 1 | The `#` prefix on a board reference | Eleven templates carry `<span class="hash">#</span>` — the board page, the thread breadcrumb, the rail, thread rows, search, admin. The inbox and search designs omit it; `UserProfile.dc.html` keeps it. One template's omission does not outweigh the product's own convention, and ADR 0028 already left the rail's prefix in place. The **colour** is adopted; the prefix stays. |
| 2 | Order pills with a 30px floor, where the design's are 25px | The floor is a shared rule across the board index, search and the inbox, added for touch. Changing it here would break the one visual grammar those three surfaces deliberately share. |
| 3 | `.chip { line-height: 1 }`, where the design's chips are 19px tall | The design writes its chips inline and never sets a line-height, so they inherit the body's `1.6` — a body measure leaking into a lapidary pill. `line-height: 1` is the system's deliberate pill metric and every other chip in the product uses it. |
| 4 | Absolute timestamps on the reading pane's post bylines | The kicker moves to relative time, because it is the same datum the row that opened the pane prints one line away, and the two disagreeing was the actual defect. Post bylines keep `post_datetime`, which is the record's register in the thread view they hand off to. |
| 5 | Pagination, where the design has "Load N more" | Addressable, linkable, and works without JavaScript. The board index made the same call. |
| 6 | A 2px transparent left border on every row, where the design overlays an absolutely-positioned active state | Production's reserves the space so selecting a row does not reflow its text. The design's approach is one frame prettier and one reflow worse. |
| 7 | No pre-selected topic on first load | The design's mock opens with `selectedId: 't1'`. Production's reading pane is a progressive enhancement — the design's own quiet state says "Without JavaScript, topics open as their own page" — so a server-rendered first paint has nothing selected by construction. |
| 8 | Snoozed topics stay out of every scope but Snoozed | The design's mock shows a snoozed topic in For You with its "snoozed until" note. Deferring a topic and still being shown it is not deferral; `normalInboxVisibility` is right and the mock is a mock. |

### Deferred

Recorded so they are not lost. None is fixed here.

1. **The design's compact register never renders.** `[data-density="compact"]`
   in `ForumInbox.dc.html:22-25` states four rules and every one of them is
   outranked by the inline `style` attribute on the row it targets, so the
   design's own compact view shows comfortable spacing. Production applies what
   the rules say and pins each value in a test. The design template should be
   fixed upstream; that is a change to the design project, not to production.
2. **`tests/browser/imladris-forum-surfaces.spec.ts` still asserts the retired
   inbox.** It looks for `.inbox-tabs a.is-active`, `[data-inbox-list] a.thread-title`
   and `[data-inbox-list] .thread-row.is-active` — the pre-transfer markup. The
   spec predates `bdd27482` and was not updated with it.
3. **`tests/browser/member-surfaces.spec.ts` captures the inbox at 924px.** That
   is the width whose blindness this ADR is about; the capture should be re-taken
   at a height and a dataset that can disagree with the design.
4. **The reading pane's composer placeholder is the shared one.** The design's is
   `Answer the strongest reading of the post…`; production's is
   `Reply to "<title>"…` from `partials/composer`. Changing it means giving the
   partial a per-surface placeholder, which touches the thread view too.
5. **The reply count in the pane is derived from the loaded page's total**
   (`$total_posts - 1`) rather than from `threads.reply_count`. Both filter
   `is_deleted = 0 AND is_pending = 0`, so they agree on real data, but they are
   two paths to one number.
6. **The app shell is `max-width: 1280px` and centred; the design is
   full-bleed** — except on this route, where `body[data-route="inbox"]
   .app-shell { max-width: none }` already opts out. The inconsistency is ADR
   0028's deferral #10 seen from the other side: the inbox is the surface that
   matches the design and the others are not.
7. **The row's overflow menu offers no Assign.** The design's carries
   `Assign…`/`Unassign` beside Snooze; production's has read/unread and the four
   snooze windows only, so assignment is reachable from the thread page alone
   even though the queue filters and labels by it.

## A note on method

ADR 0028 records that source parity is not visual parity. This surface adds the
other half: **a dataset that cannot disagree with the design is not evidence
either.** The transfer's own capture showed a queue holding one topic. A queue
with one row shows no chip that is not that row's, no order the reader did not
pick, no empty state, and no second row to measure a separator against — which
is precisely where findings 1, 2, 7 and 14 were hiding.

`tests/browser/forum-inbox-fixture.php` therefore reproduces the design's own
sixteen topics across its eight boards, with every personal signal represented
and one topic opened anonymously, and the spec asserts against that.

## Consequences

The inbox's most-repeated element renders in the register the design gives it;
its empty scopes render at all; its reading pane says whose topic it is, and
masks that when it must. One sub-AA colour is corrected in the twilight register
before it could ship, and one unread cue is restored on phones.

`tests/browser/forum-inbox-remediation.spec.ts` pins each fix with a measurement
rather than a screenshot — the inclusion cue's `text-transform`, the board
reference's computed contrast against its own pane in both registers, the quiet
state's mark size and centring, the empty scope's `text-align` and padding, the
gap between two rows, and each of the four compact-density values.
`tests/Integration/Core/AppInboxRemediationTest.php` pins the same set from the
server side, including the two stylesheet gates.

Both were confirmed against the pre-fix tree: all **7** integration tests and
**10 of the 12** browser tests fail on it. The two that pass there are the pins
rather than the fixes — the compact-density register, which production already
implemented correctly, and one half of the row-separator check.

Verified: `tests/Integration` 2098 tests, 12775 assertions, 1 skipped, OK.
`tests/Unit` 636 tests, 7366 assertions, OK. `php bin/build-imladris-assets.php
--check` current. Playwright `forum-inbox-remediation` 12 passed.
