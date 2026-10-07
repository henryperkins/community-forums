# Files changed — 2026-10-07

Implementation, contract, documentation and generated-file changes, including
the subsequent review remediation, plus the evidence slice below.
The work remains uncommitted on `main` at `f8d4350ff417f3a8d68d7870fdc57947104a5643`.

## Templates

- Modified: [`templates/board.php`](../../../templates/board.php)
- Modified: [`templates/dm/index.php`](../../../templates/dm/index.php)
- Modified: [`templates/dm/new.php`](../../../templates/dm/new.php)
- Modified: [`templates/dm/show.php`](../../../templates/dm/show.php)
- Modified: [`templates/home.php`](../../../templates/home.php)
- Modified: [`templates/layout.php`](../../../templates/layout.php)
- Modified: [`templates/partials/dm_list.php`](../../../templates/partials/dm_list.php)
- Added: [`templates/partials/dm_compose_panel.php`](../../../templates/partials/dm_compose_panel.php)
- Added: [`templates/partials/subheader.php`](../../../templates/partials/subheader.php)
- Modified: [`templates/partials/topbar.php`](../../../templates/partials/topbar.php)
- Modified: [`templates/thread.php`](../../../templates/thread.php)

## Application styles and enhancement

- Modified: [`public/assets/app.css`](../../../public/assets/app.css)
- Modified: [`public/assets/app.js`](../../../public/assets/app.js)

## PHP contracts

- Added: [`tests/Integration/Core/AppCreateMenuTest.php`](../../../tests/Integration/Core/AppCreateMenuTest.php)
- Modified: [`tests/Integration/Core/AppDirectMessageTest.php`](../../../tests/Integration/Core/AppDirectMessageTest.php)
- Modified: [`tests/Integration/Core/AppMemberShellTest.php`](../../../tests/Integration/Core/AppMemberShellTest.php)
- Modified: [`tests/Integration/Core/AppMessagesRefinementTest.php`](../../../tests/Integration/Core/AppMessagesRefinementTest.php)
- Modified: [`tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php`](../../../tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php)
- Modified: [`tests/Unit/Core/MessagesStylesContractTest.php`](../../../tests/Unit/Core/MessagesStylesContractTest.php)

## Browser contracts

- Modified: [`tests/browser/compose-destination.spec.ts`](../../../tests/browser/compose-destination.spec.ts)
- Added: [`tests/browser/create-menu-helpers.ts`](../../../tests/browser/create-menu-helpers.ts)
- Added: [`tests/browser/create-menu-regressions.spec.ts`](../../../tests/browser/create-menu-regressions.spec.ts)
- Added: [`tests/browser/create-menu-subheader.spec.ts`](../../../tests/browser/create-menu-subheader.spec.ts)
- Modified: [`tests/browser/dm-reimagine.spec.ts`](../../../tests/browser/dm-reimagine.spec.ts)
- Modified: [`tests/browser/header-hardening.spec.ts`](../../../tests/browser/header-hardening.spec.ts)
- Modified: [`tests/browser/header-phone-rows.spec.ts`](../../../tests/browser/header-phone-rows.spec.ts)
- Modified: [`tests/browser/header-regressions.spec.ts`](../../../tests/browser/header-regressions.spec.ts)
- Modified: [`tests/browser/messages-audit-regressions.spec.ts`](../../../tests/browser/messages-audit-regressions.spec.ts)
- Modified: [`tests/browser/messages-poll-regressions.spec.ts`](../../../tests/browser/messages-poll-regressions.spec.ts)
- Modified: [`tests/browser/messages-refinement.spec.ts`](../../../tests/browser/messages-refinement.spec.ts)
- Modified: [`tests/browser/package.json`](../../../tests/browser/package.json)
- Modified: [`tests/browser/unified-chrome.spec.ts`](../../../tests/browser/unified-chrome.spec.ts)

## Product and design decisions

- Modified: [`CHANGELOG.md`](../../../CHANGELOG.md)
- Modified: [`DESIGN.md`](../../../DESIGN.md)
- Modified: [`PRODUCT_DESIGN.md`](../../../PRODUCT_DESIGN.md)
- Modified: [`docs/adr/0032-unified-member-chrome.md`](../../../docs/adr/0032-unified-member-chrome.md)
- Modified: [`docs/adr/0042-inbox-header-simplification.md`](../../../docs/adr/0042-inbox-header-simplification.md)
- Added: [`docs/adr/0043-create-menu-subheader.md`](../../../docs/adr/0043-create-menu-subheader.md)
- Modified: [`docs/design-system/imladris/LOCAL_RECONCILIATION.md`](../../../docs/design-system/imladris/LOCAL_RECONCILIATION.md)

## Generated delivery and runtime digest

- Modified: [`config/assets.json`](../../../config/assets.json)
- Modified: [`config/imladris-runtime-baseline.json`](../../../config/imladris-runtime-baseline.json)
- Removed: `public/assets/dist/app-5d592d83c0577727.js`
- Added: [`public/assets/dist/app-c99da55a79b26aa2.js`](../../../public/assets/dist/app-c99da55a79b26aa2.js)
- Removed: `public/assets/dist/app-style-BtEAtCAN.css`
- Added: [`public/assets/dist/app-style-BpZnYE67.css`](../../../public/assets/dist/app-style-BpZnYE67.css)
- Modified: [`resources/imladris/manifest.json`](../../../resources/imladris/manifest.json)

## Evidence slice

- Added [`docs/evidence/create-menu-2026-10-07/README.md`](README.md): coverage, capture method and limits.
- Added [`ROOT_CHECKS.md`](ROOT_CHECKS.md): repository checks, skip details and baseline comparisons.
- Added `results.json`, four browser/project result files, `source-snapshot.json` and `visual-inspection.json`.
- Added Chromium/WebKit matrix captures, supplemental visual verification and focused Chromium regression captures.
- Added `baseline/`: untouched-HEAD native-room measurements, screenshots, reproduction helper and original copy-failure proof.
- Added `validation/`: PHPUnit, Imladris, asset, browser and detector outputs.
- Added [`regression-fixes/README.md`](regression-fixes/README.md), with final-source checks, hashes and focused Chromium/WebKit captures. Original evidence remains historical.
- Added this file list. The original `.github/prompts/create-menu-subheader.prompt.md` remains as supplied.

No owner decision remains open. Browser limitations and remaining pre-existing failures are recorded in the evidence README and repository checks.
