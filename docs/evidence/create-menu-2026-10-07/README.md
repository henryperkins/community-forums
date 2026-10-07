# Shared creation row — 2026-10-07

This folder retains the original implementation's captures and source snapshot.
The subsequent review found three regressions; their fixes and final-source
verification are recorded in [regression-fixes/README.md](regression-fixes/README.md).
Use that record for the current working tree. Original measurements and
captures remain historical evidence. Archived log trailing whitespace was
normalized during release preparation; test results and screenshots are intact.

These captures and geometry records exercise the PHP application with the shared
creation row from ADR 0043. The source is
[`tests/browser/create-menu-subheader.spec.ts`](../../../tests/browser/create-menu-subheader.spec.ts).
The browser results are summarized in [`results.json`](results.json); the four
`<browser>-<project>-results.json` files contain the individual route checks and
capture metadata.
PHPUnit, Imladris/assets, legacy browser regressions, and the final Messages
audit are recorded separately in [`ROOT_CHECKS.md`](ROOT_CHECKS.md).

Both complete browser runs retain the known native conversation failures:

| Browser | Tests passed | Tests failed | Intentional skips | Route checks | Required matrix combinations |
| --- | ---: | ---: | ---: | ---: | ---: |
| Chromium | 18 | 2 | 8 | 2,801 | 2,688 |
| WebKit | 18 | 2 | 8 | 2,801 | 2,688 |

All 5,376 required browser/route/state/width/theme/font/JavaScript combinations
completed. The additional 226 route checks cover representative captures and
the classic-scrollbar example. Each browser records eight failed geometry rows,
including duplicate representative checks, for six unique native Large-text
conversation combinations. All hard assertions passed. Each browser's two
failing tests are the native representative-capture and native member-matrix
tests; their only errors are the retained page-height and outer-composer
viewport expectations described below.

The runs used Playwright 1.61.1, Node 24.21.0, Chromium 149.0.7827.55 and WebKit
26.5. The 13 template, asset and test hashes in
[`source-snapshot.json`](source-snapshot.json) matched after both complete runs.
The snapshot is the uncommitted working tree based on the recorded `HEAD`.

## Coverage

The route matrix checks `/`, `/c/general`, `/tags`, a seeded topic, `/inbox`,
`/messages`, a seeded conversation, `/messages/new`, `/compose`, `/search`,
`/notifications`, `/feed`, the seeded member's profile, `/drafts`,
`/settings/account`, and a 404. Each route is requested as a member, a guest, and
a member with `dms` disabled, with and without application JavaScript, at
320, 390, 860, 861, 1024, 1280, and 1440px. Every matrix combination uses both
parchment (`light`) and twilight (`dark`), at normal (`medium`) and Large text.
Guest routes that require authentication are recorded with their final redirect
destination; their authentication layout does not render the member header.

The desktop project uses a normal browser viewport with classic scrollbars. The
mobile page fixtures use `isMobile: true`, touch, and a device scale factor of 2;
the guest/disabled-DM contexts use the same mobile/touch emulation at the default
scale factor of 1. Widths in the matrix and image names are CSS pixels.
The width tests intentionally skip 320/390/860px in the desktop project and
861/1024/1280/1440px in the mobile project, assigning each matrix width to its
matching device lane. The additional classic-scrollbar test runs only in the
desktop project and is skipped in mobile. These are eight project/width skips
per browser; all seven requested widths run in the combined matrix.
The full route matrix uses a height of 844px. A separate desktop check exercises
320px with Large text. Keyboard tests exercise Enter/Tab creation, board-aware
destination selection, Escape/focus return, opening one shared menu at a time,
internal-pane scroll dismissal at 400px height, in-place Messages creation, and
typed text recovery after a failed dialog send. Axe checks the shared row in each
desktop/mobile browser lane. Native creation and the server-rendered dialog
failure remain covered with JavaScript disabled.

Geometry assertions cover the 62px desktop/guest and 108px signed-in phone header
heights, the 44px phone trigger, absence of horizontal page overflow, a single
leading breadcrumb/tab row, the sub header as the first child of the skip-link
destination, bounded Messages/Inbox rooms, and the docked conversation composer
at the matrix height. Pages retain their `h1`; the board's own creation controls
remain present in representative captures.

Member appearance preferences are saved in the isolated database before each
batch so the server stamps theme and text size before application JavaScript
initializes. Guests have no saved appearance preference; the test harness sets
the same root attributes to exercise their two CSS themes and font sizes.
Browser instrumentation remains available while the site's JavaScript is
disabled. The no-JS dialog-error check adds the real dialog form's `origin=dialog`
field to the standalone new-message form, reproducing that server response.

## Captures

Files live under `<browser>/<desktop|mobile>/`. Names include width, theme, text
size, and JavaScript state. `normal` corresponds to the medium text preference.
Home, board, topic, Messages list, conversation, Inbox, and 404 examples are
captured at 320px mobile and 1440px desktop in every theme/font/JavaScript
combination. Board/menu examples also show all seven widths with Large text.
Dialog errors, the disabled-DM direct link, and classic-scrollbar behavior have
their own captures.

Open menus are asserted visible before and after viewport screenshots. Their
images use viewport capture because a full-page capture can scroll the document
and dismiss a progressive-enhancement menu. Other examples use full-page
screenshots. The JSON metadata records the actual HTML appearance attributes,
route, viewport, and menu state for each image.

There are 159 distinct matrix/representative PNGs per browser, plus one fresh
WebKit verification capture under `supplemental/`. The baseline comparison and
legacy regression captures are separate evidence groups. Representative visual
inspection included the [desktop open menu](chromium/desktop/menu-1440-dark-large-js.png),
[phone open menu](webkit/mobile/menu-320-dark-large-js.png),
[phone conversation](webkit/mobile/conversation-320-light-large-js.png),
[dialog failure](webkit/mobile/dialog-error-dark-large-js.png), and
[native error panel](chromium/mobile/dialog-error-dark-large-no-js.png).
Their labels match the measured theme and text size. The inspected controls,
menus and retained drafts are visible; the native room growth is documented
below. [`visual-inspection.json`](visual-inspection.json) records the inspection
and the fresh WebKit SVG verification. An initial image-display concern about
the phone plus was absent when re-viewing the original PNG and in three fresh
captures on the same source. The original capture remains intact.

## Reproduction

Install the browser harness dependencies and its Chromium/WebKit binaries if
they are not already installed. The commands below use the local
`retroboards-mariadb` container and reset only a disposable evidence database.
This container's root socket requires `docker exec -u 0`; the ordinary
`prepare.sh` reset is bypassed after database creation.

```bash
cd /home/ubuntu/community-forums
docker exec -it -u 0 retroboards-mariadb mariadb -uroot -p -e 'CREATE DATABASE IF NOT EXISTS retroboards_e2e_create_menu_evidence CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
export DB_DATABASE=retroboards_e2e_create_menu_evidence
export DB_RESET_CONTAINER=create-menu-nonexistent
export E2E_PORT=8024
export E2E_BASE_URL=http://localhost:8024
export RATELIMIT_PATH=/home/ubuntu/community-forums/storage/ratelimit-e2e-create-menu-evidence
export PACKAGES_STORAGE_PATH=/home/ubuntu/community-forums/storage/packages-e2e-create-menu-evidence
export RB_EVIDENCE_DIR=docs/evidence/create-menu-2026-10-07
bash tests/browser/prepare.sh
(cd tests/browser && npx playwright test 'browser/create-menu-subheader\.spec\.ts' --max-failures=0)
bash tests/browser/prepare.sh
(cd tests/browser && E2E_LAYOUT_BROWSER=webkit npx playwright test 'browser/create-menu-subheader\.spec\.ts' --max-failures=0)
```

Playwright launches the PHP server on `localhost:8024`. The harness seeds its own
accounts, reuses their session cookies only in memory, and restores the `dms`
override after each rollback check. Preparation runs before each browser group.
Logs and session material are kept outside this evidence folder; Playwright's
temporary `.artifacts` directory is removed after each completed run.

## Limits

The strict no-page-scroll assertion remains red for native phone conversations
with Large text. The existing 8rem reading floor reaches 144px and allows the
room to grow once its header, letters, and composer no longer fit together.
The [untouched-HEAD comparison](baseline/native-room-comparison.json) exercises
112 baseline/current combinations: both browsers, all seven widths, both themes,
and both text sizes. The baseline tracked files were unmodified at
`f8d4350ff417f3a8d68d7870fdc57947104a5643`; its separate worktree, database, and
port are recorded in that artifact. Matched failing native lanes overflowed by
192px on the baseline. In that comparison fixture, current Large-text overflow
is 28px at 320/390px in both browsers, and 17px Chromium / 16px WebKit at 860px.
Send bottoms in that fixture are 832px at 320/390px and 820px at 860px, within
the 844px viewport. The matrix uses a separate seeded conversation and records
its own measured bounds in the browser results; those values are summarized in
[`results.json`](results.json). Its Large-text native limits, identical across
light/dark themes, are:

| Browser | Width | Page overflow | Composer bottom | Send bottom |
| --- | ---: | ---: | ---: | ---: |
| Chromium | 320px | 28px | 872.19px | 832.19px |
| Chromium | 390px | 17px | 860.50px | 820.50px |
| Chromium | 860px | 17px | 860.50px | 820.50px |
| WebKit | 320px | 28px | 872.16px | 832.16px |
| WebKit | 390px | 28px | 872.16px | 832.16px |
| WebKit | 860px | 16px | 860.47px | 820.47px |

Send's visible intersection is 1.0 in every row. The 144px reading floor is
preserved. Baseline and current 320px captures accompany the comparison JSON.

Only native phone conversation page-height and composer-bottom viewport checks
are soft assertions, at the original `<= viewport height + 1` thresholds. They
keep their tests failing while allowing every matrix route, capture, and draft
recovery step to run. Their JSON rows explicitly record `noPageScroll: false`,
`geometryPassed: false`, and `passed: false`; composer and Send positions plus
Send's visible intersection are recorded. Native-limit images include both
viewport and full-page captures. Every other assertion remains hard.

These are local Chromium/WebKit captures of the current working tree.
Production and physical devices were not verified. Keyboard coverage is limited
to creation and Messages dialog actions. The 844px matrix asserts bounded rooms;
the shorter-height check covers menu dismissal and preserves a conversation's
established minimum-content growth. Suspended,
banned, and newcomer enforcement is outside this browser matrix and is covered
separately by the PHP integration gates. Broad existing browser regressions,
PHPUnit, and generated-asset/Imladris checks are separate release gates and are
reported in [`ROOT_CHECKS.md`](ROOT_CHECKS.md), including the old Messages copy
assertion that also fails on untouched `HEAD`.
