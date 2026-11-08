# Execution decisions and boundaries

Plan: [Feature flags and runtime configuration](../../superpowers/plans/2026-11-08-feature-flags-runtime-configuration.md).
Base: `b1d2a591b3846fda586cd66ffd2346438de8286c`.

These are all the rulings recorded during execution, in their original order.

1. **Use focused baseline checks and one completed final full PHP suite.**
   The repository's ADR 0051 release contract takes precedence over the skill's
   repeated broad-suite instructions. Failed or incomplete invocations may be
   repeated. Cost if wrong: unrelated baseline failures are discovered later;
   they must be reported rather than silently attributed to this change.
2. **Implement in a managed isolated worktree and preserve the dirty main checkout.**
   This keeps unrelated edits outside the feature branch. Cost if wrong: the
   completed branch still needs a separate integration step.
3. **Prove safe-mode rollback through an independent real Database connection.**
   This replaces the planned `withoutHarnessTransaction()` invocation, avoiding
   a commit of the test harness's administrator fixtures. The test asserts the
   actual foreign-key error, transaction closure and persisted setting, then
   restores the raw row in `finally`. Cost if wrong: a driver-specific
   connection interaction could require a separate rehearsal; the recorded
   observation applies to the tested MariaDB environment.
4. **Validate encoding before either manual map mutation.**
   This completes Task 3's explicit refusal/separate-repair contract, including
   an invalid number on the selected key. Cost if wrong: an operator must
   explicitly repair a value that overwriting the selected key could otherwise
   remove.
5. **Run inherited-Hold browser scenarios through a dedicated command.**
   Normal discovery skips the special-environment cases; the dedicated runner
   seeds a private database in Observe mode and starts the application in Hold.
   The HTTP regressions remain in the ordinary suite. Cost if wrong: the four
   browser scenarios are exercised only by `npm run evidence:runtime-config`.
6. **Keep publication outside the accepted implementation plan.**
   The work makes no production behavior or health claim. Cost if wrong: a
   later publication still requires the release/deployment checks in ADR 0051.
7. **Retain the unchanged PHP 8.5 scanner deprecation.**
   The focused baseline also reports `ThemeAssetScanner.php:68` calling
   `imagedestroy()`. Changing that scanner is outside this plan. Cost if wrong:
   the notice remains on PHP 8.5; the verification report records it explicitly.

## Review disposition

One independent reviewer inspected `b1d2a591..b1fd7ce6`. The browser harness
finding and original-map encoding edge were reproduced and corrected in
`5ad42010c0184b5296b59ff69a039c6e957f1edd`; their red/green evidence is linked
from [the review receipt](independent-review.json). No second review was
dispatched. No minor review findings remain deferred.

## Full-suite invocation corrections

The first full-suite invocation exceeded Composer's default 300-second process
timeout. The next invocation inherited `RB_TEST_FRESH=1` into a race test's
child bootstrap: MariaDB reported the child's `DROP TABLE users` waiting on a
metadata lock while the parent held the account transaction. Only those two
private-worktree processes were stopped. Neither application code nor the
race test was changed in response.

The final invocation prepares the private schema once in a standalone
bootstrap, then uses `COMPOSER_PROCESS_TIMEOUT=0 RB_TEST_FRESH=0 composer test -- --display-deprecations --display-skipped`. The focused race-test check
under that environment passes 9 tests and 48 assertions. The complete-suite
result and source hashes are recorded separately in `verification.json`.
