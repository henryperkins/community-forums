# Registry blocklist layout — 2026-11-08

> Historical layout proof: the results and tested patch below belong to the
> original `a308e1b1` candidate on `eeceed78`. The delivered PR now stacks on
> retention PR #96. Its updated manifest, fresh checks and credential response
> are recorded in [the retention follow-up](../registry-blocklist-retention-2026-11-08/README.md).
> The original captures and layout regression remain preserved. The runner
> below has been sanitized to require runtime credentials; it is not a copy of
> the original embedded development credential.

Option 3 corrects the independent mobile blocklist layout defect reported in
the admin/settings review. It does not implement options 4–5 or depend on the
keyring, email-resubscription or registry form-error branches.

## Revision and exact content

- Tested application base: `eeceed780aa9ec92e2a85e9554020a6c6d85d503`.
- Branch/worktree: `fix/registry-blocklist-mobile`,
  `/home/azureuser/rb-wt-registry-blocklist`.
- [starting-state.json](starting-state.json) records the clean base and the
  untouched keyring (`b62a12a7`) and registry-error (`ef3fe102`) worktrees.
- [tested-files.json](tested-files.json) records every changed source, test and
  generated asset, including the removed previous candidate CSS file.
- [tested.patch.gz](tested.patch.gz) is the exact binary Git diff for those
  files. Uncompressed SHA-256:
  `f43ebac1adf538d15c45512e1c3636e772ae5803b298506872792e086c0fc54d`.

Tests ran before the original commit. That commit's Git blobs and its diff
against the base were checked against this manifest and patch before push.
Documentation and evidence additions do not change the tested application.
At that original revision the older deployed asset release pins were preserved,
which omitted the live release's retention correction. This is superseded by
the PR #96 dependency and fresh asset-closure proof in the follow-up. No layout
release has been recorded as deployed.

## Observed defect and correction

The earlier review covered `b958596c` plus a preserved working-tree snapshot.
That snapshot was not restored. Current HEAD and the affected markup/styles
were inspected afresh on main before implementing this correction.

On the unchanged base, a native registry console containing
`acme/legacy-widget` gave its `.packages-rule-main` **0px** at both 320px and
390px. The action form occupied the row, forcing the identifier to wrap one
character per line. The long unbroken fixture reason also expanded document
width to 1085px. This conflicts with ADMIN §9.4's responsive console and the
Forum design system's readable identifiers, 320px reflow and usable controls.

The template now marks only local blocklist rows with `packages-block-row`.
The Forum source lets that row wrap, gives its text a flexible 18rem basis,
wraps long text, limits the form/input to available width and gives its
password input a 44px minimum height. The action moves below the identity when
the component cannot fit both. At desktop widths it remains beside the text.

The shared `packages-rule-row`, `packages-rule-main` and
`packages-revoke-form` definitions are unchanged. Their consumers were checked
in package detail, publisher, plan, consent, security and integration templates,
plus registry advisories and key revocation. The new selector matches only the
blocklist markup. No controller, service, authorization, CSRF, reauthentication,
target validation or escaping change is included.

## Regression evidence

[baseline/](baseline/) preserves the exact initial fixture/test sources and
the failing captures/measurements. The first two failures established the
0px text area. The overflow assertion was then tightened to compare against
the layout viewport's `clientWidth`, rather than mobile `innerWidth`, which
can itself expand with overflow. The repeated two-case baseline failed at
1085px versus 320px/390px. Both logs are retained. After this red run, only
the screenshot framing changed in the test; its assertions stayed unchanged.

The final browser test creates three real blocks: a normal package identity,
a long package identity with an unbroken reason, and a digest-only block with
escaped angle brackets. It exercises 320px, 390px and 1280px in both themes
and with JavaScript enabled/disabled. The narrow contexts enable mobile/touch
emulation; all twelve cases are intentionally scheduled under one Playwright
project (`desktop`) to avoid duplicate cases.

Each journey verifies:

- Exact identifier/reason text, readable text width, a single line for the
  normal identifier, bounded long text and no horizontal document overflow.
- Password/remove controls within the row, 44px targets, a useful input width,
  text above actions on narrow screens and side-by-side desktop layout.
- Keyboard Tab from password to Remove and a real native POST with CSRF.
- Wrong-password 422, empty returned password and unchanged block/audit state.
- Correct-password 303, only the submitted block removed, remaining blocks
  unchanged and exactly one new unblock audit entry.

Final geometry for the normal package identity:

| CSS viewport | Text width | Identifier lines | Document width |
| --- | ---: | ---: | ---: |
| 320px | 254px | 1 | 320px |
| 390px | 324px | 1 | 390px |
| 1280px | 713.875px | 1 | 1270px (scrollbar) |

[browser/](browser/) contains 24 full-page captures and twelve geometry records.
The full-page framing avoids a sticky header obscuring the middle of a tall
element screenshot. The 320px no-JS light and 1280px JS dark captures were also
visually inspected.

## Commands and results

Actual runner: `/tmp/rb-blocklist-layout-20261108-marr39v9/run-env.py`;
the current, sanitized version is [logs/run-env.py](logs/run-env.py). It starts with only PATH,
HOME and LANG, supplies test-only keys, an array mailer with no From address,
private databases/stores and localhost URLs. No `.env` or live credentials were
copied into the worktree. Existing installed dependencies were reused. The
original runner reused the repository's shared development DB default; that
was not generated solely for this task. The replacement requires
`RB_BLOCKLIST_DB_USERNAME` and `RB_BLOCKLIST_DB_PASSWORD_FILE`, reads the
password from a private runtime file, and validates both disposable database
names before running a child. The credential response is in the follow-up.

Application commands ran from the worktree. Browser commands ran from its
`tests/browser` directory. Every command below prefixed with `runner` used
`python3 /tmp/rb-blocklist-layout-20261108-marr39v9/run-env.py`.

| Check | Actual command | Result |
| --- | --- | --- |
| Existing registry baseline | `runner php vendor/bin/phpunit tests/Integration/Core/AppRegistryAdminTest.php --colors=never` | 5 tests / 27 assertions passed |
| Red browser baseline | `runner node node_modules/@playwright/test/cli.js test registry-blocklist-layout.spec.ts --project=desktop --grep '(320\|390)px no-js light' --reporter=line` | 2 expected failures before the fix |
| Focused PHP | `runner php vendor/bin/phpunit tests/Integration/Core/AppRegistryAdminTest.php tests/Integration/Security/PackageSecurityGateTest.php tests/Integration/Repository/RegistryRepositoriesTest.php tests/Unit/Core/ForumRuntimeAssetTest.php tests/Integration/Core/AppForumRuntimeTest.php --colors=never` | 34 tests / 205 assertions passed |
| Final browser | `runner node node_modules/@playwright/test/cli.js test registry-blocklist-layout.spec.ts --project=desktop --reporter=line` | 12 passed, 0 skipped (12.9s) |
| Forum generation | `composer build:forum`, `composer check:forum` | Both exit 0; generated CSS current |
| Asset generation | `npm run build`, `npm run check:assets` | Both exit 0; manifest and generated assets current |
| Worker/assets | `npm run test:assets` | 35 passed, 0 skipped |
| Full PHP | `runner composer test -- --colors=never --display-deprecations --display-skipped` | Exit 0; 3,576 tests / 33,934 assertions in 6:15.921; 8 existing deprecations, 1 guarded skip |
| Fixture guard | `env -i PATH=/usr/bin:/bin APP_ENV=test DB_DATABASE=ordinary_database php tests/browser/registry-blocklist-layout-fixture.php` | Expected exit 255 before autoload/database access |
| Patch whitespace | `git diff --check`, staged equivalent | Passed |

The [full log](logs/full-php.log) and [results.json](results.json) record this
run, rather than reusing a prior audit's counts. `COMPOSER_PROCESS_TIMEOUT=0`
is explicit in the runner; it does not bypass PHPUnit checks. No assertion
failures or unresolved browser skips remain.

The eight existing PHP 8.5 deprecation sites are unchanged:

1. `tests/Unit/Core/DatabaseTlsTest.php:80` — `ReflectionMethod::setAccessible()`.
2. `tests/Unit/Core/DatabaseTlsTest.php:38` — `PDO::MYSQL_ATTR_SSL_CA`.
3. `tests/Unit/Core/DatabaseTlsTest.php:39` — `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT`.
4. `src/Core/Database.php:145` — `PDO::MYSQL_ATTR_SSL_CA`.
5. `src/Core/Database.php:151` — `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT`.
6. `tests/Unit/Core/DatabaseTlsTest.php:71` — `ReflectionMethod::setAccessible()`.
7. `src/Mail/CloudflareSmtpMailer.php:110` — `curl_close()`.
8. `src/Service/Packages/ThemeAssetScanner.php:68` — `imagedestroy()`.

The guarded skip is
`AppThreadIntelligenceMigrationTest::test_0077_down_and_up_rehearsal_on_fixture_free_schema`.
Recorded reason: **direct down/up rehearsal runs only on its dedicated
throwaway database**. Its recorded command is
`DB_TEST_DATABASE=retroboards_thread_intelligence_clean vendor/bin/phpunit tests/Integration/Core/AppThreadIntelligenceMigrationTest.php --filter test_0077_down_and_up_rehearsal_on_fixture_free_schema`.
Migration 0077 is unchanged; the specialized destructive rehearsal was not run.

Text logs have only trailing whitespace normalized for Git checks. Original
output hashes are in [logs/raw-output-sha256.json](logs/raw-output-sha256.json).

## Reproduction and resource ownership

The fixture and test refuse an ordinary database: `APP_ENV=test` and a name
matching `retroboards_e2e_blocklist_layout(?:_[a-z0-9]+)*` are required.
To reproduce, provision fresh databases for that browser name and a private
`DB_TEST_DATABASE`, copy the runner into a scratch directory, and provide a
generated local account through `RB_BLOCKLIST_DB_USERNAME` and a mode-0600
`RB_BLOCKLIST_DB_PASSWORD_FILE`. Use `RB_BLOCKLIST_BROWSER_DB` and
`RB_BLOCKLIST_TEST_DB` for alternative names with the required prefixes. Do not
source a live environment file or put a password into the runner or evidence.
Run migrations through that runner, start the built-in PHP server with it,
then run the listed browser command. The browser fixture resets only the
dedicated database. PHP bootstrap may rebuild only its separate test schema.

Task-created resources were the private server on 127.0.0.1:8032, databases
`retroboards_e2e_blocklist_layout_20261108` and
`retroboards_test_blocklist_layout_20261108`, and private runtime stores.
[cleanup.json](cleanup.json) records their final disposition. The pre-existing
MariaDB container, databases and other worktrees are preserved. Raw scratch
logs remain at `/tmp/rb-blocklist-layout-20261108-marr39v9`; the independent
worktree is retained for review.

## Limits

This is local Chromium and PHP evidence. It does not claim physical-device,
Firefox, Safari or manual assistive-technology validation, application-wide
accessibility conformance, publication or deployment. The registry form-error
correction in PR #99 is independent and is not included in this tested base.
No production change, live email/identity/registry provider execution, merge or
deployment was performed. Options 4–5 remain separate.
