# Header hardening — 2026-10-06

Evidence for the `/impeccable harden` pass on the member header (`templates/partials/topbar.php`).
It follows the dual-agent critique that scored the header 27/40, recorded in
`.impeccable/critique/2026-10-06T21-51-37Z__templates-partials-topbar-php.md`.

This pass fixes the critique's two P1 findings and its context-blind controls (one P2). The
fingerprint in [working-tree.json](working-tree.json) records the tested working tree on
`553004b1`. No commit, push or deployment is implied. The decisions are recorded in ADR 0032
("Header hardening — 2026-10-06"), and DESIGN.md adds the Chrome-On-Top Rule.

| Finding | Correction | Proof |
|---|---|---|
| Page controls at z-index 30 painted over the sticky bar, which the layer stacks at 20, and over its account menu. A tap on a moderator's Log out reached the phone directory bar instead. | The bar sits at `--z-chrome` (40), an application token beside `--z-scrim` (55) and `--z-drawer` (60). Overlays that must cover the bar already start at 42. | Every account-menu item takes its own taps on `/` and on a topic at 390px. Scrolled, the directory bar passes under the bar's lower edge. [menu-open-home](mobile/menu-open-home.png), [menu-open-topic](mobile/menu-open-topic.png), [directory-bar-under-header](mobile/directory-bar-under-header.png). |
| A long community name (up to 80 characters allowed) pushed the account menu off-screen from 901 to 1280px. | The lockup has no flex basis and grows only to its own width. The wordmark ends in an ellipsis, and the accessible name stays the full site name. | An 80-character name on `/` and `/inbox` at 901, 1024, 1180, 1280 and 1440px: no sideways scroll, seat on screen, account menu inside the viewport at 901px. [901px](desktop/long-name-inbox-901.png), [1280px](desktop/long-name-inbox-1280.png). |
| A wide operator logo started a third phone row and lifted the first above the viewport, cutting the drawer opener in half. | The logo scales inside its 28px band (`object-fit: contain`). A lockup with no basis never breaks the wrapping first row. | A 6:1 logo at 390 and 320px: header stays 108px, opener whole, logo and seat on row 1, routes inside the bar. [390px](mobile/wide-logo-390.png), [320px](mobile/wide-logo-320.png). |
| New topic always opened the composer on the first board listed (Announcements). | On board pages and authorized topics it links to `/compose?board=<slug>`. Compose re-checks posting rights and falls back as before. | From `/c/general` the composer opens with General selected, and a General topic links the same board. Plain error pages keep the bare link. [new-topic-from-general](desktop/new-topic-from-general.png). |
| The reading-pane toggle showed from 861 to 1279px, where no reading pane column exists. It flipped its name and saved a preference with nothing visible changing. | It is drawn only from 1280px. ⌘J and ⌘B act only while their toggle is drawn. | At 1024 and 1279px the toggle is hidden and ⌘J saves nothing. At 1280px it toggles and persists. Behind the phone drawer ⌘B saves nothing. [pane-toggles-1280](desktop/pane-toggles-1280.png). |
| The pane toggles announced their state three times: a Hide/Show name, `aria-pressed` and `aria-expanded`. | Fixed names ("Board rail", "Reading pane") with `aria-pressed`, like the star toggle. | PHPUnit pins the markup, and the browser checks the accessible names and that `aria-expanded` is gone. |

## Validation

- **New browser spec** `tests/browser/header-hardening.spec.ts`:
  - **Chromium:** 6 passed, 4 project skips.
  - **WebKit** (`E2E_LAYOUT_BROWSER=webkit`): 6 passed.
  - **Against an unmodified `HEAD` export:** all 6 fail, each on the defect it targets. See [chromium.txt](chromium.txt).
- **Existing browser specs, Chromium** (run in a copy of the working tree, each on a fresh `prepare.sh`):
  - Eleven specs were re-run: unified-chrome, chrome-consistency, startup-geometry, member-surfaces, inbox-header, board-index-remediation, community-inbox-theme, forum-inbox-remediation, a11y, asset-release-layout and avatar-display.
  - `unified-chrome.spec.ts:575` asserted the removed `aria-expanded`; its two assertions now read `aria-pressed`, and it passes.
  - Four failures reproduce identically on the `HEAD` export, so they predate this change: `community-inbox-theme` :301 and :415, and `a11y` :574 on both projects.
- **PHPUnit:** the full suite, 3,127 tests and 23,751 assertions, has no failures. The one skip is the dedicated 0077 rehearsal. See [phpunit.txt](phpunit.txt).
- **Asset checks:**
  - `npm run build`, `npm run check:assets` and `npm run test:assets` (23 passed).
  - `composer check:imladris` passes after the application digest refresh.

## Scope and environment

- **What was tested:** synthetic Chromium and WebKit runs on Linux, with emulated phone viewports and touch, against PHP's built-in server and MariaDB.
- **Databases:**
  - `retroboards_e2e_hh`, `retroboards_e2e_hhr` and `retroboards_e2e_hhb` for the browser runs.
  - `retroboards_test_hdr*` for PHPUnit.
  - Long names and the wide logo were set through the settings table and restored by the spec.
- **Not covered:** physical devices and assistive-technology certification.
- **Out of scope:** the admin console bar keeps the layer's stacking, because `app.css` may not override a design-owned console class.
- **Still open from the critique:**
  - the unread state marks (colorize);
  - the phone row's targets, gaps, labels and focus order (adapt);
  - the polish items.
