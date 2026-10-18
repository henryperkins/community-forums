# Inbox release integration

The owner requested commit/push to main and production verification after the
local Inbox fix. Fetched main had advanced to `62ae895b5ec310fff1bfad527d899d4b07aba067`
with the Boards refinement and asset-release record. The Inbox checkpoint is
`7b863b3493b95c934572d670b15d1cd7e97a984a`. Both histories are retained through a merge.

The Boards CSS block is byte-identical to incoming main; Inbox JavaScript is
byte-identical to the reviewed fix. Both changelog entries and the Boards design
description are retained. Conflicting generated manifest/digest records were
regenerated from the composed source while preserving remote deployment pins.
Independent source review found no actionable issue in the composition.

Asset version: `2371e926206bda24`. Current application digest:
`242054f46a76f47a1e8728f0e1e103a841be3fed21790cf8e09524732057f964`.

| Check | Result |
| --- | --- |
| Full PHP suite | 3,233 tests; 25,653 assertions; one expected database-specific skip; exit 0 |
| Chromium Inbox and keyboard compatibility | 62 passed; six intentional mobile skips |
| WebKit Inbox and keyboard compatibility | 62 passed; six intentional mobile skips |
| Boards geometry and script regressions | Six passed per engine |
| Combined browser cases | 136 passed; 12 intentional skips; zero failures or flaky cases |
| Text layout | 320 states across both engines/projects, two scopes/densities, five widths and four root text sizes; no clipping, overflow, identity collision or small command targets |
| Asset tests | 35 passed |
| Generated assets and Imladris | Checks passed; all 56 manifest hashes match files |

The additional Boards cases target long labels/touch targets, viewing controls
with enlarged text and the script boundary. Both browser projects use fresh,
isolated fixture databases; Chromium and WebKit ran sequentially. The main Inbox
specs and keyboard compatibility selection are the same as the parent evidence.
Six mobile skips per engine cover contracts intentionally exercised on desktop.
Raw environment-bearing Playwright reports remain disposable; the recorded
outcomes omit environment data, cookies and request headers.

- [Current source hashes and integration receipt](verification.json)
- [Every browser outcome and skip reason](browser-runs.json)
- [PHP summary](phpunit-summary.txt) and [asset-test summary](asset-tests-summary.txt)
- [Chromium desktop](chromium/desktop/for_you-1440-16px.png) and [doubled text at 320px](chromium/mobile/needs_answer-320-32px.png)
- [WebKit desktop](webkit/desktop/for_you-1440-16px.png) and [doubled text at 320px](webkit/mobile/needs_answer-320-32px.png)

This qualifies the combined local tree. Production build, active revision, live
asset and public-endpoint receipts are recorded in the
[production evidence](../production/README.md).
No physical-device or assistive-technology acceptance is claimed.
