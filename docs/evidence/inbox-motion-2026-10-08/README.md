# Inbox and shared-control motion — 2026-10-08

This records the original evidence pass and its build boundary. Later review fixes and final release checks are recorded in [Inbox preview recovery](../inbox-preview-recovery-2026-10-08/README.md).

The implemented motion uses the existing Imladris tokens: menu opacity `.8 → 1` over 140ms, selection toolbar and Help over 240ms, and view carets rotating through 180 degrees over 140ms. Action state and hit targets are available immediately. Keyboard focus inside an action panel cancels its entrance animation. Both OS and application reduced-motion preferences remove the entrance and caret motion.

Browser work used the Playwright CLI skill and the real PHP application on an isolated fixture:

- Database: `retroboards_e2e_inbox_motion_20261008`, migrated through 0083 and seeded with the existing browser/member fixtures.
- Server: owned process on `127.0.0.1:8483`.
- Rate/package storage: `storage/ratelimit-e2e-inbox-motion-20261008` and `storage/packages-e2e-inbox-motion-20261008`.
- Engines: CLI Chromium 155.0.8059.12 and WebKit 26.6 on Linux. Viewport captures cover 1440×900 and 393×900; final touch contexts use 393×844, scale 2, `isMobile: true`, and `hasTouch: true`.
- The developer server on port 8000 and its tunnel were left running.

## Qualification and build boundaries

The first complete batch and its bounded confirmation used build **`680700d52defa6f4`**, CSS `app-style-J56m_0J8.css` / SHA-256 `712548a525f622d8079ae00993ee2fcf97abf7400f7a4494c5b34183053108a5`, and JS `app-0d803f892404e3bd.js` / SHA-256 `0d803f892404e3bd332e32c808d8f5bd193b02be43d14ed4a59fc24ef2f8bd55`.

That batch covers both engines, both viewport classes, light/dark, and normal/OS reduced/application reduced preferences: 24 state combinations. Frame sampling records animation progress, opacity, caret transforms and panel bounds through 330ms. It also covers selection and clearing to zero, Help dismissal/focus, repeated disclosure activation, and static native no-JS row menus. The confirmation adds menu-open resizing and rapid switching between menus. Images named `*-1440-*-view.png`, `*-393-*-view.png`, and `*-393-native-row-menu.png` belong to this initial build.

The raw records deliberately retain failed assertions. The first harness incorrectly counted hidden pre-position boxes as painted, used ArrowDown where these native disclosures use Tab, and expected the native fade to have finished immediately after opening. WebKit also retains finished zero-time transition objects in `getAnimations()`; these are not active animation. The corrected confirmation filters painted, positioned frames and active animation objects and uses real Enter/Tab/Escape events. It then established two actual defects:

1. Global reduced-motion duration clamps created implicit `left`/`top` transitions on positioned panels in WebKit. The first visible frame could retain the old off-screen box. Panel `transition-property: none` now prevents those geometry transitions while preserving the opacity CSS animation.
2. Help's asynchronous `close` listener could steal focus after the user had already focused and activated another control. Captured events show Help Escape → scope focus/Enter → late focus return to Inbox actions → Tab to New. The listener now preserves the user's newly focused control and still returns focus on an ordinary Help dismissal.

Only affected cases were rerun after these corrections. Final qualification is build **`9cd60f8b2665c07a`**:

| Asset | SHA-256 |
| --- | --- |
| `/assets/dist/app-style-D9QfswNc.css` | `6e471a9b37f6e4ec9c2e8228afc18d2488a9b5e46a2b0352ca82304719cdeabb` |
| `/assets/dist/app-56e9b9a8f33fd541.js` | `56e9b9a8f33fd541d5f711640c7a65cda8b112ab1e98a34987ea0f94ee7965cf` |

Both engines fetched the final assets with HTTP 200 and matching hashes. Chromium's normal desktop smoke passed **18/18 runtime assertions**. WebKit's OS/application reduced-motion cases at desktop/phone widths passed **84/84 runtime assertions**. Every painted, positioned frame was contained and fixed, panel transforms stayed `none`, and reduced motion stayed opaque and instant. Both engines retained focus inside the panel in the actual quick Help Escape → next summary focus/Enter → Tab sequence and restored focus on an ordinary Help dismissal. No page errors occurred. These are runtime assertions, not a new PHPUnit or Playwright test suite. The full earlier matrix was not rerun on the final build.

Final real touch interactions open the view, switch menus, open/dismiss Help, select/clear topics, open bulk/topic actions, and exercise both reduced-motion paths. Both engines pass **11/11 touch assertions**, with no page errors. The touch screenshots and `chromium-touch-motion.webm` belong to the final build. The 3.44-second VP8 WebM records the actual interaction sequence rather than the frame-sampling harness; encoding uses an even width of 392 pixels for the 393-pixel viewport.

## Artifacts and reproduction

- `chromium-first-runtime.json` / `webkit-first-runtime.json`: initial raw batch.
- `chromium-runtime.json` / `webkit-runtime.json`: corrected confirmation, including the actual defects above.
- `keyboard-diagnosis.json`: fresh Enter → Tab succeeds without the Help close sequence.
- `help-keyboard-diagnosis.json`: captured asynchronous focus-stealing sequence before the correction.
- `chromium-affected-runtime.json` / `webkit-affected-runtime.json`: final affected checks.
- `chromium-touch-runtime.json` / `webkit-touch-runtime.json`: final touch results.
- `capture-first.js`, `capture-confirmation.js`, `capture-affected.js`, `capture-touch.js`: exact CLI evaluation functions, kept outside the application/test source. The first two intentionally retain the assertion history described above.

Commands ran from `output/playwright/inbox-motion-20261008` using named sessions. After preparing the private fixture and launching its PHP server, the capture commands were:

```bash
/home/ubuntu/.codex/skills/playwright/scripts/playwright_cli.sh -s=inbox-motion-chromium open http://127.0.0.1:8483/login --config chromium.json
/home/ubuntu/.codex/skills/playwright/scripts/playwright_cli.sh -s=inbox-motion-webkit open http://127.0.0.1:8483/login --config webkit.json
# Login through the rendered fixture form, then snapshot before each capture.
/home/ubuntu/.codex/skills/playwright/scripts/playwright_cli.sh -s=inbox-motion-chromium run-code --filename inspect.js
/home/ubuntu/.codex/skills/playwright/scripts/playwright_cli.sh -s=inbox-motion-webkit run-code --filename inspect.js
# Final build: only affected.js and touch.js, in both named engine sessions.
```

Both owned CLI sessions and port 8483 were closed after capture. Temporary CLI/session artifacts were preserved outside the working tree in `/tmp/inbox-motion-playwright-20261008`; no login/session files are part of the reviewable evidence.

## Server, assets and developer verification

`DB_TEST_DATABASE=retroboards_ui_polish_full_20261008 composer test` passed with **3,163 tests, 24,706 assertions and one existing skip**. The [full log](verification/phpunit.log) preserves the result and expected diagnostic output. This run used the initial motion build; the full suite was not repeated after the two final browser CSS/JavaScript corrections.

On final build `9cd60f8b2665c07a`, `composer verify:imladris` passed the generated/runtime checks and **24 tests with 305 assertions**. `npm run check:assets`, `node --check public/assets/app.js` and `git diff --check` passed. [Verification results](verification/results.json), [final runtime log](verification/imladris-runtime-final.log) and [final asset log](verification/assets-final.log) distinguish the build boundaries.

The [developer record](developer-assets-health.json) confirms HTTP 200 database health, served CSS/JavaScript hashes matching the final manifest, and final asset references in the anonymous server-rendered shell. Authenticated interaction evidence comes from the isolated browser fixture above. The developer server and tunnel remained running throughout.

Changes remain uncommitted in the working tree based on `573f905d71f3c146072cffd5fecb2d2ffaca995b`. This pass did not push a commit or qualify production deployment.

## Limits

This is Linux browser emulation, not a physical iPhone/Safari qualification. Light/dark and the application's reduced-motion attribute were set directly for runtime state coverage; this task does not requalify settings persistence. No Firefox, assistive-technology, physical-device, or zoom lane is claimed. Native no-JS behavior was checked on the initial motion build; the final corrections do not alter its PHP forms or static layout. Broad screenshots establish layout/identity, while frame records and the short video establish motion. Initial failures and focused reruns are preserved rather than presenting an uninterrupted green run.
