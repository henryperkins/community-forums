# Settings source publication review

The owner requested review, commit and push of all current source changes on
`main`, based on `85094d1f8f2c93cad4ea926ed92de6fedb6b20f1`.

The reviewed scope includes the settings hardening, layout, copy/type and six
audit fixes; their PHP/browser regressions; the shared settings introduction;
the locked asset build; and the associated design documentation and historical
evidence. It also includes the optional `hello.ts` / `neon.ts` starter and its
dependencies, plus a Cloud Run service snapshot now explicitly labeled as
historical. That snapshot is not the automatic deployment configuration.

Local agent configuration, removed prompt files, bundled HTML design exports,
unrelated profile screenshots, scratch output and test storage remain local.
Application source outside the reviewed changes was not reset or cleaned.

## Review and corrections

- Native forms retain CSRF, validation drafts, permission gates and private
  subscription masking. Settings presentation uses the existing Imladris
  tokens. No application service, controller or migration changes are needed.
- All six supplied audit findings have regression coverage: narrow subscription
  controls, Security boundaries, account-row reflow, heading wrapping, tour
  enhancement availability and password-aware Connections copy.
- Authentication tests find the labeled authenticator secret rather than
  requiring the exact former CSS class string.
- The mail-worker browser test invokes PHP directly on native Windows; Linux
  keeps its existing `flock` wrapper. Fixtures require a disposable database
  and Playwright uses one worker.
- Axe cannot reliably run inside the script-disabled workflow context. Native
  accessibility checks now audit a snapshot of the rendered DOM in a separate
  script-enabled context with application scripts blocked. The actual forms,
  native navigation and POST validation history remain script-disabled.
- The Cloud Run snapshot's image/revision comments identify its 2026-10-11
  origin. No cloud configuration is applied by this review.

## Verification

The asset version is `d558418fe08d412c`, built with the locked Vite 8.3.1.

| Check | Result |
| --- | --- |
| Asset build and freshness | Passed; [build](build.log), [freshness](assets-check.log) |
| Worker and asset tests | 35 passed, 0 failed; [log](assets-tests.log) |
| PHP syntax / JS syntax | 34 changed PHP files and `tour.js` passed |
| Neon TypeScript | `tsc --noEmit --target es2022 --module nodenext --moduleResolution nodenext --skipLibCheck hello.ts neon.ts` passed; no Neon deployment tested |
| Imladris generated assets | Passed after publication reconciliation; design mirror baseline unchanged |
| Full local PHPUnit | 3,353 tests, 29,614 assertions, 12 failures, 2 warnings, 1 skipped; [log](phpunit.log), [JUnit](phpunit.xml) |
| Browser evidence | 119 unique applicable cases have passing evidence across the initial and corrective runs; 23 explicit viewport/project skips |

The complete browser run was **108 passed, 11 failed, 23 skipped**, in 9.5
minutes: [log](browser.log). Five failures were the two Windows `flock` calls
and three mobile HTTP 429 responses caused by sharing a rate-limit store with
the preceding desktop runs. Separate viewport stores and the Windows command
repair passed all four selected account cases on each viewport. Those same
confirmation runs retained three native Axe context failures per viewport:
[desktop](browser-confirmation-desktop.log),
[mobile](browser-confirmation-mobile.log).

The final native-DOM audit repair passed the three affected cases plus the
existing system-dark/forced-colors case on each viewport:
[desktop, 4 passed](browser-a11y-final-desktop.log),
[mobile, 4 passed](browser-a11y-final-mobile.log). The combined 142-case run was
not repeated after these test-only changes. Earlier failed logs remain intact;
the 119-case statement is composed evidence, not a single all-green run.

The 12 remaining PHPUnit failures are unchanged Windows deployment-harness
contracts: `CloudRunBrowserCiContractTest` (6),
`CloudRunBrowserCiPackageContractTest` (1),
`CloudRunBrowserCiUploaderContractTest` (1), and
`CloudRunDeploymentContractTest` (4). They involve Bash/Windows path handling,
archive diagnostics, unavailable `os.O_NOFOLLOW`, CRLF values, denied symlink
creation and a failed subprocess launch. The full suite is **not green**.
The prior authentication-parser and application-baseline failures are resolved.

Browser coverage includes native settings destinations, saved-feed/folder
management, disclosure error recovery, privacy masking, subscriptions, lifecycle
restrictions, session revocation, TOTP, accessibility, theme/forced-color states,
320px enlarged text, and tour script/fetch failure. This is local Chromium
evidence, not physical-device or external identity-provider verification.

## Isolation and publication

Tests used `retroboards_test_publish_20261022` and
`retroboards_unified_e2e_publish_20261022`, with browser origin
`http://localhost:8053`. Only disposable local accounts were used. Each corrected
viewport run has a separate rate-limit store under the Windows temporary
directory. The Playwright-owned server exits with each run.

The source commit is followed immediately by the merger's runtime-baseline
reconciliation commit on `main`, per ADR 0024. Automatic Worker and Cloud Run
deployment status and public asset hashes are checked after Git publication;
local tests alone do not establish production deployment.
