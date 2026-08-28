# Forum inbox remediation — browser evidence

Captures for ADR 0029: the Forum inbox (`/inbox`) measured against
`templates/forum-inbox/ForumInbox.dc.html` in the Claude Design project
`c3e02753-607c-40b6-994c-9ba1a65bb367`.

Every screenshot here is written by an assertion in
`tests/browser/forum-inbox-remediation.spec.ts`; none is taken merely beside one.

## Why the dataset matters

The transfer's own capture (`docs/evidence/member-surfaces/02-forum-inbox`, 924px
wide) showed a queue holding **one topic**. A queue with one row cannot disagree
with its design: it shows no chip that is not that row's, no order the reader did
not pick, no empty state, and no second row to measure a separator against. Three
of the defects below were invisible for exactly that reason.

`tests/browser/forum-inbox-fixture.php` reproduces the design's own dataset —
sixteen topics across its eight boards, plus one opened anonymously — so that
every signal the queue can express appears at least once: unread, mentioned,
replied-to, watched topic, watched board, followed board, followed tag, starred,
assigned, snoozed, pinned, locked, solved, needs answer, decision.

## Reproducing

```bash
DB_DATABASE=retroboards_e2e bash tests/browser/prepare.sh
DB_DATABASE=retroboards_e2e php tests/browser/forum-inbox-fixture.php
cd tests/browser
npx playwright test forum-inbox-remediation.spec.ts --project=desktop
```

Sign-in for the captures is `erestor@retro.test` / `password123`; the empty-scope
capture uses `bob@retro.test`, who has no mentions.

## The comparison harness

Fidelity was established by **rendering both and measuring the same elements**,
not by reading the design's source — the lesson ADR 0028 records. The design's
`.dc.html` was rendered offline from its own compiled runtime (React + the
`ImladrisDesignSystem_c3e027` component bundle + the token closure, all lifted
out of a design-project export) and production was rendered beside it at
1440×1200 with the same dataset and the same density.

Measured before → after, design → production:

| element | design | before | after |
|---|---|---|---|
| unread pill | `6 unread`, sentence case, 26px | `35 UNREAD`, uppercase, 20px | `35 unread`, sentence case, 26px |
| Viewing bar rules | bottom only | top **and** bottom | bottom only |
| scope control | `.84rem`, transparent | `.78rem`, `--surface-raised` | `.84rem`, transparent |
| density statement | `Compact rows change` | `Rows follow your appearance preference · change` | `Compact rows change` |
| inclusion chip | gold, sentence case, `.6rem` | evergreen, **uppercase**, `.56rem` | gold, sentence case, `.6rem` |
| board reference | `--artifact-link` (Bruinen) | `--accent` (evergreen) | `--artifact-link` |
| board reference, twilight | — | 3.08:1 | 10.8:1 |
| commends in the meta | Commended order only | every order | Commended order only |
| row meta lines | 1 | 2 (commends pushed it over) | 1 |
| row separation | one 1px hairline | hairline + 1px grid gap | one hairline |
| select-all row | 22px, indented 12px | 38px, flush | 22px, indented 12px |
| reading column | `max-width: 760px` | `min(100%, 840px)` | `max-width: 760px` |
| reading pane byline | author · tier · N replies | **absent** | author · tier · N replies |
| opening post | the lede | first row of the reply list | the lede |
| quiet-state mark | 30px, gold, filled | 56px `<img>`, uncoloured | 30px `<svg>`, gold |
| quiet state position | centred in the pane | stacked at the top | centred |
| empty queue | centred, composed | **no CSS at all** | centred, composed |

## Frames

| file | what it pins |
|---|---|
| `01-queue-desktop-light.png` | the inclusion cue is gold and in sentence case; status pills stay uppercase; the star inside the cue is filled |
| `02-queue-commended-order.png` | commends appear only in the Commended order, and the meta line does not wrap |
| `03-queue-desktop-twilight.png` | the Bruinen board reference clears 4.5:1 in the twilight register |
| `04-reading-pane.png` | the pane names the topic's author, rules off the byline, and leads with the opening post as the lede |
| `05-reading-pane-anonymous.png` | an anonymously opened topic reads `Anonymous`, with no rank and no trace of the real author |
| `06-reading-pane-quiet-state.png` | nothing chosen yet is centred in the pane, 30px, gold |
| `07-empty-scope.png` | the queue's own empty state is composed rather than an unstyled div |
| `08-queue-comfortable.png` | the comfortable register restores the snippet |
| `09-phone-queue.png` | the unread dot survives at 390px, where it is the only unread cue a row has |

## The one place production does not follow the design's render

`[data-density="compact"]` in `ForumInbox.dc.html:22-25` states four rules — row
padding `4px`, row gap `9px`, title `.99rem`, meta `margin-top: 1px` / `.67rem`,
chip gap `5px` — and every one of them is outranked by the inline `style`
attribute on the row it targets. The design's compact register therefore never
renders; the template shows comfortable spacing under a compact state.

Production applies what those rules **say**, which is why a like-for-like
measurement of the row shows differences. `the compact density applies the
register the design states` asserts each of the four values directly, so the
intent is pinned even though the design's own render disagrees with it.
