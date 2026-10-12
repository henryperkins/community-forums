# Reaction review fixes — 2026-10-12

Local verification of both findings from the mobile-controls review, completed
before publication.

## Fixes and failure evidence

1. **Desktop picker overflow.** The three-column picker previously extended to
   878px in an 861px viewport, and to 917px in a 900px viewport. The new browser
   assertions reproduced those failures in both themes. Anchoring the picker to
   its trigger's trailing edge keeps the menu and every 44px target inside the
   viewport, while retaining the native POST/303/GET submission path.
2. **Unnamed static reaction chips.** Guest and read-only aggregate chips now
   request visible labels from the shared reaction-face partial. The new PHPUnit
   regression initially found zero visible labels where nine were expected; it
   now verifies all nine built-in names for guests, suspended members and banned
   members, with no reaction forms, member tips or reactor identities. Browser
   checks verify visible Commend, Seconded and Illuminating names, their distinct
   icons and unchanged counts on desktop and phone layouts.

## Completed checks

| Check | Result |
| --- | --- |
| `composer test` | 3,233 tests, 25,648 assertions, one skip; exit 0 |
| Focused `AppReactionTest.php` | 21 tests, 124 assertions; exit 0 |
| `composer build:imladris` / `composer check:imladris` | Runtime manifest and application-surface digest refreshed; check passed |
| `npm run build` / `npm run check:assets` | Delivery assets and asset manifest current |
| `npm run test:assets` | 35 passed |
| Chromium: `mobile-controls.spec.ts` + `conversation-reactions.spec.ts` | 53 passed, 5 viewport-specific skips; exit 0 |
| WebKit: the same two specs | 53 passed, 5 viewport-specific skips; exit 0 |
| PHP lint and `git diff --check` | Passed |

Each browser completed one uninterrupted 58-case focused run. The expanded
controls contribute 20 passes and 4 skips; the existing reaction suite contributes
33 passes and one touch-only skip. PHPUnit and browser checks used separate
throwaway databases, `retroboards_test_reactionfix_20261012` and
`retroboards_e2e_reactionfix_20261012`, with the PHP browser server on port 8058.

The desktop picker is measured at 769px, 820px, 861px, 900px, 1024px and 1280px in
both themes, with JavaScript enabled and disabled. Static-chip checks cover all
three viewer states at 320px, 390px and 820px on phones, and 861px, 900px and 1280px
on desktop, in both scripting modes. Fixtures restore reactions, account states
and preferences in `finally` blocks. Each new width sample navigates away from
the old document before changing viewport size, so its header observer is not
being resized during navigation teardown.

The existing reaction suite verifies privacy-filtered member tips, real toggles,
pending-request exclusion, failure announcements and recovery, focus retention
and return, reduced motion, native submission and a focused accessibility scan.
The original drawer, reply containment and draft-preservation checks also pass.
Application-error checks remain strict, with only the parent evidence record's
exact WebKit screenshot-time CSP diagnostic classified. These are emulated
viewports; software-keyboard behavior and full-page enlarged-text reflow retain
the parent record's coverage boundaries.

## Screenshots

Captured from the passing WebKit run:

- [Desktop picker, light theme at 861 × 844](reaction-picker-light-enhanced.png)
- [Desktop picker, dark theme at 861 × 844](reaction-picker-dark-enhanced.png)
- [Guest reaction names at 390 × 844](reaction-counts-guest-mobile.png)
- [Suspended member reaction names at 390 × 844](reaction-counts-suspended-mobile.png)
- [Banned member reaction names at 390 × 844](reaction-counts-banned-mobile.png)

## Repeat

The regression spec is included in the regular browser-evidence command. A
dedicated command prepares a provisioned throwaway database and runs both focused
specs:

```bash
DB_DATABASE=retroboards_e2e_reactionfix_20261012 E2E_PORT=8058 \
  RATELIMIT_PATH=storage/ratelimit-e2e-reactionfix-20261012 \
  PACKAGES_STORAGE_PATH=storage/packages-e2e-reactionfix-20261012 \
  RB_EVIDENCE_DIR=/tmp/retroboards-reactionfix \
  npm --prefix tests/browser run evidence:mobile-controls
```

For the completed local runs, a temporary config inherited the repository's
Playwright config, suppressed PHP access logs and set both viewport projects to
the selected engine. `E2E_LAYOUT_BROWSER=webkit` selects WebKit for the controls
spec; applying WebKit to both projects in the config also runs the existing
reaction spec in that engine.

## Publication baseline

Before publishing, the existing production source
`d06a303ae2723d7da303d62069f79a877a3f1f77` was checked against its exact manifest:
all 62 allowlisted assets matched their SHA-256 hashes and GET/HEAD/304 cache
behavior. Public health, guest HTML/CSP, static 404 handling, direct-origin denial
and the exact service plus cron/monitor images passed. The automatic deployment
build was `942d50d7-a000-45b0-a0cd-3b55f76c077a`, with ready revision
`retroboards-00019-lvk` and active Worker version
`b0365d81-c285-4b35-b035-33097f3fb30b`.

That deployed asset version, `906231110793ea6f`, had not been pinned yet. It was
recorded in an isolated checkout before building the candidate, retaining its
CSS for older pages and its two predecessors, `aa3e487a59d3d205` and
`3642fd850eefb9c0`. Six exclusive files from the expired `d8529d5d0f5e0219` release
were retired by the builder. The candidate stays at version `2883bddb08c95fd1`
and publishes 57 assets, including the previous production CSS.

The legacy `boards.hperkins.blog` alias returned HTTP 403 from Google Frontend
before publication instead of the documented canonical redirect. This is kept
as a failed baseline check; it does not change the successful canonical-origin
asset and runtime checks above. The sanitized full baseline receipt is
[`production-baseline.json`](production-baseline.json).

## Production release

The fixes were committed and pushed to `main` as
`32fc59a0bca9b99a59654da1e45c45289f442e6e`. Both automatic deployment paths
succeeded: Workers Builds completed for that exact commit, and production Cloud
Build `f48d131c-3d6d-4844-b289-8266d1743a85` passed every source/build/push/deploy/job
step. Cloud Run revision `retroboards-00020-8pk` took 100% of traffic; the service
and all four cron jobs plus the monitor use the exact release image. The active
Worker version was `f64bd6eb-6f5b-4d95-83af-cf9aa220d0c1`.

All 57 allowlisted live assets matched the committed manifest, including the
retained previous-production CSS. GET/HEAD/304 cache behavior, current page asset
URLs, strict CSP headers, public database health, direct-origin denial and static
404 handling passed. [`production-deployment.json`](production-deployment.json)
records the sanitized read-only checks. Its overall result remains false because
the legacy-alias redirect check still fails, as it did before this release; the
canonical-origin asset and exact-runtime checks pass.

After those byte and deployment checks, `npm run assets:record-release --
2883bddb08c95fd1` records the released version with predecessors
`906231110793ea6f` and `aa3e487a59d3d205`. The two exclusive older editor files from
`3642fd850eefb9c0` are retired, leaving 55 published assets. Current source asset
URLs and hashes stay unchanged; the application-surface digest and generated
Imladris manifest are refreshed for the smaller retained-file tree.
