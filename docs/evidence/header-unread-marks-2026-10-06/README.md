# Header unread marks and current surface — 2026-10-06

Evidence for the `/impeccable colorize` pass on the member header. It takes the critique's
state-mark finding (`.impeccable/critique/2026-10-06T21-51-37Z__templates-partials-topbar-php.md`)
in the owner's chosen direction: **the Inbox count leads**.

The pass changes CSS only, in `app.css`'s Member chrome block and scoped to the member bar.
The console's bell and the home page's notices count are untouched. The fingerprint in
[working-tree.json](working-tree.json) covers the tested working tree on `553004b1`, which also
carries the [header hardening](../header-hardening-2026-10-06/README.md) slice. No commit, push
or deployment is implied. The decisions are recorded in ADR 0032 ("Unread marks and the current
surface — 2026-10-06"), and the DESIGN.md Topbar entry describes the result.

**Colour strategy:**
- Gold in the bar means unread. Evergreen marks place and action.
- The bar spends at most one saturated gold point, the Inbox's dot. Every other count is a
  pale chip, and no gold field is larger than a chip.
- Numerals hold 4.5:1 and state marks hold 3:1, in both registers and in forced colours.

| Before (critique) | After | Captures |
|---|---|---|
| Two unrelated counts. Inbox and Messages wore a pale chip at 10.24px. The bell wore a solid gold disc in 11px bold EB Garamond, painted from primitives (`--ink-900`), and it was the loudest mark in the bar. | The Inbox count wears the thread row's unread mark: the 8px `--accent-2` dot with its 2px halo, drawn as `::before` so count updates keep it, beside medium-weight Mono numerals. Messages, the bell and the menu's Notifications row share one chip: gold ink on gold-soft, Mono at the 0.7rem floor, tabular figures. | Day and twilight on [desktop](desktop/counts-light.png) ([twilight](desktop/counts-dark.png)) and [phone](mobile/counts-light.png) ([twilight](mobile/counts-dark.png)). Account menu: [day](desktop/menu-light.png), [twilight](desktop/menu-dark.png). |
| The bell's "99+" covered most of the 18px glyph. | The count sits beside the glyph. The bell keeps a 40px floor and grows only by the count: 69px with "12", 76px with "99+". | The counts captures above. |
| The bell's hover dimmed the glyph to 2.89:1 in twilight. | It takes the toggles' sunken wash and body ink: 9.10:1. | Asserted in the spec. |
| The current surface was a 1.04:1 wash, and it vanished under forced colours. | A 2px rule in the pill's own ink (10.08:1 day, 7.78:1 twilight) joins the wash. The rule is a border, so it survives forced colours; it is evergreen, not gold. | [Forced colours, desktop](desktop/forced-colours.png), [phone](mobile/forced-colours.png). |
| The pane toggles' band was 2.65:1 when shown and absent when hidden, so both toggles were the same empty box. | The band is filled at full strength when the pane is shown and outlined when hidden, the star toggle's grammar. | [Shown](desktop/toggles-shown-light.png), [hidden](desktop/toggles-hidden-light.png), [twilight shown](desktop/toggles-shown-dark.png), [twilight hidden](desktop/toggles-hidden-dark.png). |

## Validation

- **New spec** `tests/browser/header-unread-marks.spec.ts`:
  - Chromium and WebKit each pass 10 tests, with 2 desktop-only skips.
  - On an unmodified `HEAD` export, 8 of the 10 fail on the defects above. The 2 that pass guard the bell's 40px target when it has nothing to count.
  - Details are in [chromium.txt](chromium.txt).
- **Existing browser specs**: 12 specs re-ran, including `header-hardening` and `notifications-unified`.
  - Every failure reproduces identically on the `HEAD` export:
    - `community-inbox-theme` :301 and :415
    - `a11y` :574 (both projects) and :198
    - `notifications-unified` :139, mobile
  - The :139 failure records a real pre-existing bug for the adapt pass: without JavaScript, the phone board rail sticks below the header and covers the content scrolling beneath it.
- **PHPUnit**: full suite, 3,127 tests and 23,751 assertions; the one skip is the dedicated 0077 rehearsal ([phpunit.txt](phpunit.txt)).
- **Asset checks**: `npm run build`, `check:assets` and `composer check:imladris` are current after the digest refresh.

## Scope and environment

- **How it ran**: synthetic Chromium and WebKit on Linux, with emulated phone viewports and touch, and forced colours via Playwright emulation.
- **Data**: real unread data where the seed has it (chrome_reader's unread topic, alice's notification). Messages counts were written the way the poll writes them.
- **Not covered**: physical devices and assistive-technology certification.
- **Design system**: the design's ForumNav still draws a gold-soft chip on Inbox alone and draws the band only while shown. The next shared-chrome sync should take these changes or answer them.
- **Still open from the critique**: the phone row's targets, gaps, label size and focus order (adapt), and the polish items.
