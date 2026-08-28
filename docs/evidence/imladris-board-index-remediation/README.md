# Imladris board index — remediation evidence

Captured **2026-08-27** on `main` (baseline `7c66e2fd`) against the real PHP
application, served by `php -S` on `127.0.0.1:8021` over a private
`retroboards_e2e_bi` database, migrated from scratch and seeded by
`tests/browser/seed.php`, plus four `follows` rows so the Connections people
list has rows to render rather than only its empty state:

```sql
INSERT IGNORE INTO follows (user_id, target_type, target_id, created_at)
SELECT f.id, 'user', t.id, UTC_TIMESTAMP() FROM users f JOIN users t
 WHERE f.username IN ('alice','bob') AND t.username = 'admin';
```

**The design:** `templates/board-index/BoardIndex.dc.html` in the Claude Design
project `c3e02753-607c-40b6-994c-9ba1a65bb367`, read through the `DesignSync`
MCP tool. **The decisions, the audit that produced them, and every deferral:**
`docs/adr/0028-imladris-board-index-remediation.md`. **The capture itself:**
`tests/browser/board-index-remediation.spec.ts` — every screenshot here is
written by an assertion, not by hand, so a regression fails the spec before it
reaches the folder.

## Why these captures exist at this size

The transfer's own desktop capture is **924×540**, and so are the approved
reference PNGs it was compared against. Both P0 defects lived below 540px: the
board rows begin at roughly y=560 on a 1280px-wide render. The comparison method
was structurally blind to the surface's primary list. Every desktop frame here is
**1280×1400**, tall enough to show at least three complete board rows with their
peek lists.

## Screens

| File | What it shows |
|---|---|
| `01-directory-desktop-light.png` | 1280×1400, guest, `?sort=active&peek=3`. **The P0 fix.** Each board's peek list sits *beneath* its row, indented, sharing the row's left edge — the superseded `.board-index .forum-directory__board` rule had made the `<article>` a two-column grid and put the whole peek list in column 2. Also shows the centred reading column, the description sitting beside its board name, the Viewing bar as one row of pills (nine `<form>`s laid out inline), the `Active` order selected, `Peek 3` selected in the segmented control, the mono totals strip, the order note, and the guest note. |
| `02-directory-desktop-twilight.png` | The same directory with `data-theme="dark"`, ordered by `Top`. Confirms the signal, the peek bullet, the order note and the tab dot all survive the register flip — the dot now paints `--gold-ink`, which twilight remaps, rather than the `--gold-500` primitive that never does. |
| `03-directory-compact-keeps-peek.png` | `data-density="compact"`. The board **description** is gone — compact is the triage register — and the **peek list is still there**, tightened. Before the fix the same rule hid the peek too, which silently overrode the reader's own Peek choice while the Viewing bar still showed it as On. |
| `04-pane-tags.png` | The Tags pane. Pills with a name and a topic count. |
| `05-pane-notices.png` | The Notices pane. The heading and its two actions share a baseline row; the notice names its topic in quoted italics (`Alice Avery replied to “Welcome to RetroBoards”`), carries a gold unread mark with an `sr-only` "Unread." beside it, and a right-aligned mono relative time over a hairline. The Notices tab carries its unread dot. |
| `06-pane-connections.png` | The Connections pane. Followers/Following tabs with counts and a visible current-tab rule, then the people list: monogram · display-face name · mono `@username · N regard` · a right-aligned Remove, ruled between rows. Remove is offered on Followers only, as the design specifies. |
| `07-phone-viewing-sheet-open.png` | 390×900. The phone Viewing sheet open over its scrim, with its `<summary>` lifted above the scrim. |
| `08-phone-viewing-sheet-closed.png` | The same sheet closed again by tapping that summary — the path that did not exist before. |
| `09-phone-search-reachable.png` | 390×900. `/search`, reached from the topbar's icon-only search entry. |

Frames 04–06 render in twilight because the preceding test leaves the admin's
theme set to dark. That is left as captured on purpose: it is the only evidence
that the eleven newly-written pane classes flip with the register, which is what
the audit found the transfer had never checked for any of them.

## Measured against the design's own rendering

The design was also rendered directly — `BoardIndex.html`, the offline export of
the same artboard — and both pages measured in the browser at 1440×1200. Three
numbers drove the layout corrections below:

| | design | production, before | production, after |
|---|---|---|---|
| `h1` left edge | 469 | 361 | **469** |
| reading column, gap left / right of its pane | centred | −24 / 192 | **centred** |
| board name → description gap | 15px | ~90px | **16px** |
| board row anchor | `flex` | `grid 218/397/121` | **`flex`** |

The board-name gap is the one that read worst: a `minmax(150px, .55fr)` grid
track padded every short board name out to 218px, so "Announcements" and "News
and updates from the team." looked like two columns rather than one phrase.

## What the captures assert, not merely show

`tests/browser/board-index-remediation.spec.ts` measures rather than eyeballs:

- **Geometry.** The peek list's bounding box must start at or below the row's
  bottom edge and share its left edge within 40px, and the board `<article>`'s
  computed `grid-template-columns` must not resolve to more than one track. This
  is the assertion the P0 would have failed.
- **Presentation exists.** Each pane's list must compute `list-style-type: none`
  and an inline start padding under 40px. An unstyled `<ul>` keeps the UA's disc
  marker and 40px indent, so this fails precisely when the CSS is missing —
  the check that would have caught the second P0 at merge time.
- **The sheet can be closed.** `document.elementFromPoint()` at the centre of the
  open sheet's summary must resolve to that summary. It resolved to the scrim
  before the fix, which is what made the sheet a trap.
- **The column is centred.** Its left and right gaps within its pane must agree
  to within 2px, and both must be positive.
- **The description is beside its name.** The gap between the name's right edge
  and the description's left edge must be 0–24px, and the facts group must end
  flush with the row's right edge.

## Automated results

| Suite | Command | Result |
|---|---|---|
| Board index + notices + navigation + Imladris fidelity | `DB_TEST_DATABASE=retroboards_test_bi vendor/bin/phpunit --filter "ForumIndex\|Notification\|NavigationService\|Imladris"` | **OK — 110 tests, 1039 assertions** |
| Remediation pins | `DB_TEST_DATABASE=retroboards_test_bi vendor/bin/phpunit tests/Integration/Core/AppForumIndexRemediationTest.php` | **OK — 11 tests, 86 assertions** |
| Imladris runtime assets | `php bin/build-imladris-assets.php --check` | **Imladris runtime assets are current.** |
| Browser evidence | `RB_BASE_URL=http://127.0.0.1:8021 npx playwright test board-index-remediation.spec.ts --project=desktop` | **6 passed** |

## One negative check worth recording

The anonymity fix was verified by reintroducing the defect. With
`directorySignals`'s OP join filtering on `is_deleted = 0 AND is_pending = 0`,
`AppForumIndexRemediationTest::test_a_peek_row_masks_an_anonymous_author_even_when_the_op_is_soft_deleted`
fails with the real author's name rendered into the peek row
(`<span>Remediation Author · just now</span>`) for a topic posted anonymously
whose opening post a moderator had soft-deleted. The join was then restored and
the test passes. A test that passes against the broken code proves nothing; this
one does not.
