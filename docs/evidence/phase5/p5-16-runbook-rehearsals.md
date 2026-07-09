# P5-16 Runbook Rehearsals

**Date:** 2026-07-09
**Requirements:** GA-DOD-03, GA-DOD-19
**Status:** Captured.

## Environment Note

The plan's Docker database reset command was not available in this host
environment, and local root login was denied. The rehearsal therefore used the
pre-granted local test schemas:

- Migration/upgrade throwaway: `retroboards_test_dm`
- Backup source/destination: `retroboards_test` -> `retroboards_test_dm`

All console/app-boot commands used the deterministic test-only `APP_KEY` value
also used by the browser evidence harness.

## Rehearsal Matrix

| Area | Command or source | Result |
|---|---|---|
| Clean install | `APP_KEY=0000000000000000000000000000000000000000000000000000000000000000 APP_ENV=testing DB_DATABASE=retroboards_test_dm php bin/console migrate:fresh` | Applied 76 migration(s); `migrate:status \| tail -n 30` included `[x] 0076_phase5_invitation_audit`. |
| Full rollback/reapply | `APP_KEY=0000000000000000000000000000000000000000000000000000000000000000 APP_ENV=testing DB_DATABASE=retroboards_test_dm php bin/console migrate:rollback`, `migrate:status \| rg -c '\[ \]'`, then `migrate` with the same env | Rolled back 76 migration(s); unapplied count was 76; reapplied 76 migration(s); status tail again included `[x] 0076_phase5_invitation_audit`. |
| Historical upgrade | `APP_KEY=0000000000000000000000000000000000000000000000000000000000000000 APP_ENV=testing DB_DATABASE=retroboards_test_dm php bin/console verify:upgrade --force` | `Result: PASS` with 17/17 checks passed through `0076_phase5_invitation_audit`. |
| Backup/restore | `APP_KEY=0000000000000000000000000000000000000000000000000000000000000000 DB_CONTAINER=host DB_ROOT_USER=retro DB_ROOT_PASSWORD=retropw DB_BACKUP_SRC=retroboards_test DB_BACKUP_DST=retroboards_test_dm tests/backup/rehearse.sh` | Rehearsal passed; transcript: `docs/evidence/backup-restore/rehearsal.log`; 196311-byte dump, 114 tables, 310 rows, checksum match, schema no-op migration, repair clean, restored app booted. |
| Resolver fallback | `APP_KEY=0000000000000000000000000000000000000000000000000000000000000000 APP_ENV=testing php bin/console verify:resolver-parity` | 1551 tuples, 1551 agreed, 0 mismatches; wrote `docs/evidence/phase5/resolver-parity.md`. |
| Budgets | `APP_KEY=0000000000000000000000000000000000000000000000000000000000000000 APP_ENV=testing php bin/console verify:phase5-budgets` | Wrote `docs/evidence/phase5/performance-budgets.md`; Gate A measured rows passed: registry signature 0.4122 ms, package install/update 35.8306 ms, theme build/apply 7.6435 ms, resolver 0.3611 ms, WebAuthn 1.7076 ms, cached/cold OIDC 0.4734/1.1515 ms, invitation redemption 398.3025 ms. |
| Trust root / registry / package / theme | `APP_KEY=0000000000000000000000000000000000000000000000000000000000000000 RB_TEST_FRESH=1 vendor/bin/phpunit --no-progress` with the plan Step 6 file list | OK (83 tests, 398 assertions). |
| Owner / passkey / provider / invitations / token / worker | `APP_KEY=0000000000000000000000000000000000000000000000000000000000000000 vendor/bin/phpunit --no-progress` with the plan Step 7 file list | OK (163 tests, 923 assertions). |

## Command Highlights

```text
Fresh database. Applied 76 migration(s).
[x] 0076_phase5_invitation_audit
Rolled back 76 migration(s).
76
Applied 76 migration(s).
Result: PASS (17/17 checks passed)
Parity: 1551 tuples, 1551 agreed, 0 mismatches.
OK (83 tests, 398 assertions)
OK (163 tests, 923 assertions)
```

Backup/restore final line:

```text
Backup: 196311 bytes - 114 tables - 310 rows - restore verified byte-for-byte by row count + CHECKSUM TABLE, schema intact, app boots.
```
