# Settings subscription-action polish

Owner-requested `impeccable polish`, starting from `9178f170`. The six earlier
audit repairs are already on `main`; this pass does not reapply them.

## Scope

- Shorten the per-subscription button's visible label to **Save**. At 320px with
  doubled root text, the former label split its final word and left a lone `n`.
- Retain `Save subscription for <target>` as the accessible name, including the
  neutral unavailable-target label. Native POST, CSRF, delivery preferences and
  opt-out behavior are unchanged.
- Pin the concise label, target context, actual single-line text fit and 44px
  target. Keep the full-width editing-form/select and stacked opt-out checks.
- No CSS, fonts, tokens, layout, motion, dependencies or generated assets change.
  The shorter button keeps its natural width rather than acquiring a new style.

## Initial polish verification

- Focused PHP: **4 tests, 23 assertions passed**;
  [log](phpunit-focused.log). Available and unavailable subscriptions both keep
  the short visible label and their privacy-safe accessible names.
- Three applicable browser cases have passing evidence: the updated readability
  case passed in [confirmation](browser-confirmation.log), and native Save plus
  opt-out passed in both [desktop](browser-native-desktop.log) and
  [mobile](browser-native-mobile.log) projects. These are composed results, not
  a claim that a final combined three-case run was repeated.
- The first [subscription run](browser-subscription.log) failed its old
  full-width-button assertion. The new label produced a readable 96.78px-wide
  button, but the assertion still required 90% of the 184px form. The sole
  correction batch replaces that incidental width assumption with actual text
  fit plus a 44px target. Form/select width and action stacking remain asserted;
  the final label must now occupy exactly one rendered line rather than up to
  three. The original failed run and artifacts remain intact.
- The confirmation covers both themes at 320px with a 32px root, desktop
  1440px, phone 390px, and 640px landscape. The explicit matrix runs in desktop
  Chromium; the separate native journey also runs in the touch-emulated mobile
  project. Each uses server-rendered forms with scripting disabled.
- PHP lint for both changed PHP files and scoped `git diff --check`: passed.
- [Asset freshness](assets-check.log): passed; version `d558418fe08d412c` is
  unchanged. No delivery build or release recording was performed.

## Visual confirmation

Reviewed the saved [desktop light](confirmation/desktop/subscription-desktop-light.png),
[phone twilight](confirmation/desktop/subscription-phone-dark.png), and
[doubled-text twilight](confirmation/desktop/subscription-320-double-dark.png)
captures. The existing Imladris register, action order and pane reflow remain.

The [enlarged-text metrics](confirmation/desktop/subscription-320-double-dark.json)
record a 184px form/select and a 96.78 × 56px Save button, with one text line at
the unchanged 28.8px control type size. The opt-out form follows below it.

## Initial full-suite result (before the publication request)

[Full PHPUnit](phpunit.log), with [JUnit identities](phpunit.xml): **3,353 tests,
29,606 assertions, 13 failures, 2 warnings, 1 skipped**, with zero errors.
The suite is **not green**.

Compared with the completed pre-polish run, the same twelve documented Windows
deployment/browser-harness failures remain. The only additional failing identity
is `ImladrisRuntimeAssetTest::test_checked_in_runtime_asset_matches_the_allowlisted_design_system_sources`:
the template edit intentionally makes the reviewed presentation digest stale.
`config/imladris-runtime-baseline.json` was not updated merely to pass the check.
That old-base publication requirement was subsequently superseded by incoming
ADR 0051, as recorded below; no baseline was restored or reconciled for this fix.
The warnings remain denied symlink creation and a failed process launch. The
skip remains the fixture-free migration down/up rehearsal.

## Isolation and limits

PHP used `retroboards_test_settings_20261025_7e31`; browser fixtures used
`retroboards_unified_e2e_settings_20261025_7e31` at `http://localhost:8057`.
Each browser invocation used a separate temporary rate-limit store. Earlier
user-owned scratch files and publication evidence were left intact.

No new axe, physical-device, real browser-zoom or external-provider verification
is claimed by these focused tests. Earlier accessibility evidence remains
separate. The polish-only pass stopped after confirmation, without committing,
pushing or deploying. The owner's subsequent publication request is recorded below.

## Publication qualification

On the request to commit and push, fetched `origin/main` and fast-forwarded the
17 incoming commits from `9178f170` through
`960f7f0f27b15ca7def0e815ce19912077e9d249`. The three local polish files had no
upstream overlap. Unrelated untracked files were preserved. ADR 0051 removes
the application-wide presentation baseline and requires bounded relevant checks,
not the optional broad hosted browser matrix.

- Installed the incoming root and browser lockfiles with `npm ci`; application
  Vite is now **8.3.5**. No dependency changes belong to this polish commit.
- [Asset tests](publication/assets-tests.log): **35 passed**.
  [Asset freshness confirmation](publication/assets-check-confirmation.log):
  passed, with upstream version **`3aa554af4ef3f85a`**, all 58 delivery assets
  and existing deployed-release pins unchanged. The initial check found only
  `config/assets.json` stale because Git had checked it out with CRLF. Running
  the supported build reproduced every asset and the same Git-normalized
  manifest blob; this is not an asset release or retention update.
- [Combined focused browser run](publication/browser.log): **3 passed, 1 declared
  skip**, zero retries. The desktop project drives the explicit viewport/text
  matrix; its mobile duplicate is intentionally skipped. Native Save/opt-out
  journeys pass on both desktop and touch-emulated mobile, without JavaScript.
  Fresh screenshots and metrics are under `publication/browser/`. No UI changes
  were made after the original polish confirmation.
- [Completed full PHPUnit](publication/phpunit-complete.log), with
  [JUnit](publication/phpunit-complete.xml): **3,361 tests, 29,709 assertions,
  11 failures, 2 warnings, 1 skip**, zero errors. The suite is **not green**.
  All failures are in the existing Cloud Run deployment/browser-CI contracts:
  Windows path separators and shell/process behavior, unavailable Unix upload
  flags, denied symlinks and CRLF-sensitive deployment parsing. The four settings
  copy tests pass with 23 assertions, and the Imladris runtime checks pass.
- Two earlier qualification attempts remain recorded: [bootstrap denial](publication/phpunit.log)
  for a newly named database without grants, then [default-memory exhaustion](publication/phpunit-final.log).
  The completed run reused the existing private settings test database and ran
  the `composer test` PHPUnit target directly with `php -d memory_limit=1G`.

Only the notification label, its PHP/browser regressions, changelog and scoped
evidence are intended for publication. No migrations, settings writes against
production, CSS, Worker or generated-asset changes are included. Exact automatic
deployment receipts will be stored outside the repository and linked in the
publication handoff, avoiding another proof-only production deployment (ADR 0051).
