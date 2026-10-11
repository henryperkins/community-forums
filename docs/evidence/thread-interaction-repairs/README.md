# Thread interaction repairs — 2026-10-07

The separate follow-up to the document-scroll change repairs Topic tools focus
cycling and an empty-dock click defect, and refreshes three stale Study-test
assumptions. The no-JavaScript test passes unchanged on a fresh private fixture.

Topic tools and Messages modal filters check every closed details ancestor.
A nested summary under closed Topic management is excluded from the cycle;
Shift+Tab from Close reaches the management summary and Tab wraps back to Close.
The [desktop capture](topic-tools-desktop.png) shows the rendered drawer.

In source mode, pressing Quote used to fold the empty dock on pointerdown.
The 255px dock shrank to 67px and document scrolling clamped by 188px. Pointerup
then landed on a different element and the quote handler never ran. The
[before-event record](quote-retargeting-before.json) confirms the target change
and zero insertions. Outside collapse now waits for a settled click target;
focusout waits during a pointer gesture, and drag/cancel release has a deferred
fallback. Keyboard-only departure still folds an empty dock.

The regression expands the dock before selecting Source, holds a mouse press
across the focusout task, and verifies exactly one quote plus editor focus on
release. Reaction expectations read closed-disclosure text via textContent and
check the generated CSS separator directly. Keyboard expectations check the
resting More control and the action siblings revealed by focus.

## Final verification

The browser fixture used `retroboards_e2e_thread_repairs_20261007`; PHPUnit used
its own `retroboards_e2e_thread_repairs_php_20261007`. Both are throwaway local
fixtures separate from the developer instance.

| Lane | Result |
|---|---|
| Full PHPUnit | 3,134 tests, 23,831 assertions, 1 skipped; runtime source hashes unchanged |
| Imladris | Assets current; 24 tests, 305 assertions |
| Generated assets | Build and consistency check passed |
| Worker tests | 24 passed |
| Chromium: Study, remediation, startup, composer expansion | 67 passed, 43 project-specific skips |
| Chromium: Messages modal focus | 1 passed, including desktop/narrow/short/no-JS variants |
| WebKit: five previously failing Study cases | 5 passed |
| Syntax and diff checks | Both JavaScript files valid; clean whitespace checks |

The PHPUnit skip is the existing migration down/up rehearsal reserved for its
special fixture-free database; no migrations changed. The affected browser
suites include both desktop and mobile, themes, native forms, focus, startup and
composer accessibility. WebKit verification uses the installed 2359 runtime;
these runs do not establish physical-device/software-keyboard coverage. The
[native reply capture](reply-no-js-webkit.png) is from the passing WebKit case.

The full repository browser suite was not run. The old no-JS timeout did not
reproduce, and its old artifacts do not identify the stalled action. No timeout
was loosened. All five cases pass in Chromium and WebKit; the wider affected
Chromium suites also pass. Raw logs and traces remain local under
`output/playwright/thread-repairs/`; generated screenshot baselines were restored
once the current captures were saved. [Verification JSON](verification.json)
records final counts, runtime URLs and hashes.
