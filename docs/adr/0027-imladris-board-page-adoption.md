# ADR 0027: Imladris board page — adoption, conflicts, and new write surface

**Date:** 2026-08-27
**Status:** Accepted and implemented on branch `feat/imladris-board-page`
(baseline `c98d0d0d`).
**Relates to:** `templates/board-page/BoardPage.dc.html` in the Claude Design
project `c3e02753-607c-40b6-994c-9ba1a65bb367` (the design this branch adopts);
ADR 0024 (the admin/account adoption that set the pattern for reading a design
project into production); `docs/superpowers/plans/2026-08-02-imladris-forum-surfaces-production.md`
and `docs/superpowers/specs/2026-08-03-board-topic-density-remediation-design.md`
(the board decisions this ADR amends); CLAUDE.md's rule that deferrals and
reversals are recorded in an ADR and never silently dropped.

## Context

The Imladris design project gained a `board-page` template — a full
specification for `/c/{slug}`. A structural comparison against production found
**77 differences**, of which 15 collided with a decision already recorded in
this repo. Several of those recorded decisions turned out to exist only in a
commit message (`3f5f0472`), in source comments, and in test assertions —
`DECISIONS.md`, the twenty-six prior ADRs, and the root `CHANGELOG.md` were all
silent on them. That thinness is itself why some were easy to reopen, so this
ADR writes the resolutions down.

## Decisions

### Adopted from the design

| # | Change | Why |
|---|---|---|
| 1 | The band's eyebrow is the board's **category name**, not the constant word "Board" | The category is strictly more informative and costs one `LEFT JOIN` in `BoardRepository::findBySlug`. Amends `plans/2026-08-02-…-production.md:395`. |
| 2 | The facts become a `<dl>` register — `Topics / 24`, `Posts / 1,204`, `Access / Public` — right-aligned above the actions | Each value names itself. The old interpunct line made "24 topics · 1,204 posts · Public board" one run-on string in which a number could be read against the wrong noun. The `data-board-fact` hooks and the conditional archive cell survive. |
| 3 | Status becomes a **pill in a reserved column after the title**, not a coloured word on the meta line | The recorded prohibition (`thread_row.php:25-26`) is against a chip **stacked above** the title, "so the title stays the first thing read". A column *after* the title honours that intent better than the meta line does, and satisfies the Word-and-Colour rule (`DESIGN.md:237`). The column is emitted whether or not the topic has a status, so the activity column never shifts between rows. |
| 4 | The row's 3px status left-rule is **dropped** on this presentation | It existed because status had nowhere else to live. With a pill column it would state status twice. Do not ship both. |
| 5 | Pinned and Locked become bare **marks on the title line**, not pills | They are not status — they qualify the title. They are siblings of the `<a>`, never children, so the title alone remains the link's accessible name. |
| 6 | The star moves to a **reserved 18px column** | A fixed cell guarantees rows never shift; the inline star could not. Worth doing only together with #9, since the star never rendered at all before. |
| 7 | The reply count is **visible text with its noun** (`0 replies`), replacing the `aria-hidden` glyph plus an `sr-only` twin | One source, not two saying the same thing. The em-dash-at-zero affordance is given up deliberately. |
| 8 | The topics header becomes a **sticky column ruler** carrying the row's own track list | Its labels now rule the columns beneath them. It sticks *below* the condensed masthead (`--topbar-h + --board-condensed-h`), never at the same offset — the bar is `z-index: 6` and would otherwise cover it. |
| 9 | Board rows carry the viewer's own state — **starred, assigned, snoozed** | `thread_row.php` already rendered all three; `listBoardRows` never selected them, so they were dead on every board page. "A topic you starred must read as starred on its own board." |
| 10 | Full empty state: the eight-point mark, the headline, and a first-topic invitation gated on `can_post` | |
| 11 | Two-move pagination with `Showing N of M topics`; the numbered strip stays for every other caller behind a `variant` flag | An unavailable move renders as a `<span aria-disabled>`, not a dead anchor — it is then neither focusable nor announced as a link. |

### Kept against the design

| # | Production keeps | Why |
|---|---|---|
| 12 | The band's three approved colours as **hex literals** (`#2E4A3A` / `#FAF6EC` / `#C29A44`) | Playwright asserts them, and the green/gold/parchment primitives have no twilight flip. Secondary band colours *are* tokens, for the same reason inverted: they do not flip either, so the slab reads identically in both registers. |
| 13 | The **condensed sticky masthead** | The design simply has no scrolled state; it is not a decision against one. The sticky topics header is complementary. |
| 14 | The **mobile FAB** | `PRODUCT_DESIGN.md:165`. It is also the keyboard escape hatch that justifies the condensed bar's `aria-hidden`. |
| 15 | The **"Latest activity" eyebrow** | String-asserted and specified at `specs/2026-08-02-…-production-design.md:120`. |
| 16 | The follow note as a visible `<p>`, with the design's sentence *also* on the button's `title` | A `title` alone is invisible to touch and to keyboard users. |
| 17 | The new-topic composer as a **modal under JS** | `app.js:811-819` (handoff §5.2), and 18 Playwright call sites resolve its triggers. The panel takes the design's chassis — the gold rule and the `New topic in #{board}` eyebrow — without changing what the element is. The eyebrow renders inside the `<form>`, since only `> .composer` is lifted into the modal. |
| 18 | `.board-topics { margin-top: 22px }` (design says 20px) | A 2px difference is not worth reopening a numeric acceptance contract with measured evidence behind it. |
| 19 | Follow gated on `community` **and** `expanded_feeds` | `specs/2026-08-02-…-production-design.md:100-103`. The design shows it for any signed-in member. |
| 20 | The New topic button server-rendered `hidden` | Without JS it would toggle a `<details>` the `<summary>` already toggles. |

### New write surface

The design's gutter marker and "Mark all read" had no backend. Both now exist,
gated by the existing `engagement` flag and dark without it:

- `POST /t/{id}/read` with an explicit `state=read|unread`. **Explicit, not a
  toggle**, so a no-JS double-submit lands on the state the member clicked
  rather than flipping past it.
- `POST /c/{slug}/read` — bulk, inside a transaction, board-scoped.
- `ThreadUserRepository::markUnread()` is a **new** write, not a reuse:
  `markRead()` is monotonic by construction (`GREATEST`) and silently no-ops
  when asked for a lower watermark. It clears `last_read_post_id` and INSERTs
  the row when absent — deleting the row would fall the thread back to
  `engagement_cutover_at`, which on a default install reads as *read* and would
  invert the action. `AppBoardReadStateTest` regression-tests exactly this.
- `unreadCountForBoard()` deliberately does **not** reuse `unreadCount()`'s
  predicate: it counts exactly the rows `unreadFlags()` puts a dot on, so the
  header's number always equals the dots below it.
- Neither route is behind `WriteGate`, unlike `star()`. Opening a topic already
  advances this same watermark for a suspended member, and suspension means
  "read but not write" — refusing the manual marker while the automatic one
  still fires would contradict itself.

### Caught by the branch's own review, before merge

A five-dimension adversarial review of the diff found five defects, four of
which only appear in a state a screenshot does not show. Recorded because each
is a rule this codebase already had and this branch nearly broke:

1. **Retained star survived an `engagement` rollback.** `is_starred` was
   selected for any signed-in viewer. `listBoardRows` was already scrubbing the
   `topic_workflow` columns on rollback, with a comment stating the governing
   rule — the new engagement column was simply not covered by it. Each flag now
   nulls the columns it owns.
2. **The composer eyebrow rendered behind the scrim.** Only
   `.composer-details[open] > .composer` is lifted into the modal, so an
   eyebrow that was a *sibling* of the form stayed on the page. It belongs in
   the form's `wrapper_slot`.
3. **The empty state's CTA was dead under JS**, lacking
   `data-open-topic-composer` while app.js had already hidden the `<summary>`.
4. **A 2px band of scrolling rows** between the two sticky elements:
   `box-sizing: border-box` puts the condensed bar's rule inside its
   `min-height`, so the `+ 2px` allowance was one border too many.
5. **The sticky topics header was 18px narrower than the list it rules** at
   ≤680px, where the list goes full-bleed and the header did not.

### Incidental fixes

- **The four `.tier-*` pills were painted from numbered primitives, and three of
  them broke in twilight.** The numbered ramps are never remapped for the dark
  register, so each pill kept its day-register colours on a `#161D24` page.
  Measured before the fix, in twilight: **Loremaster 1.23:1** ink-on-chip — the
  pill effectively vanished, chip and page differing by 1.22:1; **Legend
  3.55:1**, below AA, on a chip blazing at 14.27:1 against the page; **Veteran**
  legible at 7.22:1 but a 13.73:1 pale-blue slab, the wrong register rather than
  an accessibility failure. `.tier-member` was already correct — it was the only
  one built from semantic tokens, and it is the model the other three now
  follow: `--on-staff`/`--surface-staff`, `--on-brand-subtle`/`--brand-subtle`,
  `--on-info`/`--surface-info`. All four now measure 5.5–10.1 in parchment and
  6.2–9.3 in twilight. The one visible day-register change is Legend's ink,
  gold-700 → gold-800: `--on-staff` exists precisely because gold-700 on
  gold-100 measured 3.55:1 and missed AA, a rationale already written down at
  `app.css`'s `.badge-staff`.
  **Note these classes are not rendered by any template today** — they are
  shipped design-system CSS with no consumer in this app, so the fix is verified
  by computed-style measurement against the real generated stylesheet in both
  registers, not by a screenshot.
  Edited in the builder's **source** (`docs/design-system/imladris/components.css`);
  `resources/imladris/components.css` and `public/assets/imladris.css` are
  regenerated outputs.


- **`.chip-decision_made` was invisible in twilight.** It painted
  `var(--green-800)` — a numbered green, which never flips — on the twilight
  brand wash. Now `var(--on-brand-subtle)`: identical in the day register,
  legible in both. Surfaced by the board row, where Decision is a pill.
- **FT-08**: `can_follow_board` now consults `WriteGate::canWrite`. A suspended
  member was being offered a control whose POST the write gate refuses —
  contradicting "state beats role".
- **`relative_datetime()`** (new helper). The activity column is read by
  comparing its rows, and `human_datetime()`'s ~24 unwrappable characters
  overflowed any column narrow enough to leave the title its measure — visibly,
  over the new status pill. The exact instant stays on the element's
  `datetime`/`title` attributes.
- The board column widens from 880px to **1080px**, scoped to `.board-view`.
  The row grew three right-hand tracks; the container grows by roughly their
  width so the *title* keeps the measure the density pass settled on.

## Deferrals

| Deferred | Why | What a follow-on needs |
|---|---|---|
| The design's `Posting to #{board}` composer footer line | The `New topic in #{board}` eyebrow above the panel already states the destination; a second line in an already-crowded action bar would repeat it. | If the eyebrow is ever dropped, restore the footer line in `composer_shell.php`'s `composer-meta-row`. |
| The design's **visible `Title` label** above the composer's title field | Attempted, then reverted. `.composer-header > .input` (`app.css:993`) is a **direct-child** selector that strips the field's border and matches the body's inset "so the composer reads as one surface instead of a detached input stacked above a framed component". A `<label>` wrapper makes the input a grandchild and silently gives the border back — caught by `composer-expansion.spec.ts:446`, which asserts `borderTopWidth === '0px'`. The design's placeholder copy was kept; `aria-label="Topic title"` carries the name. | A composer slice that lets the header slot carry a label without breaking the one-surface contract — probably a sibling `<span>` inside `.composer-header`, with the direct-child rule widened and the geometry assertions updated together. |
| The design's borderless, octagonal `clip-path` title field | Same shell, same reason: `composer-expansion.spec.ts` measures that input's geometry. | A composer slice that re-skins the field for every mount, with the geometry assertions updated together. |
| The design file is **not** mirrored into `docs/design-system/imladris/templates/board-page/` | That mirror is maintained by a whole-project sync with its own digest tripwire (`config/imladris-design-baseline.json`). Hand-copying one template would leave the mirror internally inconsistent and force a second baseline edit for a file nobody synced. | A `/design-sync` pass that brings `board-page` in with everything else the project has gained, and updates the design baseline in the same commit. |
| `imladris-forum-surfaces.spec.ts` › "forum index does not overflow across the 860px shell transition" fails | **Pre-existing.** Verified failing on the baseline with this branch's presentation changes stashed. It concerns `/`, not `/c/{slug}`, and the spec is not in `npm run evidence`, so CI never ran it. | A forum-index slice. `scrollWidth` is 785 against a `clientWidth` of 800 at that width. |
| `field-error-a11y.spec.ts` › ":user-invalid paints an engraved field before any round-trip" fails | **Pre-existing**, verified the same way. It asserts the `website` input on `/settings/account` changes `box-shadow` once `:user-invalid` matches — a surface this branch does not touch. | An account-settings slice: either the engraved `:user-invalid` rule is gone or its selector no longer matches. |
| `thread-view-study.spec.ts` › "Study layout matches desktop and mobile geometry" fails by **exactly 15px** | **Pre-existing**, verified the same way. It asserts the warden's-tools panel is flush to the viewport within 2px; 15px is a classic scrollbar width, the same signature as the forum-index failure above. Both are on surfaces this branch does not touch. Because `npm run evidence` chains its four groups with `&&`, this one failure aborts the whole capture, so the standing set in `docs/evidence/browser/` could not be refreshed through the npm script. Running the four groups without the abort showed why that barely matters: a re-capture rewrites nearly every file whether or not the page changed, so those PNGs were left at their committed state and only `docs/evidence/imladris-forum-surfaces-production/` — the spec that owns the board's visual contract — is updated here. | A shell slice covering both: decide whether these tests should account for a classic scrollbar (`scrollbar-gutter`), and unbreak the evidence chain either way. |

## A note for the merger

Per **ADR 0024 obligation 4**, `config/imladris-runtime-baseline.json` is
refreshed once per merge, on `main`, by the merger, as the immediately-following
commit — **no slice branch contains a change to it**, and this branch does not.
The consequence is that `ImladrisRuntimeAssetTest` is **expected to fail on this
branch**: it digests `templates/` and `public/assets/` (excluding the generated
`imladris.css`), and this branch moves both. The current application digest is
`45ccbdd02ef0e93e1a63cd238eac3b9b329507f9406632d16854b4b441541e57`.

One consequence worth knowing before the next design-system slice: because that
guard sits inside `ImladrisAssetBuilder::expectedFiles()` with no bypass, the
Imladris assets **cannot be rebuilt on a branch that has moved the application
surface** without first writing the new digest into the very file obligation 4
forbids a slice to change. The tier fix here needed a rebuild, so it was done by
writing the digest, building, and restoring the baseline — leaving only the
genuine build outputs. `resources/imladris/manifest.json` therefore carries both
the new `components.css` source hash (this branch's, correct) and the new
`surface_sha256` (the merger's value, which a post-merge rebuild will reproduce
identically).

## Consequences

`/c/{slug}` now states its board's category, its facts as a labelled register,
and each topic's status exactly once in a column that does not move. The
viewer's own state — unread, starred, assigned, snoozed — renders on the board
as well as in the inbox, and unread is now something a member can set, not only
something that happens to them.

The board is the only surface that takes the two-move pagination and the
`presentation="board"` row anatomy; `/inbox` and `/tags/{slug}` are untouched,
and `AppBoardIdentityDesignTest::test_tag_route_keeps_the_default_shared_thread_row_contract`
still guards that boundary.
