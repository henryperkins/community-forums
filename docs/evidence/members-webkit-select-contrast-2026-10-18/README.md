# Members WebKit select contrast — 2026-10-18

Members Role and State filters now inherit the shared styled select instead of WebKit native painting. Dark-mode labels remain readable, with native keyboard and GET filtering preserved. Forced-colors mode receives a visible focus outline.

The regression was reproduced before the fix: WebKit painted a light background under pale text (maximum sampled label contrast 1.268:1). After the fix, actual control pixels match the dark theme; sampled Role and State label pixels reach 12.19:1 and 11.50:1. See `paint-verification.json` and the synthetic before/after screenshots.

Validation: 18 focused browser cases passed across Chromium/WebKit, desktop/mobile, light/dark and JavaScript on/off; two WebKit forced-colors cases were skipped because emulation is unavailable. Both Chromium forced-colors cases passed. The existing Members/People suite passed 30 cases with 20 clean axe scans. All 35 worker/asset tests and the asset consistency check passed.

The full PHP suite ran 3,294 tests (26,064 assertions, one existing skip); its only failure was the generated Imladris manifest requiring regeneration after the reviewed presentation baseline update. The manifest is regenerated in the immediately-following main bookkeeping commit and its contract is rechecked there.

Focused reproduction command: `cd tests/browser && npx playwright test --config playwright.members-select.config.ts`. Fixtures use a private local database and synthetic accounts. Browser viewports are emulated; these checks do not certify physical devices.
