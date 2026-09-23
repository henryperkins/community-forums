# One topic row, one star — browser evidence

Captures for ADR 0036: the inbox now renders the one topic row
(`templates/partials/thread_row.php`, `presentation => 'inbox'`), and the star is
one control with one glyph (`templates/partials/star_toggle.php`, the commend
star).

## Reproducing

```bash
DB_DATABASE=retroboards_e2e bash tests/browser/prepare.sh
cd tests/browser
npx playwright test topic-row-consolidation.spec.ts forum-inbox-remediation.spec.ts
```

The spec seeds the design's own sixteen-topic dataset
(`tests/browser/forum-inbox-fixture.php`) and signs in as `erestor@retro.test` /
`password123`. Every `desktop-*.png` and `mobile-*.png` here is written by an
assertion in `topic-row-consolidation.spec.ts`.

## Parity: the queue row did not move

The queue row changed vocabulary (`.inbox-row-*` became the thread row's
classes under `.thread-row-inbox`), so the check was a role-by-role
computed-style fingerprint, not a screenshot diff. The same elements were found
by role in both markups: row, selection cell and box, unread slot and dot,
monogram, copy column, title line, title, chips and each chip, snippet, meta and
each meta item, board link, star cell, star button, glyph, row menu, summary and
panel. About seventy properties and each element's box relative to its row were
compared, before and after, from the same database snapshot:

- 1100px parchment and twilight, comfortable and compact
- 1440px (split pane), 700px, and 390px comfortable and compact
- the Starred scope and the Commended order
- hover on a row, the open row menu, and the keyboard cursor with an open preview

Titles, chips, meta, snippet, selection, unread slot, monogram, row menu and
panel geometry are **identical** across all of them. The values that moved:

| what | before → after | why |
|---|---|---|
| star glyph | Lucide `star` / `star-filled`, 16px → commend star, 18px | the one esteem glyph; the four-point star fills less of its box, so it takes 2px more to read at the same size. The 28px button and the glyph's centre did not move |
| unset star | stroked five-point star → the commend star in outline (`is-outline`) | on and off must differ in shape, not only ink |
| `::before` computed values | — | the pseudo-element is `content: none` and never renders |
| `order` / `gap` on non-flex children in compact | — | computed only; no box moved |

`forum-inbox-remediation.spec.ts`, ADR 0029's twelve measured pins, passes on
the converged row.

## Captures

| file | shows |
|---|---|
| `10-board-compact-before-after.png` | the board in compact density: before, "Amending a decision after it has been cited" collapsed into a column of single clipped words; after, it wraps and its meta sits beneath, as in comfortable |
| `11-default-list-before-after.png` | the tag list: "Decision Made" → "Decision", and the absolute instant → elapsed time on a `<time>` |
| `12-queue-before-after.png` | the queue at 1100px: only the star column changes |
| `13-topic-head-before-after.png` | the topic head's labelled star, now rendered by the shared partial: identical |
| `14-star-states-3x-parchment.png`, `15-star-states-3x-twilight.png` | the queue toggle at 3×: outline when unset, filled gold when starred, in both registers |
| `16-board-favourites-2x.png` | `/settings/boards`: the favourite is the same glyph in outline, where it printed ☆ |
| `desktop-queue-stars.png`, `mobile-queue-stars.png` | the queue with both toggle states; on a phone the toggle keeps a 34px target |
| `desktop-board-marker.png` | the board's quiet "Starred" marker, the same glyph |
| `desktop-board-compact.png`, `mobile-board-compact.png` | the compact board, long title wrapped |
| `desktop-default-list.png` | the tag list's row with elapsed time |
