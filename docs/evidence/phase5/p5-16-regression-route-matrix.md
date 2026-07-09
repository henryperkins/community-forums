# P5-16 Regression and Route-Permission Matrix

**Date:** 2026-07-09
**Requirement:** GA-DOD-20
**Status:** Captured.

## Commands

Commands were run from the isolated `phase5-p5-16-closeout` worktree with the
deterministic test-only `APP_KEY` set because this worktree has no `.env`.

## Results

| Command | Final output line |
|---|---|
| `APP_KEY=0000000000000000000000000000000000000000000000000000000000000000 RB_TEST_FRESH=1 vendor/bin/phpunit --no-progress` | `OK (1831 tests, 9396 assertions)` |
| `APP_KEY=0000000000000000000000000000000000000000000000000000000000000000 vendor/bin/phpunit --no-progress` pass 1 | `OK (1831 tests, 9396 assertions)` |
| `APP_KEY=0000000000000000000000000000000000000000000000000000000000000000 vendor/bin/phpunit --no-progress` pass 2 | `OK (1831 tests, 9396 assertions)` |
| Focused route-permission matrix | `OK (240 tests, 2139 assertions)` |
| All-flags-off filter | `OK (2 tests, 29 assertions)` |
| Package-execution-disabled pins, no process-wide brake env | `OK (25 tests, 93 assertions)` |

## Coverage

- Full regression matched test and assertion counts across fresh schema and two
  reused-schema passes.
- The focused matrix covered feature flags, API authorization, resolver parity
  inventory, enforcement cutover, role/board roster administration, invitations,
  OIDC providers, passkey registration/login/recovery, package security console,
  and theme package paths.
- The package brake was exercised by the existing DB setting and injected config
  cases inside `PackageCredentialAuthGuardTest` and related package health tests;
  no process-wide `PACKAGE_EXECUTION_DISABLED=1` wrapper was used.

## Disposition

GA-DOD-20 can advance after this file is linked in
`docs/phase5/requirement-ledger.json` and the Phase5EvidenceMap guard passes.
