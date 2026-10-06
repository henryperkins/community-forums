# Chrome consistency — 2026-10-06

The six runtime findings and the documentation finding from the read-only review
are addressed locally, along with subscription-title overflow, long-brand admin
header displacement and a WebKit saved-details startup regression found during
verification. This evidence is
for the working tree based on `ff3c47e4`; no commit, push or deployment is implied.
The [working-tree fingerprint](working-tree.json) records the tested asset version
and application-surface digest.

| Finding | Result | Evidence |
|---|---|---|
| Open phone board drawer allowed focus into covered main content | Focus enters the drawer, Tab/Shift+Tab wrap, covered main content is inert, and Escape/scrim dismissal restores the opener. Resize releases the main content and keeps focus visible even with a closed desktop rail. Delayed enhancement preserves an already-focused native link. | New `chrome-consistency.spec.ts`, existing `startup-geometry.spec.ts`; [Chromium focus](chromium/drawer-keyboard.png), [WebKit focus](webkit/drawer-keyboard.png). |
| Desktop rail scrolled under the header | Sticky rails clear the 62px member header and retain their remaining viewport height. Inner-scrolling routes and the phone drawer retain their offsets. | Both-engine geometry checks on settings and Boards/Inbox/Search/Compose; [Chromium](chromium/settings-scrolled-dark.png), [WebKit](webkit/settings-scrolled-dark.png). |
| Plain errors offered controls for absent panes | Layout capabilities determine which controls render. Authenticated errors retain identity and destinations, omit drawer/pane controls, and preserve member preferences. | Kernel tests cover 404, admin 403 and denied private-board 404; browser tests and scoped axe checks cover 404/403 headers. [Chromium](chromium/error-header.png), [WebKit](webkit/error-header.png). |
| Tags/topic primary state differed from boards | Boards is current on `/tags`, `/tags/*` and authorized canonical topics. Its root URL remains usable. Task surfaces keep their own state. | Kernel and both-engine route tests. |
| Folder shortcuts lacked standard board state | One board-link renderer supplies active state, unread pills, URLs and visibility labels to both copies. Inbox preview updates both pills; reload preserves read state. Counts come from the same read-gated aggregate and repeat without inflating Inbox totals. Lost private access removes both copies. | Kernel tests include public/private/hidden/muted/denied boards and membership loss; both-engine preview/refresh checks. |
| Current admin area started off-screen on phones | At 860px and below, a native disclosure names the current area before interaction. It opens the same ordered, role- and flag-gated destinations, with a non-link current item. Desktop keeps its horizontal tier. | Kernel role/flag tests and both-engine light/dark and no-JS journeys. [Light](chromium/admin-current-area-light.png), [dark](webkit/admin-current-area-dark.png). |
| Chrome descriptions had drifted | DESIGN's two stale navigation descriptions were corrected; a scoped sidecar extension, ADMIN's responsive contract, ADR 0032 follow-up and reconciliation/changelog notes record the final behavior. | Pre-existing DESIGN and sidecar changes were snapshotted. Comparison confirms only two DESIGN paragraphs and the new sidecar navigation extension were added by this work. |
| Long subscription title widened settings | Names wrap at a readable width. A narrow panel puts the title, delivery controls and off action on successive rows, including tablet layouts where both sidebars consume space. The wider panel retains its horizontal row. | Both engines at 320/390/719/720/860/861/1024/1280/1440px, light/dark, JavaScript on/off. Assertions cover readable title width and successive control placement, beyond document overflow. [Chromium no-JS](chromium/subscriptions-light-no-js.png), [WebKit no-JS](webkit/subscriptions-light-no-js.png). |
| Long community name displaced admin controls | The controls and brand mark retain their natural size while the wordmark shortens. | Existing 900px long-brand regression and both-engine measurement. [Chromium](chromium/admin-header-900px-long-brand.png), [WebKit](webkit/admin-header-900px-long-brand.png). |
| WebKit restored a saved Messages column as an overlay | The controller waits for styles before restoring the saved column. Overlays start closed with the phone back control reachable. Explicit details fragments stay open during resize; a restored column closes when it becomes an overlay, preserving the saved preference. | Existing `messages-audit-regressions.spec.ts` P2-1, extended for fragments and resize, in both engines. The [pre-fix startup probe](messages-startup-before.json) recorded no loaded stylesheets and `position: static` before load, followed by the correct fixed-overlay style. Explicitly opened drawer: [Chromium](chromium/messages-details-1280.png), [WebKit](webkit/messages-details-1280.png). |

## Validation

- Full `composer test`: **3,125 total, 23,724 assertions, zero failures/errors,
  one skip**. The skip is the separate 0077 migration down/up rehearsal, which
  requires its specifically named throwaway database; see [skip.txt](skip.txt).
  The stale-read-view queue regression ran and passed. This frontend work does
  not claim that dedicated migration rehearsal.
- `composer verify:imladris`: **24 passed, 305 assertions**. Generated runtime
  resources match the adopted contract. The application digest was refreshed
  after generating the new fingerprinted bundles; imported design source and
  the bounded member-surface CSS bridge were retained.
- `npm run build` and `npm run check:assets`: passed. `npm run test:assets`:
  **23 passed**. Retained deployed asset versions remain in the manifest.
- Chromium: `chrome-consistency`, `unified-chrome`, `startup-geometry` and
  `admin-dashboard`: **51 passed, 23 intentional viewport skips**. This includes
  guest/member/operator chrome, existing long-account-name checks, themes,
  320px native primary links, persisted panes, keyboard access, blocked/delayed
  scripts and no-JavaScript behavior.
- WebKit: `chrome-consistency` and `admin-dashboard`: **19 passed, 11 intentional
  viewport skips**, covering the corrected chrome, light/dark registers,
  native no-JavaScript navigation and existing operator layout/a11y checks.
- Messages P1-1, P2-1 and P3-5: **3 passed in each engine**. These cover shared
  modal focus handling, saved column/overlay behavior, explicit fragments,
  resize, button keyboard semantics, focus return and the native no-JS link.
- `git diff --check`: passed.

The admin browser harness now supports the second engine. WebKit preserves the
declared fractional border where Chromium quantizes it. A standalone reproduction
also established that Playwright screenshot capture causes WebKit to reject a
temporary stylesheet under the application's strict CSP. The admin harness
excludes that exact console message only while its screenshot helper runs;
application CSP violations outside capture and every other error remain checked.
The application's CSP is unchanged.

## Scope and environment

Tests use real PHP rendering and MariaDB in two separate, dedicated databases:
`retroboards_chrome_fix_browser_20261006` for browser fixtures and
`retroboards_chrome_fix_tests_20261006` for PHPUnit. Fake seeded credentials and
dummy provider configuration are local only. The server uses localhost with a
matching WebAuthn RP ID. Both databases were dropped after verification; the
schema query confirmed zero remaining review databases. The temporary server was
stopped and port 8036 was confirmed closed. Diagnostic logs remain in the scratch
directory; no production data was used or changed.

Screenshots and synthetic mobile/touch/keyboard checks cover Chromium and WebKit
on Linux; they are not physical-device or assistive-technology certification.
Axe checks are scoped to representative chrome plus the existing admin suite;
they do not certify every app page. Production was not deployed or re-audited.
The original read-only review artifacts remain intact in
`/tmp/retroboards-chrome-review-20261006/`.
