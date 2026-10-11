# Header review regressions — 2026-10-07

This record covers the three findings from the review of the uncommitted member
header changes and their fixes. The working tree is based on `553004b1`; no
commit, push, or deployment is implied. The existing two-row member header and
108px shared height remain the design decision in ADR 0042.

| Finding | Fix and regression coverage |
|---|---|
| At a 320px desktop Chromium viewport, the classic scrollbar leaves a 305px header. The action cluster wrapped, moving the first controls to −16.5px and the route row below the fixed header. The same regression affected nearby widths through 350px. | Spacing follows the header's actual inline size. Outer gutters and gaps ease from 8px to 4px, and the counted bell's inner padding yields before its 44px target. The live-app test covers 320, 330, 340, 350, 360, and 390px and asserts that all controls occupy one row inside the 108px header. |
| At 320px with Large text and capped Inbox/Messages counts, the route row extended to 324.42px and introduced horizontal page scroll. | Route padding and gaps adapt to available width while retaining the font size and 44px height. Live-app assertions cover Large text at 320, 360, and 390px in desktop and touch contexts, plus day/twilight captures. |
| The header's `?board=7` link could choose board ID 7 instead of the board whose slug is `7`. | `board` prefers an exact listed slug, then supports a legacy ID when no slug matches. Explicit `board_id` and rejected POST data resolve by ID. A listed unpostable slug falls back without being reinterpreted as an ID. Five HTTP tests cover sort order, explicit/legacy IDs, 422 draft preservation, and posting/visibility gates. Browser cases click the real header link with JavaScript enabled and disabled. |

The layout tests use the real application and built asset URLs against a private
browser database. Bell polling returns deterministic `105` unread notifications
and Messages; the server-counted Inbox badge is set to `99+` in the DOM to isolate
the layout case from seed size. The unread synchronization test separately uses
102 real topics and the real read endpoints. Large text uses the same
`data-font-size="large"` attribute as the member preference.

The compose browser fixture adds an empty public numeric-slug board sorted after
General and a dedicated reader. Its slug equals General's ID, so the former
resolver selected the wrong destination. It does not add unread topics or
modify existing members' preferences.

## Before and after

The before images are isolated specimens of the reviewed production header
markup and final CSS cascade, with loaded production fonts and capped counts.
The after images are the real `/inbox` page in the corrected working tree.

- Classic scrollbar: [before](before/classic-scrollbar-320.png),
  [Chromium after](chromium/desktop/classic-scrollbar-320.png),
  [WebKit after](webkit/desktop/classic-scrollbar-320.png).
- Large phone text: [before](before/large-text-320.png),
  [Chromium day](chromium/mobile/large-text-320-light.png),
  [Chromium twilight](chromium/mobile/large-text-320-dark.png),
  [WebKit day](webkit/mobile/large-text-320-light.png),
  [WebKit twilight](webkit/mobile/large-text-320-dark.png).
- Numeric destination after the actual New topic click:
  [Chromium desktop](chromium/desktop/numeric-board-js.png),
  [Chromium phone without JS](chromium/mobile/numeric-board-no-js.png),
  [WebKit desktop](webkit/desktop/numeric-board-js.png),
  [WebKit phone without JS](webkit/mobile/numeric-board-no-js.png).

## Validation

The new layout spec failed before the CSS fix in all three applicable cases
(one project-specific skip). Four of the five new compose HTTP cases failed
before the resolver fix; all five pass afterward. The legacy-ID case already
passed and protects that compatibility behavior.

| Final check | Result |
|---|---|
| Full `composer test` | 3,134 tests, 23,833 assertions, one skip; no failures. [Log](phpunit.txt). |
| Scoped Chromium browser run | 75 passed, 21 project-specific skips; no failures. [Log](chromium.txt). |
| Same scoped run in WebKit | 75 passed, 21 project-specific skips; no failures. [Log](webkit.txt). |
| Asset delivery tests | `npm run test:assets`: 23 passed, no skips or failures. |
| Generated assets and runtime | `npm run check:assets` and `composer check:imladris` passed. |
| Syntax and whitespace | PHP syntax checks for the controller and new PHP tests/fixture, JavaScript syntax checks for `app.js`/`tour.js`, and `git diff --check` passed. |

The PHPUnit skip is the existing dedicated, fixture-free 0077 migration rehearsal
(`AppThreadIntelligenceMigrationTest::test_0077_down_and_up_rehearsal_on_fixture_free_schema`),
which requires a separate `retroboards_thread_intelligence_clean` database. No
migration changed in this fix. Browser skips restrict a case to its applicable
desktop or mobile project; the three new applicable layout cases and four new
compose cases passed in each engine. Both browser passes include the existing
drawer focus/resize, menu hit testing, theme, unread synchronization, and native
no-JavaScript navigation cases.

An independent review found no remaining actionable issue. Its separate
production-HTML specimens also checked medium/Large text, desktop/touch
viewports, and capped counts across 320–860px in both engines.

```bash
# Run from the repository root, with an already-created private test DB.
DB_TEST_DATABASE=retroboards_compose_fix_20261007 composer test
npm run check:assets
npm run test:assets
composer check:imladris
git diff --check

# Run prepare.sh from the root, then Playwright from tests/browser.
DB_DATABASE=retroboards_e2e_header_fix_20261007 bash tests/browser/prepare.sh
DB_DATABASE=retroboards_e2e_header_fix_20261007 \
  E2E_BASE_URL=http://localhost:8019 E2E_SKIP_WEBSERVER=1 \
  RB_EVIDENCE_DIR=/tmp/rb-header-fix-20261007/final-chromium \
  npx playwright test header-regressions.spec.ts compose-destination.spec.ts \
    header-phone-rows.spec.ts header-polish.spec.ts header-hardening.spec.ts \
    header-unread-marks.spec.ts unified-chrome.spec.ts
# Reseed the same throwaway browser DB, then repeat with
# E2E_LAYOUT_BROWSER=webkit and a separate RB_EVIDENCE_DIR.
```

The standalone PHP server used the same private browser database, local-only
dummy APP_KEY/provider credential, array mail driver, and task-specific rate
limit/package storage. The browser and PHPUnit databases are separate. The new
cases are included in `npm run evidence:chrome` and the main browser-evidence
command; their default captures live under the workflow's uploaded evidence
directory. `E2E_LAYOUT_BROWSER=webkit` also selects WebKit for unified chrome.

An initial broader Chromium run exposed a fixture timing mismatch in the unread
test: it assigned `threads.last_post_at` from a fresh PHP clock instead of the
stored post timestamp. Crossing a second could leave a topic unread after its
latest post was read. The fixture now uses the stored timestamp, satisfying the
read cursor's timestamp comparison. The isolated recheck passed before that
correction; the final complete run validates the corrected fixture.

An initial asset/full-suite check also caught an outdated application digest
after the delivery build replaced the candidate CSS file. The reviewed baseline
and runtime manifest were refreshed after that build; generated asset bytes and
the asset version stayed unchanged. Final checks use that corrected baseline.

## Limits and identity

Chromium and WebKit ran on Linux with emulated viewports and touch. The original
width failures were reproduced in Chromium; the targeted original WebKit probe
did not reproduce them because its metrics differed. Passing WebKit coverage
guards compatibility. Physical devices, real browser zoom, and assistive
technology certification were not tested. The full browser-evidence collection
and remote CI were not run for this scoped fix.

Asset candidate: `a084b1b37e0c6efe`, CSS
`/assets/dist/app-style-COc4R5l-.css`. The reconciled application digest is
`1cdf7b6fa4c2006a6c0ec12dfe450ad0afc6ae9434e7a94320eb70327663d16c`.
See [working-tree.json](working-tree.json) for source fingerprints.
