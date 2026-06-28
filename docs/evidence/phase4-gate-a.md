# Phase 4 Gate A Evidence

Updated: 2026-06-28

## Automated Tests

- `./vendor/bin/phpunit` → 448 tests / 1572 assertions, green.
- `./vendor/bin/phpunit tests/Unit/SanitizationTest.php` → table rendering is sanitized.
- `./vendor/bin/phpunit tests/Integration/Core/AppPhase4GateATest.php` → topic workflow, group-DM intervals/reports/account-state, advanced Markdown, board/tag follows, board tag/wiki toggles, reputation ledger window/delete/restore/rebuild, remove-follower, and community-memory smoke coverage.
- `./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php` → Phase 4 flags deploy dark by default.

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

Security/privacy reads were improved with regression tests, but Gate A still needs the PHASE_4_PLAN evidence set for browser flows, accessibility, SEO/crawl behavior, performance/load budgets, backup/rollback, and explicit operator runbook rehearsal.
