# Header phone rows — 2026-10-07

Evidence for the `/impeccable adapt` pass on the member header. It takes the critique's phone
findings (`.impeccable/critique/2026-10-06T21-51-37Z__templates-partials-topbar-php.md`) in the
owner's chosen direction: **keep ADR 0042's two rows and fix the details**.

The pass changes `app.css` (Member chrome block, the phone drawer, the colorize block's phone
rules), adds one reading-order block and one focus guard to `app.js`, draws the divider only beside
a pane toggle (`topbar.php`), and turns the drawer's close link into a named cross
(`sidebar.php`). The fingerprint in [working-tree.json](working-tree.json) covers the tested
working tree on `553004b1`, which also carries the
[header hardening](../header-hardening-2026-10-06/README.md) and
[unread marks](../header-unread-marks-2026-10-06/README.md) slices. No commit, push or
deployment is implied. The decisions are recorded in ADR 0032 ("Phone rows and the drawer —
2026-10-07") and in an addendum to ADR 0042. The DESIGN.md Topbar and Sidebar rail entries describe
the result.

Measurements are from Chromium with phone emulation (`isMobile`, touch) on a member account with
the widest counts ("99+"), unless a row says otherwise. Before is the working tree as the
colorize pass left it.

| Before | After | Captures |
|---|---|---|
| The phone bar's first-row targets were 40px with 2–7px between them; route labels were 11.8px, 1px apart. | Every first-row control (drawer opener, lockup, search, New topic, bell, seat) is a 44px target with 8px between targets. The gap eases to 4px only below about 340px, so a "99+" bell at 320px still fits. The lockup is the one control that yields further, to 38px at 320px and never past its mark. Routes keep the desktop's 13.1px labels, 44px tall and 8px apart. The bar stays 108px. | [390px, day](mobile/rows-390-light.png), [twilight](mobile/rows-390-dark.png), [360px](mobile/rows-360.png), [320px](mobile/rows-320.png) |
| Tab went opener, lockup, Boards, Inbox, Messages, then back up to search, New topic, bell and seat. | Tab and a screen reader's swipe go row by row: opener, lockup, search, New topic, bell, seat, then Boards, Inbox, Messages. `app.js` moves the routes after the account controls while the drawer breakpoint applies, and back beside the lockup above it. Without JavaScript the order stays the desktop one. | Asserted in both engines. |
| A guest's phone bar spent a second row on Boards alone (108px). Sign up was a 43×19 target and Log in 69×33. | A bar with a single route keeps one 62px row, since the lockup already leads to Boards. The drawer and scrim start under it. Sign up and Log in are 44px tall. | [Guest, day](mobile/guest-390-light.png), [twilight](mobile/guest-390-dark.png) |
| The community's name was hidden at 900px and below. At 1180px the default name was clipped (87 of 102px) while the search kept its 300px. | The name stays wherever at least about five letters show, ending in an ellipsis; below that it drops whole and the mark stands alone, never a stub ([widths](mobile/lockup-widths.txt)). Above 1080px the name and the search share the room, so the default name is whole at 1180px with the search at 249px ("99+" counts). | [430px](mobile/lockup-430.png), [860px](mobile/lockup-860.png), [900px](desktop/lockup-900.png), [1180px](desktop/lockup-1180.png) |
| An orphan divider showed at 721–860px, after the pane toggles had given way to the drawer, and on the plain error page. | The divider is rendered only beside a pane toggle and hidden at the drawer breakpoint. | [800px](mobile/divider-800.png), [404 page](desktop/divider-404.png) |
| The enhanced drawer hid its close link, so its Tab loop had no close control; focus landed on the first board. | A 44px cross at the drawer's top corner, the Topic tools style, in both the native fragment drawer and the enhanced one. The enhanced drawer focuses it on opening. Enter or a tap closes the drawer and returns focus to the opener. If the window widens past 860px with focus on the cross, focus moves to the rail's first link. | [Day](mobile/drawer-light.png), [twilight](mobile/drawer-dark.png) |
| Without JavaScript the phone rail stayed sticky under the bar. Scrolled, it covered 108–785px of an 844px screen on `/notifications` and took the taps (`notifications-unified.spec.ts:139`). | The rail is a bounded 176px block above the content that scrolls away with the page, the treatment account pages already had. | [Board page, top](mobile/nojs-board-top.png), [scrolled](mobile/nojs-board-scrolled.png) |
| Touch screens above 860px got the desktop bar's 30–35px controls. | With `pointer: coarse`, every control on the single-row bar is 44px tall, and New topic's label stays centred. | [1024px touch](desktop/touch-1024.png) |

## Validation

- **New spec** `tests/browser/header-phone-rows.spec.ts`:
  - Chromium and WebKit each pass 12 tests, with 4 skips for project-specific tests.
  - On an unmodified `HEAD` export, 11 of the 12 fail on the defects above. The one that passes
    guards that the desktop bar keeps a guest's route.
  - Details are in [chromium.txt](chromium.txt).
- **Existing browser specs**: 17 specs re-ran ([chromium.txt](chromium.txt)).
  - `notifications-unified` :139 on mobile, a pre-existing failure, now passes.
  - The run found one real regression: with focus on the new close control, widening past 860px
    lost focus. It is fixed, and `chrome-consistency` covers it. The drawer and header specs then
    re-ran green on the fixed tree.
  - Every other failure reproduces identically on the `HEAD` export:
    - `community-inbox-theme` :301 and :415
    - `a11y` :574 (both projects)
    - `auth-hardening` :203 (both projects; the passkey button is not rendered in this environment)
- **Updated assertions** in `header-unread-marks.spec.ts`: the phone bell's floor is 44px (the
  desktop's stays 40px).
- **PHPUnit**: full suite, 3,128 tests and 23,758 assertions; the one skip is the dedicated 0077
  rehearsal ([phpunit.txt](phpunit.txt)).
- **Detector**: `impeccable detect` on `topbar.php` and `sidebar.php` reports no findings.
- **Asset checks**: `npm run build`, `check:assets` and `composer check:imladris` are current after
  the digest refresh.

## Scope and environment

- **How it ran**: synthetic Chromium and WebKit on Linux, with emulated phone viewports and touch,
  and JavaScript disabled through Playwright.
- **Not covered**: physical devices, real browser zoom, and assistive-technology certification.
  **Correction from the subsequent review:** the original 316px row budget did not allow for a
  classic scrollbar gutter. At a 320px Chromium desktop viewport the header was only 305px wide,
  which caused a third row and clipped controls. The
  [regression fix and evidence](../header-regressions-2026-10-07/README.md) now cover narrow
  desktop viewports as well as Large text. Real browser zoom remains untested.
- **Still ADR 0042**: phones keep the two rows and the 108px height for members. In landscape on a
  short phone those rows take a large share of the height; the owner kept that decision.
- **Design system**: the layer still hides the name at 900px and sizes phone controls at 40px. The
  next shared-chrome sync should take these changes or answer them.
- **Superseded in part**: the [polish pass](../header-polish-2026-10-07/README.md) later moved the phone lockup into a
  size-container wrapper. When the name drops, the link no longer keeps the room it would have used, and the search
  joins the actions on the right. The measurements here are this pass's.
