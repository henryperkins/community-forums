# Phase 4 Gate A Evidence

Updated: 2026-06-28

## Automated Tests

- `./vendor/bin/phpunit` → 456 tests / 1635 assertions, green.
- `./vendor/bin/phpunit tests/Unit/SanitizationTest.php` → table rendering is sanitized.
- `./vendor/bin/phpunit tests/Integration/Core/AppPhase4GateATest.php` → topic workflow, staff-set status protection, group-DM intervals/reports/account-state/report throttle, advanced Markdown, board/tag follows, board tag/wiki toggles, tag merge/visibility/hidden-write gating, reputation ledger window/delete/restore/rebuild, legacy repair-to-ledger compatibility, remove-follower, summary source/retire/restore, and wiki revert coverage.
- `./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php` → Phase 4 flags deploy dark by default; group-DM and tag public/admin routes are route-gated while dark.

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

Security/privacy reads were improved with regression tests. Production rollout still needs the PHASE_4_PLAN evidence set for browser flows, accessibility, SEO/crawl behavior, performance/load budgets, backup/rollback, and explicit operator runbook rehearsal; this is recorded as release-operations evidence in `docs/adr/0003-phase-4-closeout-deferrals.md`.
