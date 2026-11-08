# Signed-in email resubscription authorization

Date: 2026-11-08 (UTC). Branch: `feat/app-key-keyring`.

## Revision and dependency

This correction is a separate commit on top of
`402e8127a5848c203235d9780dd7097960d1deb5` (APP_KEY key ring). That parent
introduced `POST /settings/notifications/email/resubscribe`; the correction
depends on that route, its notification-settings panel and suppression lookup.
It does not include audit options 2–5, key migration changes, provider work or
production actions.

The starting checkout was clean. The prior audit snapshot was based on
`b958596c` plus uncommitted work; it was not restored. HEAD, the parent diff,
current controllers, services, repositories, tests and ADR 0059 were reread
before editing. GitHub's PR search for this branch returned no PR; the correction
stays on the branch that owns the implementation.

The exact tested source/test patch is [tested.patch.gz](tested.patch.gz) (gzip-compressed); its SHA-256
and individual file digests are in [tested-files.json](tested-files.json).
Documentation and screenshots accompany that patch in the containing commit.
Text logs have trailing whitespace normalized for repository checks; their raw
output hashes are in `logs/raw-output-sha256.json` and original copies remain in
the task scratch directory. The compressed patch preserves its exact bytes.
The new regression test is
[`AppEmailResubscriptionTest.php`](../../../tests/Integration/Core/AppEmailResubscriptionTest.php).

## Behavior and transaction

- `NotificationSettingsService::resubscribeEmail()` receives the authenticated
  account ID, locks the current account row with `findForUpdate()`, and applies
  its existing `WriteGate` policy. Both this method and ordinary notification
  delivery increases use that policy; no controller-specific state list exists.
- The account's current email comes from that locked row. In the same transaction,
  the repository runs a single `DELETE ... WHERE email = ? AND reason =
  'unsubscribe'`. This write evaluates the current suppression reason and locks
  the row as necessary. There is no preceding eligibility read followed by an
  unconditional delete.
- Restricted states receive the established 403 denial with their suppression
  preserved. An expired suspension follows `WriteGate`'s existing policy.
- A successful removal changes only that unsubscribe. Another member's suppression,
  pauses, digest/timezone choices, subscription channels/frequency and other
  preferences remain intact. Existing delivery reductions remain permitted.
- Feedback reports either removal with other settings still applying, or no
  removal. It does not claim that mail is enabled through every independent gate.

USER §4.6 is the controlling delivery-increase rule. ADR 0059's signed-in recovery
route retains its original purpose and now documents the authorization and
conditional-mutation boundary.

## Red baseline

Both commands ran against the **unmodified application at `402e8127`** with the
new regression tests, using a fresh private test database and fake configuration:

```bash
python3 "$scratch/run-env.py" php vendor/bin/phpunit tests/Integration/Core/AppEmailResubscriptionTest.php --filter test_a_member_suspended_after_sign_in --colors=never
python3 "$scratch/run-env.py" php vendor/bin/phpunit tests/Integration/Core/AppEmailResubscriptionTest.php --filter test_a_concurrent_operator_change --colors=never
```

- [Suspended-after-sign-in baseline](logs/baseline-suspended.txt): expected 403,
  observed 303; one failing test.
- [Operator-change baseline](logs/baseline-race.txt): bounce and complaint were
  deleted after a second connection committed the change. Four failing cases,
  covering native and emulated prepares. Each observed `null` instead of the
  operator's suppression reason.

The retained test bodies reproduce these failures on the parent. Later test
additions also cover pause/subscription preservation and current contact/state;
source line numbers in the first logs refer to the earlier version of the test
file. The primary reproduction and interleave bodies are retained.

The race tests leave the harness rollback transaction only for fixtures they own,
then clean up those committed users and suppression rows. A test-only PDOStatement
wrapper schedules an actual second-connection update immediately before the
member's real delete. It also attempts an account update with a one-second
session lock-wait limit, proving that the authorization row remains locked until
removal completes. The suppression result is read from the independent connection.
No repository or policy is mocked and no production hook was introduced.

## Corrected verification

- [Focused PHP checks](logs/focused-final.txt): **38 tests, 590 assertions passed**.
  Command:

  ```bash
  python3 "$scratch/run-env.py" php vendor/bin/phpunit tests/Integration/Core/AppEmailResubscriptionTest.php tests/Integration/Core/AppNotificationPreferencesRepairTest.php tests/Integration/Core/AppKeyRingRotationTest.php --colors=never
  ```

  This covers the new 16-case regression file, existing restricted-member
  delivery reductions and the parent key-ring resubscription contract. It includes
  suspended-after-sign-in, banned/deactivated/pending-deletion/deleted/indefinitely
  suspended states, expired suspension, fresh contact/state despite an old viewer,
  CSRF/authentication, another member, pauses/digest/subscription preferences,
  manual/bounce/complaint suppressions and both concurrent prepare modes.
- [Focused browser journey](logs/browser.txt): **4 passed, 0 skipped**, at
  1280×800 and 390×844, each with JavaScript on and off. The checked-in
  [`email-resubscription.spec.ts`](../../../tests/browser/email-resubscription.spec.ts)
  uses real native forms and actual POST responses. Its
  [fixture](../../../tests/browser/email-resubscription-fixture.php) refuses
  databases outside its dedicated test prefix.
The parent key-ring browser spec's success-message expectation was aligned with
the new feedback; that entire spec was not rerun. Its PHP resubscription cases
did run, and the focused native browser journey verifies the new feedback.

- [Full PHP suite](logs/full-phpunit.txt): **exit 0, 3,634 tests, 34,607
  assertions**, completed in 5:56.962. There were no failures/errors, with eight
  existing PHP 8.5 deprecations and one intentionally guarded migration-rehearsal
  skip. The earlier timeout is excluded from this passing result.
- `git diff --check`: passed. No generated-asset or Worker input changed, so no
  asset rebuild or Worker verification was required by ADR 0051.

The browser journey signs in before suspension, submits the stale native form
and observes 403, reloads settings and sees the unsubscribe retained, lifts the
fixture restriction and removes the eligible row while the pause remains checked,
changes a displayed unsubscribe into an operator complaint before submit and
observes a no-op, then reduces digest delivery while suspended. The other
member's suppression and recorded independent preferences survive every step.

Representative captures (the full four-mode matrix is under `browser/`):

| State | Phone without JavaScript | Desktop without JavaScript |
|---|---|---|
| Suspension denial | [403](browser/mobile/no-js/01-suspension-denied.png) | [403](browser/desktop/no-js/01-suspension-denied.png) |
| Eligible removal; pause retained | [Settings](browser/mobile/no-js/02-unsubscribe-removed-pause-retained.png) | [Settings](browser/desktop/no-js/02-unsubscribe-removed-pause-retained.png) |
| Operator complaint retained | [No-op](browser/mobile/no-js/03-operator-complaint-retained.png) | [No-op](browser/desktop/no-js/03-operator-complaint-retained.png) |
| Restricted reduction allowed | [Digest off](browser/mobile/no-js/04-restricted-reduction-permitted.png) | [Digest off](browser/desktop/no-js/04-restricted-reduction-permitted.png) |

### Runner corrections

The first focused PHP invocation exposed a test-setup error: the guest POST
had no established guest CSRF cookie. The test now obtains it from `/login`
before checking the authentication redirect. The final focused/full checks use
that corrected setup.

The initial browser attempt selected the nonexistent literal option value `off`;
the native Off option actually has an empty value. That attempt was stopped and
is preserved in [browser-initial.txt](logs/browser-initial.txt). The journey now
selects the displayed **Off** label and verifies the persisted null and rendered
empty value; all four cases passed afterward.

The first full `composer test` invocation reached over 94% before Composer's
300-second process timeout terminated it. Its
[partial log](logs/full-phpunit-timeout.txt) is not a passing verification. No
application changes followed. The private test schema fingerprint was removed
to recover the interrupted run, and the suite was rerun with
`COMPOSER_PROCESS_TIMEOUT=0`, plus deprecation/skip details. No global setting or
application behavior changed. `RB_TEST_FRESH=1` was not inherited by concurrency
subprocesses.

## Reproduction configuration

Observed toolchain: PHP 8.5.4, PHPUnit 11.5.56, Node 22.22.1, Playwright 1.61.1
with bundled Chromium, existing local MariaDB container. No dependency changes
were needed.

The retained [environment runner](logs/run-env.py) constructs a clean process
environment with local fixture DB settings, `APP_ENV=test`, `APP_KEY='0'×64`, a
dummy AI credential, `MAIL_DRIVER=array`, insecure local-only session cookies,
and isolated rate-limit/package/media paths. It inherits PATH, HOME and LANG
only. The [full-suite runner](logs/run-full-env.py) additionally disables
Composer's process time limit. No real provider call, live email, production
change, merge or deployment was performed.

To reproduce from the correction checkout, copy both runners into a newly
created scratch directory so their runtime paths stay outside the repository:

```bash
scratch="$(mktemp -d /tmp/rb-resubscription-proof-XXXXXX)"
cp docs/evidence/email-resubscription-2026-11-08/logs/run-env.py "$scratch/"
cp docs/evidence/email-resubscription-2026-11-08/logs/run-full-env.py "$scratch/"
docker exec rb-mariadb mariadb -uroot -prootpw -e "CREATE DATABASE retroboards_test_resubscription_20261108 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE retroboards_e2e_resubscription_20261108 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON retroboards_test_resubscription_20261108.* TO 'retro'@'%'; GRANT ALL PRIVILEGES ON retroboards_e2e_resubscription_20261108.* TO 'retro'@'%';"
python3 "$scratch/run-env.py" php bin/console migrate
python3 "$scratch/run-env.py" php tests/browser/seed.php
python3 "$scratch/run-env.py" php -S 127.0.0.1:8029 -t public public/index.php
```

The explicit `CREATE DATABASE` fails if either name is occupied; do not reset a
pre-existing database. Run the PHP commands above from repository root in another
shell. Run the browser command from `tests/browser` while the private server is
running:

```bash
python3 "$scratch/run-env.py" node node_modules/@playwright/test/cli.js test email-resubscription.spec.ts --reporter=line
```

The browser's PHP server and test process share the runner configuration. Its
fixture never enables providers or dispatches mail. The full suite command is:

```bash
python3 "$scratch/run-full-env.py" composer test -- --colors=never --display-deprecations --display-skipped
```

The original task's disposable resources are removed after checks complete;
cleanup details are recorded alongside the final result below. Keep this
committed evidence and the regression tests to preserve reproducibility.

## Final coverage and cleanup

The full suite's skip is
`AppThreadIntelligenceMigrationTest::test_0077_down_and_up_rehearsal_on_fixture_free_schema`: it requires
`DB_TEST_DATABASE=retroboards_thread_intelligence_clean`. Migration 0077 is
unchanged; this destructive specialized rehearsal was not run for this correction.

The eight deprecations are in unchanged `DatabaseTlsTest` / `Database` TLS
constants and reflection calls, `CloudflareSmtpMailer::curl_close`, and
`ThemeAssetScanner::imagedestroy`. The full log lists all triggering tests and
locations. These are existing PHP 8.5 compatibility notices; no unrelated
assertion failure remains. The correction's focused PHP and browser checks have
no skips. No old audit pass count is used as evidence for this patch.

The private PHP server was stopped, both task databases were dropped, and the
private media/package/rate-limit stores plus test schema fingerprint were removed.
The existing MariaDB container and other databases were preserved. See
[cleanup.json](cleanup.json) and [results.json](results.json). Only reproducible
logs/screenshots and small scratch copies remain. The actual source/test patch
hashes were checked again after the full run with no drift.

No production resources, live email-provider calls, merge or deployment are part
of this correction. Audit options 2–5 remain separate follow-ups.
