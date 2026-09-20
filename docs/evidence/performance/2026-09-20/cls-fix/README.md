# Mobile rail and reply-dock geometry

Implemented locally on 2026-09-20. Warm mobile CLS is **0.038149** in three consecutive runs, below the requested **0.1** target. The comparable local baseline was **1.047470**. The earlier production measurement was **1.036996**; this change has not been deployed or measured in production.

## What changed

- CSS selects the mobile drawer, bounded thread scroll area, and compact reply geometry before deferred scripts run, using `scripting: enabled`. With JavaScript disabled, the existing flowing rail, inline Topic tools, and full textarea form remain available.
- Native fragment links open and close the rail and Topic tools while `app.js` is delayed or blocked. Enhancement replaces those links with the existing buttons, preserves an already-open drawer and focused control, and adds Escape/focus return. Only the matching drawer fragment is consumed; post deep links are preserved.
- Before composer enhancement, focus or typed text exposes the full reply form. The installed controller retains explicit minimize, draft restoration, and empty-form collapse. Server-rendered errors and drafts retain their expanded state.
- A lazy rich-editor upgrade preserves an already-focused textarea in Source mode, including its caret and continued typing. Members can explicitly choose Rich text afterwards.

Geometry is established by the initial stylesheet while content and native controls remain usable. This follows the space-reservation approach in [web.dev's CLS guidance](https://web.dev/articles/optimize-cls).

## Measurements

These are disposable **local moderator-member fixtures**, using the seeded “Share your favourite keyboard shortcuts” topic. They are laboratory results, not production p75 estimates. The Browser plugin was unavailable; the repository's installed Playwright/Chromium harness was used.

Chromium 149.0.7827.55, 390×844, DPR 2, mobile/touch enabled, 4× CPU slowdown, 150 ms network latency, 1.6 Mbps down / 750 Kbps up. Each sample observes 3.5 seconds after load. No input was sent during measurement. A local-only [router](lab/router.php) gives hashed assets the production immutable-cache policy; warm samples recorded **17 cached responses**. There is no request interception in these cache measurements.

| Sample | Before CLS | After CLS | After sum of all shift values |
| --- | ---: | ---: | ---: |
| Cold | 0.097459 | 0.000553 | 0.037901 |
| Warm 1 | 1.047470 | 0.038149 | 0.038149 |
| Warm 2 | — | 0.038149 | 0.038149 |
| Warm 3 | — | 0.038149 | 0.038149 |

Chromium marked one cold shift as `hadRecentInput` in both versions despite the harness sending no input. The table therefore also reports unfiltered shift sums. The three warm results exclude no shifts. All final samples returned HTTP 200 with zero console/page errors.

[Machine-readable summary](measurements.json), [raw before](lab/before.json), [raw after](lab/after.json), [before screenshot](lab/before-warm.png), [after screenshot](lab/after-warm.png). The capture harness is [browser-measure.cjs](../browser-measure.cjs).

The controlled delayed-bundle test independently guards actual geometry, so input attribution cannot mask the regression:

| Thread geometry | Before fix, before → after app | After fix, throughout startup |
| --- | --- | --- |
| Main top | 739.17 → 62 px | 62 px |
| Thread scroll top | moved up 677.17 px | 78 px |
| Dock height | shrank another 165.72 px when composer loaded | 67 px |
| Dock top | moved during both enhancements | 761 px |

See [failing baseline](red/mobile/startup-thread-delayed.json) and [passing geometry](green/mobile/startup-thread-delayed.json). Those regression tests deliberately delay bundles using routing, which disables cache; they are separate from the cold/warm measurements above. Residual shifts are smaller thread-content/header changes, rather than movement of the main region or dock. The earlier production image-dimension finding remains outside this fix.

## Verification

- **7 new browser regressions pass:** delayed home/thread startup, blocked app, native drawer handoff, blocked composer, delayed rich editor with caret/typing preservation, and actual JavaScript-disabled navigation/forms. [Log](startup-tests.txt), [test source](../../../../../tests/browser/startup-geometry.spec.ts).
- **83 existing applicable browser cases passed across the initial and focused reruns**; 31 viewport-specific cases were skipped. Coverage includes desktop/mobile drawers, keyboard focus, no-JS navigation, restored drafts, explicit minimize, posting, topic tools, reduced motion, responsive geometry and axe checks. [Initial run](browser-initial.txt), [affected rerun](browser-rerun.txt), [final drawer/visual checks](browser-final.txt).
- The initial browser run exposed a selector collision with `body.topic-tools-open` and a test locator that assumed only one drawer toggle existed. Both were corrected. Disposable-fixture presence-rate exhaustion and two network-idle timeouts cleared on the focused rerun; posting assertions passed.
- **8 asset tests pass**, generated assets match their sources, and the PHP templates lint cleanly. `composer verify:imladris` passes **24 tests / 296 assertions** after the approved baseline refresh. [Asset tests](asset-tests.txt), [asset check](asset-check.txt), [release Imladris verification](release-imladris.txt).
- Release **PHPUnit: 2,956 tests, 22,331 assertions, zero failures**, with six existing deprecations and one skip. The approved baseline refresh cleared the original presentation-review checksum failure. The focused fidelity/runtime integration suite also passed **30 tests / 326 assertions**. [Release log](release-phpunit.txt), [original pre-approval log](phpunit.txt), [focused log](phpunit-focused.txt).

Representative [desktop drawer](screenshots/browser/desktop/81-thread-tools.png), [mobile drawer](browser/mobile/03-drawer-open.png), [blocked-composer form](green/mobile/startup-blocked-composer-native-form.png), [native drawer handoff](green/mobile/startup-native-drawer-handoff.json), and [caret-preservation evidence](green/mobile/startup-delayed-adapter-preserves-input.json) are retained.

## Release approval

The original performance brief reserved presentation-baseline refresh and release for human review. After reviewing this implementation, the user explicitly requested commit, push to `main`, and production deployment on 2026-09-20. That authorization supersedes the earlier hold. `config/imladris-runtime-baseline.json` now records the [reviewed application digest](application-digest.txt), and the generated Imladris manifest has been rebuilt. The original [gate output](imladris-gate.txt) is retained as historical evidence.

The production release follows the repository runbook: pushing `main` triggers Workers Builds. Authenticated production measurements must be repeated after the exact commit has deployed; the local measurements above are not a claim about live production CLS.

## Cleanup

The dedicated fixture database and its two temporary PHP servers were removed after capture. Local fixture cookies were deleted. Thirty-seven pre-existing browser-evidence files rewritten by regression tests were restored byte-for-byte; selected new captures live only under this evidence directory. The pre-existing development server on port 8011 was left running. See [cleanup record](cleanup.json).
