# Mobile navigation, reply frame, and reactions

Local verification of the three screenshot-reported issues. The shared phone board drawer now uses `min(240px, 70vw)`. Folded reply and mobile Messages frames use a 12px radius and 4px inset around a 44px submit control. The nine built-in reaction choices use distinct SVG icons in a three-column grid, with accessible gesture names, hover hints, and unchanged native POST behavior. Custom reactions retain their visible names. Browser console checks also identified and prompted repair of the shared eye icon's malformed SVG path.

The subsequent uncommitted-change review found desktop picker overflow and missing visible names on static guest/read-only chips. Both are fixed and covered by expanded regression checks. [Review follow-up evidence](review-fixes/README.md) records the current verification results and screenshots; the checks below describe the initial implementation.

The working tree was clean at the start. No commit, push, or deployment was performed. Browser and PHPUnit runs used separate throwaway databases (`retroboards_e2e_mobile_20261012` and `retroboards_test_mobile_20261012`). The Browser plugin was unavailable; evidence uses the repository's Playwright harness and local PHP server on port 8028.

## Completed checks

| Check | Result |
| --- | --- |
| `composer test` | 3,232 tests, 25,630 assertions, one skip; exit 0 |
| `composer verify:imladris` | 24 tests, 305 assertions; exit 0 |
| `npm run build` and `npm run check:assets` | Generated delivery assets and manifest are current |
| `npm run test:assets` | 35 passed |
| New mobile control checks, Chromium | 6 passed, 2 desktop skips |
| Existing focused regression checks, Chromium | 22 passed, 8 viewport skips |
| New mobile control checks, WebKit | 6 passed, 2 desktop skips |
| Existing focused regression checks, WebKit | 22 passed, 8 viewport skips |
| `git diff --check` | Passed |

The new checks cover the drawer on Boards, Inbox, Messages, a topic, and Settings at 320px, 390px, and 820px; reply button containment at those widths and 1280px with normal and 200% text; Markdown and rich editor draft preservation; and named, distinct, 44px reaction choices that retain their icons after a real toggle. The existing checks cover desktop rail geometry, both themes, focus containment/return, native navigation and reactions without JavaScript, reaction tips and accessibility, reply expansion, and DM/edit composer mounts.

The controls originally failed the new mobile checks before the fixes. An initial full PHPUnit run identified a stale generated Imladris manifest; rebuilding it and rerunning the full suite produced the result above. The passing browser evidence comprises separate new-control and existing-regression runs, rather than one uninterrupted full browser suite.

Playwright 1.61.1's WebKit screenshot preparation inserts an inline `body {}` stylesheet to synchronize animations. The app's strict CSP refuses it. The new checks classify only this exact diagnostic during screenshot capture; other capture diagnostics and application errors before or after capture fail the checks. CSP was not relaxed.

## Scope and screenshots

These are emulated browser viewports, not physical iPhone or Android checks. Software keyboard behavior was not qualified. The 200% text checks verify the reply control's containment; they do not qualify full-page reflow across the app. These checks were completed before publication; [the review follow-up](review-fixes/README.md) records publishing verification separately.

The screenshots below are from the passing WebKit control run at 390 × 844 CSS pixels:

- [Narrower shared drawer](drawer-mobile.png)
- [Contained reply button](reply-mobile.png)
- [Compact reaction icons](reaction-menu-mobile.png)

To repeat the Chromium controls against a freshly prepared dedicated database:

```bash
DB_DATABASE=retroboards_e2e_mobile_20261012 \
  RATELIMIT_PATH=storage/ratelimit-e2e-mobile-20261012 \
  PACKAGES_STORAGE_PATH=storage/packages-e2e-mobile-20261012 \
  bash tests/browser/prepare.sh

cd tests/browser
DB_DATABASE=retroboards_e2e_mobile_20261012 E2E_PORT=8028 \
  RATELIMIT_PATH=storage/ratelimit-e2e-mobile-20261012 \
  PACKAGES_STORAGE_PATH=storage/packages-e2e-mobile-20261012 \
  RB_EVIDENCE_DIR=/tmp/retroboards-mobile-controls \
  npx playwright test mobile-controls.spec.ts
```

The control spec selects WebKit with `E2E_LAYOUT_BROWSER=webkit`. The existing-regression WebKit run used a temporary config applying that browser to both viewport projects. Existing regression selection: `surviving reaction counts|touch reaction addition|reactor tips remain|twilight reaction tips|without JavaScript a reaction|reply dock expands|empty reply dock|DM and edit mounts|rail state persists|closed phone drawer|mobile chrome reaches|desktop rail POST|geometry, in both registers` across `conversation-reactions.spec.ts`, `composer-expansion.spec.ts`, and `unified-chrome.spec.ts`.
