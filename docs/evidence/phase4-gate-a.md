# Phase 4 Gate A Evidence

Updated: 2026-06-30

## Automated Tests

- Accepted Gate A baseline: `./vendor/bin/phpunit` → 456 tests / 1635 assertions, green.
- Current checkout: `RB_TEST_FRESH=1 composer test` → 803 tests / 3236 assertions, green.
- `./vendor/bin/phpunit tests/Unit/SanitizationTest.php` → table rendering is sanitized.
- `./vendor/bin/phpunit tests/Integration/Core/AppPhase4GateATest.php` → topic workflow, staff-set status protection, group-DM intervals/reports/account-state/report throttle, advanced Markdown, board/tag follows, board tag/wiki toggles, tag merge/visibility/hidden-write gating, reputation ledger window/delete/restore/rebuild, legacy repair-to-ledger compatibility, remove-follower, summary source/retire/restore, and wiki revert coverage.
- `./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php` → Phase 4 flags deploy dark by default; group-DM and tag public/admin routes are route-gated while dark.
- `cd tests/browser && npm run evidence` → 27 passed / 1 skipped across desktop
  and mobile viewports, refreshing 28 screenshots per viewport in
  `docs/evidence/browser/`.
- `tests/backup/rehearse.sh` → latest saved closeout log
  `docs/evidence/backup-restore/prodlike-rehearsal-2026-06-30.log`; the
  backup/restore rehearsal passed on the current 105-table schema with 116
  seeded rows, matching row counts and `CHECKSUM TABLE`, no pending migrations,
  clean repair, and restored app boot.

## Adjacent Regression Sweeps

- `AppFollowFeedTest`
- `AppLeaderboardTest`
- `AppReactionTest`
- `AppBadgeSolvedTest`
- `AppDirectMessageTest`
- `AppPostingTest`
- `AppModeratorScopeTest`
- `AppModerationTest`

## Remaining Evidence Gaps

Security/privacy reads were improved with regression tests, local browser capture
is refreshed, and the local backup/restore rehearsal passes. Production rollout
still needs the remaining PHASE_4_PLAN release-operations evidence set:
accessibility tooling, SEO/crawl behavior, performance/load budgets, production
backup/rollback operations, explicit operator runbook rehearsal, and
product-owner closeout. This is recorded as release-operations evidence in
`docs/adr/0003-phase-4-closeout-deferrals.md`.
