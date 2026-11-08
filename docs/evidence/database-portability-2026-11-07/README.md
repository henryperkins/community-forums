# Database portability and reaction identity

Date: 2026-11-07. This is local qualification of the backend findings recorded
in [Forum theme evidence](../forum-theme/README.md). Publication and production
migration of this candidate have not been performed.

Base application revision: `b1d2a591b384`. Qualification uses PHP 8.5.4 and
PHPUnit 11.5.56 against MySQL 8.4.13, MariaDB 10.11.19 and MariaDB 11.8.9.
The final full MySQL and MariaDB 10.11 runs use disposable Docker containers
with RAM-backed `/var/lib/mysql` storage to reduce repeated schema-reset I/O;
the upgrade rehearsals ran on their original disposable disk-backed containers.
The existing MariaDB 11 container retains its normal storage.

## Corrections

`ThreadIntelligenceQueue` chooses `FOR SHARE SKIP LOCKED` on MySQL and
`LOCK IN SHARE MODE SKIP LOCKED` on MariaDB from the connected server's version.
The shared lock, acquisition order, visibility gate and transient-busy behavior
remain intact. Existing queue tests cover simultaneous enqueues on one board,
visibility changes and sweep contention. The syntax follows the
[MySQL locking-read documentation](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html)
and [MariaDB SELECT documentation](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/select).

Migration `0086_reaction_emoji_identity` changes only `reactions.emoji` to
`VARCHAR(48) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL`. The existing
unique key and foreign keys remain. An `information_schema` guard avoids
repeating the ALTER after a completed upgrade. `down()` retains the correction:
restoring a lossy collation could collapse distinct keys. Greenfield rollback
eventually drops the table through `0019`.

An inventory of the migrations found no other Unicode emoji key columns.
`custom_emoji.shortcode` contains normalized ASCII catalogue names and retains
its existing matching rules. This migration cannot reconstruct reactions
previously removed by a colliding toggle.

The audit test now extracts the rendered Before and After JSON and compares
their decoded content. It still requires the raw-details markup and both
original state values, independently of MySQL's whitespace normalization.

The complete MySQL run exposed four more formatting-dependent assertions in
`ThreadIntelligenceBudgetTest`: corrupt counters and three invalid-date cases.
These now capture the database's stored JSON before calling the budget, then
require identical stored bytes afterward. Corruption must still refuse a
reservation and must never reset spend; no budget application code changed.

## Reproduction and upgrade rehearsal

Before the fixes, the MySQL 8.4 queue test failed with SQL error 1064 at
`SKIP LOCKED`, and the audit test rejected normalized JSON whitespace. On
MariaDB 10.11, the new HTTP regression and both previously documented reaction
tests failed: adding a second supplementary emoji removed the first.

The new browser regression also failed against `utf8mb4_general_ci`: adding
Celebrate unselected Commend. With migration `0086` applied, the same case passes.

The [upgrade rehearsal script](emoji-upgrade-rehearsal.php) builds all 85 historical
migrations in a new disposable database on each isolated engine. It seeds three
legacy reactions, including a maximum-length custom shortcode, then applies
`0086` directly twice. It checks unchanged IDs, references, emoji bytes and
timestamps; unchanged capacity and nullability; one ALTER on the first call and
zero on replay; independent existence/toggle lookups under native and emulated
prepares; and preservation of distinct reactions through the forward-only
`down()` method. Each scratch database is removed afterward.

- [MySQL 8.4.13 rehearsal](emoji-upgrade-mysql84.json), starting from the historical
  `utf8mb4_0900_ai_ci` column: passed.
- [MariaDB 10.11.19 rehearsal](emoji-upgrade-maria1011.json), starting from
  `utf8mb4_general_ci`: passed.

## Validation

All three full PHP runs exited **0**, with zero failures and errors. Completed
checks:

| Check | Result |
| --- | --- |
| Full PHP — MySQL 8.4.13 | [3,574 tests, 33,992 assertions, three skips](logs/phpunit-mysql84.log) |
| Full PHP — MariaDB 10.11.19 | [3,574 tests, 34,009 assertions, two skips](logs/phpunit-maria1011.log) |
| Full PHP — MariaDB 11.8.9 | [3,574 tests, 34,017 assertions, one skip](logs/phpunit-maria11.log) |
| Focused queue, audit and reaction tests — MySQL 8.4 | 55 tests, 328 assertions, zero failures/errors, one engine-setting skip |
| Focused queue, audit and reaction tests — MariaDB 10.11 | 55 tests, 328 assertions, zero failures/errors, one engine-setting skip |
| Final budget corruption/reservation tests — all three engines | 18 tests, 63 assertions each: [MySQL](logs/budget-mysql84.log), [MariaDB 10.11](logs/budget-maria1011.log), [MariaDB 11](logs/budget-maria11.log) |
| Upgrade and direct replay — both engines | Passed, with native and emulated prepares |
| Desktop/mobile reaction identity — enhanced/native | [Four browser cases passed](browser-results.txt) |
| Asset drift | `npm run check:assets` passed |
| Worker asset runtime | `node --test tests/worker/assets-runtime.test.mjs` [passed after sandbox restrictions were removed](worker-runtime-result.json) |

The final edit only corrected budget-test comparisons. MariaDB 11's full
application pass was followed by the focused run of the final budget file.

Every full run reports the same eight existing PHP 8.5 deprecation locations:
reflection accessibility calls, legacy PDO TLS constants, `curl_close()` and
`imagedestroy()`. These are detailed in the linked full logs and remain separate
runtime-maintenance work; they do not fail these runs.

The skips are explicit: migration `0077`'s destructive down/up rehearsal requires
its own named database on every engine; the stale-view abort case requires
MariaDB's `innodb_snapshot_isolation=ON` (exercised on MariaDB 11, inapplicable on
MySQL and disabled on fresh MariaDB 10.11); and MySQL rejects the oversized JSON
number used by one feature-override round-trip case before application code can
receive it.
The first full-suite attempts had an empty local `APP_KEY` and exceeded
Composer's 300-second timeout. Final runs use an explicit non-production key,
`MAIL_DRIVER=array`, `COMPOSER_PROCESS_TIMEOUT=0` and fresh test databases.
The default local Sendmail configuration has no `From` address, which correctly
caused `AppEmailChangeTest::test_request_keeps_the_old_contact_and_shows_pending_address`
to return 422. That unchanged test passes with the configured in-memory mailer.
The force-reset runs only
in a separate bootstrap preflight: inheriting `RB_TEST_FRESH=1` into race-test
children makes them rebuild the parent's database while it holds row locks.
Those stalled attempts were stopped and are not counted as completed results.

[full-suite.sh](full-suite.sh) selects the in-memory mailer, performs the
preflight, unsets the reset flag, then invokes `composer test` with a streamed
event log and deprecation details.
Run from the repository root with the selected isolated database's environment:

```bash
env APP_KEY=0000000000000000000000000000000000000000000000000000000000000000 \
  COMPOSER_PROCESS_TIMEOUT=0 DB_HOST=127.0.0.1 DB_PORT=33084 DB_SOCKET= \
  DB_USERNAME=root DB_PASSWORD= DB_SSL=false \
  DB_TEST_DATABASE=retroboards_portability_mysql84 \
  bash docs/evidence/database-portability-2026-11-07/full-suite.sh /tmp/rb-mysql84-events.log
```

MariaDB 10.11 uses port `33101` and `retroboards_portability_maria1011` on its
separate container. MariaDB 11 uses the repository's normal test DB configuration.

Browser evidence uses Chrome for Testing 149.0.7827.55 on Linux, driven by
Playwright 1.61.1, at 1280×800 desktop and 390×844 mobile
CSS pixels, with a disposable MariaDB 10.11 database and normal CSP. The flow
adds Commend and Celebrate, verifies both selected states after reload, removes
Celebrate, and verifies that Commend remains selected with its original count.
The desktop Add picker remains a native form even with JavaScript enabled.

- [Desktop enhanced](browser/desktop/emoji-identity-enhanced.png)
- [Desktop native](browser/desktop/emoji-identity-native.png)
- [Mobile enhanced](browser/mobile/emoji-identity-enhanced.png)
- [Mobile native](browser/mobile/emoji-identity-native.png)

The PHP regression additionally checks author reputation after independent
addition/removal and after `RepairService` recomputation. Browser fixtures use
self-reactions to avoid modifying another member's reputation ledger.

## Retained release

`npm run assets:record-release -- 5dc1b40f3b23d46d` records the previously verified
live asset version alongside `c66e3b55dba6da68` and `3630440af0d45598`, retiring
`67ba8434286ff7e6`. The current asset version remains `5dc1b40f3b23d46d`. The
Worker runtime test exercises retained dependencies and verifies their bytes,
as well as GET, conditional 304, HEAD and safe missing-file behavior.

This groups release bookkeeping with the backend change under ADR 0051;
it is not a new deployment receipt.
