# Inbox controls, visibility and shared creation evidence

This records the original evidence pass and its build boundary. Later review fixes and final release checks are recorded in [Inbox preview recovery](../inbox-preview-recovery-2026-10-08/README.md).

Rendered inspection started on 2026-10-07 and completed across the UTC boundary on 2026-10-08. This directory contains the corrected design's qualification captures; stale assets and the first inspection are preserved separately under `/tmp` and are not presented as final qualification.

The confirmation qualified **92/92 unique browser checks across 105 executions**: 96 passing executions and nine preserved failures. The initial 92 executions passed 85 and failed seven. A bounded seven-case reconciliation passed five and retained two WebKit rapid `j` then `#` failures. The resulting navigation repair and a late border-cascade repair were then checked with six affected cases, all passing. This is reconciled qualification, not an uninterrupted green run. [execution-results.json](execution-results.json) records every lane and its command.

| Confirmation lane | Initial result | Targeted reconciliation |
| --- | --- | --- |
| Chromium controls and shared subheaders | 43 passed / 1 failed | Native mobile row journey: 1 passed |
| Chromium thread workflow | 0 passed / 2 failed | Desktop and mobile: 2 passed |
| WebKit controls and shared subheaders | 40 passed / 4 failed | Native desktop/mobile: 2 passed; shortcut desktop/mobile: 2 failed |
| WebKit thread workflow | 2 passed / 0 failed | Not repeated |

The final affected-case check passed the existing title/border test in both engines and both projects (four cases), plus both WebKit shortcut cases. The rest of the 92-case matrix was not repeated after these two narrowly scoped source repairs.

The corrected harness retains full containment assertions. Native menus must use static document flow, increase reachable scrolling bounds, and be fully painted inside their list pane after ordinary scrolling. Playwright's initial fractional scroll stopped approximately 1.4 px short in Chromium; a bounded scroll of the actual list owner now reveals the entire panel. A no-JavaScript WebKit wait incorrectly relied on page animation frames and was removed. The workflow fixture was already in Needs answer without a history event; the test now makes real Open → Needs answer transitions.

The rapid-keyboard failures started an asynchronous topic preview with `j` and immediately submitted the tomorrow form with `#`. The Starred row disappeared after navigation, but Snoozed remained empty and the private database recorded no hide. The application now invalidates the pending preview on capture-phase native submission and pagehide, preventing its late canonical fallback from competing with form navigation. The unchanged rapid input sequence now observes the exact snooze POST's 303 response, stays on Inbox Starred and finds the persisted timed state in Snoozed. A stronger late CSS selector also removes the original upturned dividers; the existing title case now checks computed border radius 0 px and left border width 1 px.

## Covered behavior

- The header retains its title, scope, current count, unread summary, sort, actions and creation control in one physical band at widths 320, 393, 861 and 1440, in both themes and medium/large appearance type. Narrow panes expose combined scope/sort choices with full accessible names and a visible caret. Desktop panes remain constrained by their board rail.
- Scope, sort, create, row and bulk panels fit their usable pane or viewport. Interactive actions measure at least 44 × 44 CSS px. Snoozed recovery appears early in the scope menu.
- Escape returns focus to each originating summary. Enhanced Help has an explicit close control, receives Escape, returns focus to Inbox actions and suppresses mutation shortcuts while it owns focus. Its native disclosure remains available without JavaScript.
- Individual and selected-page timed hide, persistent hide and restoration run as real POST/redirect forms, enhanced and without JavaScript. The switch is OFF for either hidden kind and ON after restoration. Bulk actions preserve the selected page boundary and hidden Clear selection stays hidden without JavaScript.
- Preview removal updates page-read IDs and counts. After an earlier Unread row is removed, Shift-selection uses the surviving rows without page errors and correctly checks the master checkbox. Persistent preview rows update their native action to Mark unread and submit it successfully.
- Legal long unbroken topic text stays within its content column at narrow and rail-constrained widths. Row dividers retain a computed 0 px radius and 1 px left border through the final cascade. Long scope/order names and unread counts remain available at larger type.
- Scoped Axe WCAG 2 A/AA and WCAG 2.1 AA checks cover the Inbox header/list and open Help. No violations were reported by the qualified panel journeys.
- Boards, board topics, thread, Messages, conversation and composer preserve contextual shared creation routing and the compact header contract.

Representative captures are [320 px large type](chromium/mobile/closed-320-light-large.png), [393 px dark](webkit/mobile/closed-393-dark-default.png), [861 px bulk actions](webkit/desktop/bulk-menu-861-light.png), [Help](chromium/mobile/help-393-light.png), [wrapped topic title](webkit/desktop/unbroken-title-861-dark.png) and [last native row](webkit/mobile/last-row-native-320.png).

Native last-row measurements are recorded per engine/project in `native-last-row-geometry.json`. At 320 px, Chromium painted all 203.4375 px of its panel and WebKit painted all 203.421875 px. Both panels contribute to their list owner's reachable scroll height. `document.documentElement.clientWidth` equaled `innerWidth` in these measured lanes. The viewport assertion uses usable client width; no scrollbar gutter mismatch was observed here.

## Asset and fixture identity

[served-assets.json](served-assets.json) records HTTP-fetched asset hashes matched against the build manifest before the final affected cases ran. The final corrective build is `f2dfa7a4c4d019ca`:

- CSS `/assets/dist/app-style-B8T0LKSf.css`: `18fee5c120e3289dea3d2e732159bf71b20e8a3dfb250843a59bdfa6674c275e`.
- JavaScript `/assets/dist/app-0d803f892404e3bd.js`: `0d803f892404e3bd332e32c808d8f5bd193b02be43d14ed4a59fc24ef2f8bd55`.

The initial confirmation and harness reconciliation used `dea42daf175bcd4b`, preserved in [confirmation-first-assets.json](confirmation-first-assets.json). Those captures remain associated with that identity. Final title/border captures were overwritten by the affected-case check on `f2dfa7a4c4d019ca`; remaining captures retain the earlier confirmation build. No full matrix claim is made for the final build.

All commands used private database `retroboards_e2e_inbox_polish_20261007`, port 8482, isolated rate-limit/package paths and one Playwright worker. The private database was prepared with existing `prepare.sh`, seeded by the existing fixture, and updated with additive migration 0083. No browser work used the main PHP server on 8000 or the Cloudflare tunnel. Playwright stopped each owned server after its command; port 8482 had no listener after reconciliation.

Run from `tests/browser` after preparing the private database:

```bash
export DB_DATABASE=retroboards_e2e_inbox_polish_20261007
export E2E_PORT=8482
export RATELIMIT_PATH=storage/ratelimit-e2e-inbox-polish
export PACKAGES_STORAGE_PATH=storage/packages-e2e-inbox-polish

for rb_browser in chromium webkit; do
  E2E_LAYOUT_BROWSER="$rb_browser" RB_EVIDENCE_DIR=docs/evidence/inbox-controls-polish-2026-10-07 npx playwright test inbox-controls-polish.spec.ts inbox-header.spec.ts shared-subheader-context.spec.ts --output="../../docs/evidence/inbox-controls-polish-2026-10-07/.artifacts/$rb_browser-controls"
  E2E_LAYOUT_BROWSER="$rb_browser" RB_EVIDENCE_DIR="docs/evidence/inbox-controls-polish-2026-10-07/gate-a-$rb_browser" npx playwright test gate-a.spec.ts --grep 'phase 4 topic workflow' --output="../../docs/evidence/inbox-controls-polish-2026-10-07/.artifacts/$rb_browser-workflow"
done

E2E_LAYOUT_BROWSER=chromium RB_EVIDENCE_DIR=docs/evidence/inbox-controls-polish-2026-10-07 npx playwright test inbox-controls-polish.spec.ts --project=mobile --grep 'row hiding.*without JavaScript' --output=../../docs/evidence/inbox-controls-polish-2026-10-07/.artifacts/chromium-reconciled
E2E_LAYOUT_BROWSER=chromium RB_EVIDENCE_DIR=docs/evidence/inbox-controls-polish-2026-10-07/gate-a-chromium npx playwright test gate-a.spec.ts --grep 'phase 4 topic workflow' --output=../../docs/evidence/inbox-controls-polish-2026-10-07/.artifacts/chromium-workflow-reconciled
E2E_LAYOUT_BROWSER=webkit RB_EVIDENCE_DIR=docs/evidence/inbox-controls-polish-2026-10-07 npx playwright test inbox-controls-polish.spec.ts --grep 'row hiding.*without JavaScript|snooze shortcut' --output=../../docs/evidence/inbox-controls-polish-2026-10-07/.artifacts/webkit-reconciled

E2E_LAYOUT_BROWSER=chromium RB_EVIDENCE_DIR=docs/evidence/inbox-controls-polish-2026-10-07 npx playwright test inbox-controls-polish.spec.ts --grep 'long unbroken topic' --output=../../docs/evidence/inbox-controls-polish-2026-10-07/.artifacts/chromium-affected-confirmation
E2E_LAYOUT_BROWSER=webkit RB_EVIDENCE_DIR=docs/evidence/inbox-controls-polish-2026-10-07 npx playwright test inbox-controls-polish.spec.ts --grep 'long unbroken topic|snooze shortcut' --output=../../docs/evidence/inbox-controls-polish-2026-10-07/.artifacts/webkit-affected-confirmation
```

The stale-asset setup attempt is preserved in `/tmp/inbox-controls-polish-stale-assets-20261008/SETUP-FAILURE.md`; it was stopped and does not qualify current source. The first valid inspection used build `90924db7488667c0`, passed 38/40 checks, identified the native bottom-panel defect and supplied the source correction batch. It is preserved in `/tmp/inbox-polish-first-current-assets-20261008/FIRST-INSPECTION.md`. The requested layout source detector returned `[]`; it was not used as rendered proof.

## Server and developer verification

The qualified full PHPUnit run used private database `retroboards_ui_polish_full_20261008` and completed with **3,163 tests, 24,706 assertions, zero failures and one existing skip**. The skipped migration 0077 down/up rehearsal requires its dedicated fixture-free database and was not run. The [full log](verification/phpunit.log) retains the skip and expected diagnostic output. Focused snooze tests also passed with native PDO prepares: 42 tests and 585 assertions.

The full suite preceded the final border and preview-navigation repairs described above. After those two CSS/JavaScript changes, `composer verify:imladris` passed its runtime/source checks and 24 tests with 305 assertions; `npm run check:assets` also passed. These final checks are recorded in [runtime verification](verification/imladris-runtime-check.log) and [asset verification](verification/assets-check.log). The full PHPUnit suite was not repeated after those repairs.

Earlier verification attempts exposed a removed source-owned compatibility bridge, outdated row-wrapper assertions and an application digest recorded before the asset build. Those issues were corrected before the qualified full run. An unchanged thread-intelligence concurrency test also failed during the first 11 minutes after UTC midnight because its backdated request crossed the reservation's day boundary. That behavior was independently reproduced on a private database; the qualified full run passed after that window. These reconciled results do not represent an uninterrupted green run or a repair to that unrelated test.

The existing developer server and tunnel were left running. Additive migration 0083 was applied to the local `retroboards` database. The [developer health and asset record](verification/developer-health-assets.json) confirms HTTP 200 database health and CSS/JavaScript hashes matching final build `f2dfa7a4c4d019ca`. An [authenticated Inbox request](verification/developer-authenticated-inbox.json) returned HTTP 200 with the compact controls, visibility switch and final asset references. Its temporary local QA session was removed after the request; no session token or member content is retained in this evidence.

These changes remain uncommitted in the working tree based on `573f905d`. This pass did not push a commit or qualify production deployment.

## Limits

Playwright 1.61.1 ran headless Chromium and WebKit on Linux using desktop/mobile emulation. There was no physical iPhone, real Safari, Firefox, assistive-technology session or browser-zoom qualification. Large type is the application's appearance preference. The database, titles and unread counts are test fixtures. This browser lane does not prove production deployment, production database health or release status. Initial and reconciled runs are reported separately; final-build checks are limited to the six affected cases above.
