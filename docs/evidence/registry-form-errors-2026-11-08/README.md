# Registry form errors and retained drafts

Date: 2026-11-08 UTC. Branch: `fix/registry-form-error-context`.

## Exact revision and independence

The implementation starts from current `origin/main` at
`eeceed780aa9ec92e2a85e9554020a6c6d85d503`, in the separate worktree
`/home/azureuser/rb-wt-registry-form-errors`. The original workspace remains on
`feat/app-key-keyring` and the accepted resubscription commit
`b62a12a7d15c5480259bbaf4cb9d4d131d9bac90`.

Before editing, the registry controller/template, helpers and trust service were
hashed on both branches and were identical ([starting-state.json](starting-state.json)).
There is no dependency on the key ring, resubscription route, APP_KEY attribution
migration or PR #98. This registry correction targets main independently.

The exact source/test patch is compressed in [tested.patch.gz](tested.patch.gz).
Its uncompressed SHA-256 and individual file digests are in
[tested-files.json](tested-files.json). Those five source/test files are the
candidate exercised by the checks below; documentation and evidence accompany
them in the delivery commit.

## Behavior

`AdminRegistryController::formFailure()` gets a literal action name from the
matched controller action and a target ID from its route parameters. The
presentation context is action plus target, including the key row ID for
revocation. It does not read a context or target identifier from submitted fields
and does not grant authority. Existing service and controller authorization,
feature availability, CSRF, target validation and reauthentication paths remain
in place.

Only the submitted action's allowed nonsecret fields are retained. Passwords,
extra secret inputs and unrelated POST fields do not enter the view model.
Rotation/advisory service errors are associated with their envelope input;
pin dates/public key and key revocation reasons retain their own draft. The same
state mechanism isolates creation, enabling, block/removal and acknowledgement
forms so those actions cannot receive the trust forms' errors either.

The template uses existing `field_attrs()` and `field_error()` helpers with
unique action/target/field IDs. The failed disclosure reopens even when the error
is solely the password; only the originating input is marked invalid and focused.
Revoke forms already live in their key rows rather than disclosures, and stay
there with their own reason/error. A missing or no-longer-rendered target yields
one page alert instead of placing the draft in another form.

Retained values and errors remain escaped. Rotation/advisory textarea rendering
includes the structural first newline that browsers consume, preserving an
operator's actual leading newline. No CSS, client runtime JavaScript, registry
business rule, network fetch or provider behavior changed. The unrelated mobile
blocklist layout (option 3) is outside this correction.

## Failing baseline and corrected checks

On the unchanged application at `eeceed780aa9ec92e2a85e9554020a6c6d85d503`:

- Existing trust-console tests passed: [existing-baseline.txt](logs/existing-baseline.txt),
  **5 tests, 27 assertions**.
- The new [regression file](../../../tests/Integration/Core/AppRegistryFormErrorsTest.php)
  failed five targeted cases: [red-baseline.txt](logs/red-baseline.txt). Wrong
  passwords closed pin/rotation/advisory disclosures; revoke lost its reason;
  malformed rotation JSON lacked a field association. The fixture uses two
  registries and multiple keys in the same registry, so card-only scoping cannot
  satisfy the assertions. The exact initial fixture is retained in
  [baseline-regression-test.php.txt](logs/baseline-regression-test.php.txt).
  The final regression file expands coverage to the adjacent create/enable/block
  forms, verifier failures, unexpected secrets and leading newlines; running its
  broader filter on the baseline will therefore execute additional cases.

Commands from the worktree root:

```bash
python3 "$scratch/run-env.py" php vendor/bin/phpunit tests/Integration/Core/AppRegistryAdminTest.php --colors=never
python3 "$scratch/run-env.py" php vendor/bin/phpunit tests/Integration/Core/AppRegistryFormErrorsTest.php --filter 'test_wrong_password|test_malformed_rotation' --colors=never
```

Corrected targeted verification:

- [Focused PHP](logs/focused-final.txt): **32 tests, 787 assertions passed**, from
  `AppRegistryFormErrorsTest`, `AppRegistryAdminTest`, `RegistryTrustServiceTest`
  and `RegistryAdvisoryServiceTest`.
- [Browser journey](logs/browser.txt): **4 passed, 0 skipped**. Every journey runs
  six refused native submissions: wrong-password pin/rotation/advisory/revoke,
  malformed rotation JSON and malformed advisory JSON. The matrix is desktop
  1280×800 / mobile 390×844 with JavaScript on/off. All compare the registry/key/
  advisory/block/audit state hash before and after each failed request.
- The first browser pass exposed leading-newline loss in a textarea despite the
  already corrected form context ([browser-first.txt](logs/browser-first.txt)).
  It was fixed in the candidate above and the entire four-mode journey passed.
- [Full PHP suite](logs/full-phpunit.txt): **exit 0, 3,589 tests, 34,621 assertions**,
  completed in 05:58.412. No failures/errors; eight existing PHP 8.5
  deprecations and one guarded Thread Intelligence migration skip remain.
- `git diff --check` passed for the application/test candidate. No generated-asset
  or Worker inputs changed, so asset builds/Worker checks were not required.

PHP coverage verifies exact nonsecret drafts, retained pin dates, same-card action
isolation, same-card key isolation, other-registry isolation, empty password fields,
absence of unexpected posted secrets, unique error IDs, correct `aria-describedby`,
one autofocus target, output escaping, nonexistent targets, CSRF and member denial,
forged context identifiers, service verification failures and unchanged registry state.
Existing success-path trust tests also run.

The [browser source](../../../tests/browser/registry-form-errors.spec.ts) verifies
actual 422 POST responses, reopened disclosures, exact JSON (including leading and
trailing newlines), focus, error visibility/association, blank passwords, no parsed
injected markup, no errors or submitted values in any other form, and no mutation.
Its [fixture](../../../tests/browser/registry-form-errors-fixture.php) refuses any
database outside its dedicated test prefix. It creates two local disabled registry
sources with public fixture keys and does not fetch their example.test URLs.

Representative screenshots (24 captures cover all six cases in all four modes):

| Case | Desktop, no JavaScript | 390px, no JavaScript |
|---|---|---|
| Pin password error | [Pin](browser/desktop/no-js/pin-password.png) | [Pin](browser/mobile/no-js/pin-password.png) |
| Rotation draft and password | [Rotation](browser/desktop/no-js/rotation-password.png) | [Rotation](browser/mobile/no-js/rotation-password.png) |
| Advisory password error | [Advisory](browser/desktop/no-js/advisory-password.png) | [Advisory](browser/mobile/no-js/advisory-password.png) |
| Key-specific revoke reason | [Revoke](browser/desktop/no-js/revoke-password.png) | [Revoke](browser/mobile/no-js/revoke-password.png) |
| Malformed rotation JSON | [JSON](browser/desktop/no-js/rotation-malformed.png) | [JSON](browser/mobile/no-js/rotation-malformed.png) |

## Reproduce

The existing installed dependencies were used without package changes: PHP 8.5.4,
PHPUnit 11.5.56, Node 22.22.1 and Playwright 1.61.1 / bundled Chromium. PHP dependencies
were copied into the isolated worktree; browser dependencies are linked within an
ignored real node_modules directory. No application `.env` was copied.

The [runner](logs/run-env.py) constructs a clean process environment with local
fixture database settings, `APP_ENV=test`, dummy APP_KEY and AI credentials,
`MAIL_DRIVER=array`, local HTTP session settings and isolated filesystem stores.
It inherits only PATH/HOME/LANG and sets `COMPOSER_PROCESS_TIMEOUT=0` for the full
suite's known runtime. It is recorded unchanged from the actual invocations.

Copy it to a new scratch directory before using it, to keep runtime files outside
the evidence directory. The explicit CREATE statements fail if either DB name is
already occupied; do not reset someone else's database.

```bash
scratch="$(mktemp -d /tmp/rb-registry-forms-proof-XXXXXX)"
cp docs/evidence/registry-form-errors-2026-11-08/logs/run-env.py "$scratch/"
docker exec rb-mariadb mariadb -uroot -prootpw -e "CREATE DATABASE retroboards_test_registry_forms_20261108 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE retroboards_e2e_registry_forms_20261108 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON retroboards_test_registry_forms_20261108.* TO 'retro'@'%'; GRANT ALL PRIVILEGES ON retroboards_e2e_registry_forms_20261108.* TO 'retro'@'%';"
python3 "$scratch/run-env.py" php bin/console migrate
python3 "$scratch/run-env.py" php -S 127.0.0.1:8031 -t public public/index.php
```

From another shell at the worktree root, with the same scratch value:

```bash
python3 "$scratch/run-env.py" php vendor/bin/phpunit tests/Integration/Core/AppRegistryFormErrorsTest.php tests/Integration/Core/AppRegistryAdminTest.php tests/Integration/Service/RegistryTrustServiceTest.php tests/Integration/Service/RegistryAdvisoryServiceTest.php --colors=never
python3 "$scratch/run-env.py" composer test -- --colors=never --display-deprecations --display-skipped
```

Run browser evidence from `tests/browser` while the private server is running:

```bash
python3 "$scratch/run-env.py" node node_modules/@playwright/test/cli.js test registry-form-errors.spec.ts --reporter=line
```

The initial/final result distinctions, final source hashes and cleanup are retained
with this evidence. No live-provider execution, production changes, merge or
deployment is included. Options 3–5 remain independent follow-ups.

## Final coverage, limitations and cleanup

The full suite's eight deprecations are PHP 8.5 notices in unchanged TLS
reflection/constants (`DatabaseTlsTest` and `Database`), `curl_close`
(`CloudflareSmtpMailer`) and `imagedestroy` (`ThemeAssetScanner`). All eight sites
and their triggering tests are named in the full log. These are separate from
this registry correction. No unrelated assertion failure remains.

The one skip is
`AppThreadIntelligenceMigrationTest::test_0077_down_and_up_rehearsal_on_fixture_free_schema`.
Recorded reason: direct down/up rehearsal runs only on its dedicated throwaway
database. Recorded command: `DB_TEST_DATABASE=retroboards_thread_intelligence_clean
vendor/bin/phpunit tests/Integration/Core/AppThreadIntelligenceMigrationTest.php
--filter test_0077_down_and_up_rehearsal_on_fixture_free_schema`. Migration 0077
is unchanged here, so that specialized destructive rehearsal was not run. The
focused registry PHP/browser checks have no skips.

Text execution logs have only trailing whitespace normalized for repository
checks; their original byte hashes are in `logs/raw-output-sha256.json`, with raw
copies retained in the task scratch directory. The compressed tested patch
preserves the exact diff bytes. Source/test digests were rechecked after the
full run with no drift. The initial baseline fixture is retained separately
from the expanded final regression file.

Both task databases were dropped, the private PHP server stopped, and private
rate-limit/package/media stores plus the test schema fingerprint removed. See
[cleanup.json](cleanup.json) and [results.json](results.json). The shared MariaDB
container, other databases and the original keyring workspace were preserved.
The independent registry worktree and small reproducible scratch logs remain
available for review. No live provider, merge or deployment was executed.
