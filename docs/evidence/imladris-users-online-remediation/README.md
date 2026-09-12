# Users Online remediation — evidence (ADR 0031)

Captured by `tests/browser/users-online-remediation.spec.ts`
(`cd tests/browser && npm run evidence:presence`), 15 tests × `desktop` (1280×800)
and `mobile` (390×844 @2x).

## Why this directory exists at all

It is the first `/users-online` capture in the history of this repository.
`tests/browser/seed.php` never wrote `last_seen_at` or `show_presence`, and the
roster excluded the viewer, so every previous evidence run photographed an **empty**
presence rail and no frame of the page was ever taken. That is how a page using
five classes with zero CSS rules — a default bulleted list, a name and handle
collided into "Elrond Peredhel@elrond", and a 0×0 presence dot — passed through
several release gates. Under PRODUCT_DESIGN §13, this surface's UI had never been
visually verified.

## Frames

| File | Shows |
|---|---|
| `desktop/01-directory.png` | The roll: three-column grid, filters with counts, search, pagination, the guidance strip; the rail beside it capped at 5 with "+10 more". |
| `desktop/02-directory-dark.png` | The same page in the twilight register. The away dot must re-theme here — the design's `--amber` has no dark remap and would not have. |
| `desktop/03-rail-after-poll.png` | The rail *after* a forced poll cycle. It used to reflow from six rows to twenty about a second after load. |
| `desktop/04-directory-nojs.png` | JavaScript disabled. Filters are links, search is a GET form, paging is links. |
| `desktop/05-directory-signed-in.png` | Signed in: the viewer is on their own roll, marked "you". |
| `mobile/*` | The same five, at phone width, with the rail opened from the nav toggle. |

Frames are written **per project**. A single shared path let the mobile run
overwrite the desktop frames with 390px-wide captures filed under the desktop
name — an unexamined artifact of exactly the kind this directory exists to stop.

## What the spec measures, and why it measures rather than matches

A test asserting "the class is present" would have passed against the broken
page. These assert geometry and computed style instead:

- the presence dot has a **non-zero box**, standalone and in a row;
- `[hidden]` on the widget computes **`display: none`** (`.presence-widget { display: block }`
  used to beat the user-agent rule, so the attribute was decorative);
- the rail's **height does not change** when the poll lands, and the row count
  equals the server's own `data-presence-limit`;
- an unchanged row **keeps its DOM node** across a poll, so the live region has
  nothing to re-announce;
- the away colour differs from here-now, **changes** between registers, and does
  **not collapse onto** here-now in dark;
- `.presence-name` and `.presence-sub` occupy **different lines**;
- a members-only profile is absent for a guest and present for a member — the
  row marker, not the handle, because the page legitimately echoes a search term
  back into its own input.

## Recaptured 2026-09-12

The frames were regenerated after the presence handoff sync and the verbatim port of the
page from the design's template (the 860px column, the roll as a card, the empty card, the
pager's own buttons, the magnifier and focus ring in the search field, the auto-fit guidance
grid) and after the member chrome became the design system's ForumNav / BoardRail (ADR
0032). The spec's measurements are unchanged; `unified-chrome.spec.ts` adds the cascade
cases this spec never covered (the seat's leaf, the profile dot, the bare dot, the hover
states).
