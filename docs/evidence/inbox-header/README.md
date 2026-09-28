# Inbox header simplification

Verified locally on 2026-09-28 against the real PHP application and isolated
MariaDB databases. This is implementation evidence, not a deployment record.
Built asset version: `ffc423564f256d2f`.
Decision: [ADR 0042](../../adr/0042-inbox-header-simplification.md).

## Result

The inbox opens with one compact heading, Show and Sort controls, and its topics.
The actions disclosure contains the existing current-page read form and Help.
The topic count names its unit; row density remains in Appearance. At 860px and
below, Boards, Inbox, and Messages occupy a complete second navigation row.
The same 108px header token governs the drawer and scroll offsets.

The focused browser suite asserts the first topic starts above 360 CSS pixels
with JavaScript enabled in light and dark themes. It checks mobile widths 320,
390, 430, 760, and 860, and desktop widths 861, 901, 1024, 1080, 1280, and 1440.
Scope and sort retain
each other in real URLs. Actions work as native disclosures and POST forms with
JavaScript disabled; the no-JavaScript Sort panel fits at 390px and 430px.
Escape restores focus to the menu trigger. An axe scan of the inbox list with
Help open reports no WCAG 2 A/AA or 2.1 AA violations.

## Captures

These captures come from the final passing Chromium and WebKit header checks. Mobile
light/dark and Help use a 390px viewport; the no-JavaScript action capture uses
430px. All mobile captures preserve touch/mobile context at 2x pixel density.
Desktop captures use 1280px.

| State | Desktop | Mobile |
| --- | --- | --- |
| Light | [Capture](desktop/inbox-light.png) | [Capture](mobile/inbox-light.png) |
| Dark | [Capture](desktop/inbox-dark.png) | [Capture](mobile/inbox-dark.png) |
| WebKit, dark | [Capture](desktop/inbox-dark-webkit.png) | [Capture](mobile/inbox-dark-webkit.png) |
| Actions and Help | [Capture](desktop/inbox-help.png) | [Capture](mobile/inbox-help.png) |
| After marking a page read, JavaScript disabled | [Capture](desktop/inbox-after-page-read-no-js.png) | [Capture](mobile/inbox-after-page-read-no-js.png) |

## Verification

- `DB_TEST_DATABASE=retroboards_inbox_header_test composer test`: 3,121 tests,
  23,651 assertions, zero failures, one existing dedicated-database migration
  rehearsal skip.
- `DB_TEST_DATABASE=retroboards_inbox_header_test composer verify:imladris`:
  current generated assets; 24 tests and 305 assertions passed.
- `npm run test:assets`: 23 tests passed. `npm run check:assets` confirmed the
  fingerprinted assets and manifest match source.
- Combined Chromium run: 85 passed, seven viewport-specific skips, zero failures.
  It covers `inbox-header`, `unified-chrome`, `member-surfaces`,
  `forum-inbox-remediation`, `asset-release-layout`, and `field-error-a11y`.
  Current assets and the preceding two builds all loaded successfully with
  JavaScript enabled and disabled. Older builds retain their original header
  height; the current build uses 108px on mobile.
- Final Chromium header and asset-retention checks: 18 passed, zero skips or
  failures. These include the 861/901/1080px boundary assertions, full mobile
  context without JavaScript, and an explicit same-row 430px regression check.
  Historical asset checks allow the supported header heights and verify the
  matching content offset, so a release retains its geometry as its age changes.
- Final WebKit header checks: six passed, zero skips or failures, covering the
  same responsive, light/dark, focus, axe, and no-JavaScript contracts.

The combined run used this command from `tests/browser` after preparing the
isolated browser database with `prepare.sh`:

```sh
DB_DATABASE=retroboards_inbox_header_e2e E2E_PORT=8038 \
RATELIMIT_PATH=/tmp/retroboards-inbox-header-final-ratelimits \
PACKAGES_STORAGE_PATH=storage/packages-e2e-inbox-header \
RB_EVIDENCE_DIR=/tmp/inbox-header-regressions-final \
npx playwright test inbox-header.spec.ts unified-chrome.spec.ts \
  member-surfaces.spec.ts forum-inbox-remediation.spec.ts \
  asset-release-layout.spec.ts field-error-a11y.spec.ts
```

The final focused checks use the same environment with fresh rate-limit and
evidence directories: `npx playwright test inbox-header.spec.ts
asset-release-layout.spec.ts` for Chromium, and `E2E_LAYOUT_BROWSER=webkit npx
playwright test inbox-header.spec.ts` for WebKit.

Use fresh test rate-limit storage for repeated combined runs. An earlier run
reused buckets from prior checks and received three 429 responses; the fresh
uninterrupted run above passed. The inbox fixture now refreshes post timestamps
together with each topic's activity timestamp when re-seeded, preserving the
read-cursor tuple used by the real application.

## Release preparation

The deployment preflight found that the already-live avatar release
`a160f1a21c51fcb2` had not yet been recorded in `deployedReleases`. Its live HTML
references and all 24 fingerprinted asset hashes matched commit `2913e682`.
That exact commit was exported to an isolated directory and recorded with
`npm run assets:record-release -- a160f1a21c51fcb2`. The inbox build then inherited
that verified retention record and its complete files. The inbox candidate
remains `ffc423564f256d2f`; this operation did not prematurely mark it deployed.

## Boundaries

This evidence does not include a physical iPhone or a production deployment.
WebKit was exercised through Playwright on Linux, including mobile emulation.
Automated accessibility checks complement the focus and native-navigation tests;
they are not a full assistive-technology audit.

An independent review also identified an existing inbox behavior outside this
presentation change: after previewing and removing a row from the Unread scope,
the page-read form can retain the initial hidden topic IDs until navigation.
This change preserves that form's server contract; the tested no-JavaScript
page-read flow submits exactly the displayed page.
