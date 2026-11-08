# Feature flags and runtime configuration verification

Base revision: `b1d2a591b3846fda586cd66ffd2346438de8286c`.

Final tested candidate: `5ad42010c0184b5296b59ff69a039c6e957f1edd`, branch
`codex/feature-flags-runtime-config`. Subsequent changes record verification
and documentation only. Implementation is local and unpublished.

The moderation form and audit now use the same effective anti-abuse mode as enforcement. The direct-service analysis probe is historical base-revision evidence; the HTTP tests and browser run below verify the implementation.

- Four new HTTP regressions failed before the change: [red summary](task-1-red.txt).
- The changed moderation tests and existing safeguards pass: 20 tests, 175 assertions ([PHPUnit log](task-1-phpunit.log)).
- Chromium passes four scenarios across 1280×800 and 390×844, with JavaScript enabled and disabled ([browser log](task-1-browser.log)). The private browser database was fully seeded in the default mode before the server was started with `ANTIABUSE_MODE=hold`.
- The browser checks the page title, URL, visible enforcement form, selected inherited `hold`, an ordinary save and its audit, native Block review/edit/confirm, and no console/page errors. The Browser plugin was unavailable; the repository Playwright workflow was used.

Captures for each viewport are under `desktop/` and `mobile/`: inherited Hold and confirmed Block, each with and without JavaScript. The mobile inherited Hold and final desktop confirmed Block captures without JavaScript were visually inspected. No CSS or generated asset inputs changed.

The isolated analysis probe uses real PDO SQLite in memory for settings SELECTs; it is not a MySQL write, HTTP or browser test. Its receipt names the base source hashes. Reproduce it against that base source tree with `php analysis-mode-probe.php /path/to/base/tree b1d2a591b3846fda586cd66ffd2346438de8286c`.

## Safe mode transaction

The MariaDB failure test first observed `theme_safe_mode = "1"` after the real audit foreign-key failure, instead of the previous absent setting ([red log](task-2-red.log)). After wrapping the setting and audit writes together, the test verifies both absent and disabled prior states are retained. It uses an independent real connection outside the harness transaction and restores that row in `finally`.

The theme package suite passes 29 tests and 283 assertions ([PHPUnit log](task-2-phpunit.log)), including one audit entry per successful transition, password-guarded exit, and continued environment-forced safe mode. PHP 8.5 reports the existing `ThemeAssetScanner.php:68` `imagedestroy()` deprecation, also present in the unchanged baseline. This task does not change that scanner.

## Manual override recipes

The rehearsal extracts the three PHP command bodies from the published runbook and executes the disable/restore recipes in child processes. It only inserts synchronization after the map read; the competing writer calls the real feature-console service. Its destructive setup is restricted to `DB_DATABASE=retroboards_flags_rehearsal`.

- The original recipe erased the console's `search=false` override. The baseline/current old-recipe run also exposed last-key `[]` storage and replacement of corrupt maps ([baseline receipt](runbook-lock-baseline.json)).
- The revised recipes retain both changes for absent and existing rows, preserve existing unknown/nested keys, restore one key alongside a concurrent console write, store `{}` after the last removal, refuse JSON null/list/scalar/out-of-range-number maps unchanged through their explicit guard, and retain deliberate manual enablement ([passing receipt](runbook-lock-rehearsal.json)).
- The receipt contains the exact runbook and command hashes. Reproduce with `DB_DATABASE=retroboards_flags_rehearsal php docs/evidence/feature-flags-runtime-config-2026-11-08/runbook-lock-rehearsal.php` against a dedicated local database with that name. It does not inspect production.

## Independent review and final corrections

The read-only branch review accepted the runtime resolver, atomic safe-mode write and independent-connection test. It identified a browser harness integration problem and a target-key encoding edge ([review disposition](independent-review.json)).

The special browser cases initially failed normal discovery when their environment was absent ([red summary](review-browser-red.txt)). Run them with `cd tests/browser && npm run evidence:runtime-config`: the runner prepares `retroboards_runtime_config_browser` in Observe mode and starts the test server with inherited Hold, then executes the four viewport/JavaScript cases. `RB_RUNTIME_CONFIG_DATABASE` may select another guarded browser database and `RB_RUNTIME_CONFIG_PORT` defaults to 8037. Normal harness discovery skips these special-environment cases ([default-discovery log](browser-default-discovery.log)); the dedicated runner passes all four ([runner log](task-1-browser.log)).

Both runbook commands now validate the original map before changing the selected key. The added `community:1e999` case failed for both commands before that correction ([red receipt](review-encoding-red.json)); all ten refusal cases now pass alongside the concurrency checks ([final receipt](runbook-lock-rehearsal.json)).

## Final application verification

The completed full PHP suite exits 0: **3,580 tests, 34,043 assertions**, eight
PHP 8.5 deprecations and one skipped test, in 6 minutes 1.785 seconds
([complete log](full-suite.log), [structured receipt and source hashes](verification.json)).
The invocation uses `COMPOSER_PROCESS_TIMEOUT=0 RB_TEST_FRESH=0 composer test -- --display-deprecations --display-skipped`
after a standalone fresh-schema bootstrap on `retroboards_flags_config_test`.

The deprecations are at unchanged source/test locations: reflection accessibility
and PDO TLS constants in `DatabaseTlsTest`/`Database`, `curl_close()` in
`CloudflareSmtpMailer`, and `imagedestroy()` in `ThemeAssetScanner`. The focused
unchanged baseline also reports one deprecation
([baseline log](baseline-focused.log)). No full-suite baseline run is claimed.

The skipped test is
`AppThreadIntelligenceMigrationTest::test_0077_down_and_up_rehearsal_on_fixture_free_schema`,
which requires its own dedicated fixture-free database. This branch changes no
migrations. Generated-asset and Worker inputs are also unchanged.

Earlier full-suite attempts were incomplete: Composer first reached its default
300-second timeout; then `RB_TEST_FRESH=1` propagated into a child race-test
bootstrap and blocked schema rebuilding on a parent transaction. Preparing the
schema once and using `RB_TEST_FRESH=0` resolved the invocation issue; the focused
race-test environment check passes 9 tests and 48 assertions
([log](race-environment-check.log)). No application or race-test code was changed
for those invocation corrections.

All execution rulings, costs and the independent-review boundary are retained
in [execution decisions](execution-decisions.md). No minor review findings or
implementation tasks remain deferred. The branch and managed worktree are
preserved for integration; no production publication or deployment verification
was requested or performed.
