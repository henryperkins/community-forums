# Feature flags and runtime configuration verification

Base revision: `b1d2a591b3846fda586cd66ffd2346438de8286c`.

The moderation form and audit now use the same effective anti-abuse mode as enforcement. The direct-service analysis probe is historical base-revision evidence; the HTTP tests and browser run below verify the implementation.

- Four new HTTP regressions failed before the change: [red summary](task-1-red.txt).
- The changed moderation tests and existing safeguards pass: 20 tests, 175 assertions ([PHPUnit log](task-1-phpunit.log)).
- Chromium passes four scenarios across 1280×800 and 390×844, with JavaScript enabled and disabled ([browser log](task-1-browser.log)). The private browser database was fully seeded in the default mode before the server was started with `ANTIABUSE_MODE=hold`.
- The browser checks the page title, URL, visible enforcement form, selected inherited `hold`, an ordinary save and its audit, native Block review/edit/confirm, and no console/page errors. The Browser plugin was unavailable; the repository Playwright workflow was used.

Captures for each viewport are under `desktop/` and `mobile/`: inherited Hold and confirmed Block, each with and without JavaScript. The mobile inherited Hold capture was visually inspected. No CSS or generated asset inputs changed.

The isolated analysis probe uses real PDO SQLite in memory for settings SELECTs; it is not a MySQL write, HTTP or browser test. Its receipt names the base source hashes. Reproduce it against that base source tree with `php analysis-mode-probe.php /path/to/base/tree b1d2a591b3846fda586cd66ffd2346438de8286c`.

## Safe mode transaction

The MariaDB failure test first observed `theme_safe_mode = "1"` after the real audit foreign-key failure, instead of the previous absent setting ([red log](task-2-red.log)). After wrapping the setting and audit writes together, the test verifies both absent and disabled prior states are retained. It uses an independent real connection outside the harness transaction and restores that row in `finally`.

The theme package suite passes 29 tests and 283 assertions ([PHPUnit log](task-2-phpunit.log)), including one audit entry per successful transition, password-guarded exit, and continued environment-forced safe mode. PHP 8.5 reports the existing `ThemeAssetScanner.php:68` `imagedestroy()` deprecation, also present in the unchanged baseline. This task does not change that scanner.
