# Phase 4 Gate A Evidence

Updated: 2026-07-01

## Automated Tests

- Accepted Gate A baseline: `./vendor/bin/phpunit` → 456 tests / 1635 assertions, green.
- Current checkout: `composer test` → 866 tests / 4493 assertions, green.
- `./vendor/bin/phpunit tests/Unit/SanitizationTest.php` → table rendering is sanitized.
- `./vendor/bin/phpunit tests/Integration/Core/AppPhase4GateATest.php` → topic workflow, staff-set status protection, group-DM intervals/reports/account-state/report throttle, advanced Markdown, board/tag follows, board tag/wiki toggles, tag merge/visibility/hidden-write gating, reputation ledger window/delete/restore/rebuild, legacy repair-to-ledger compatibility, remove-follower, summary source/retire/restore, and wiki revert coverage.
- `./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php` → `topic_workflow`, `tags`, `expanded_feeds`, and `reputation_ledger` default on and remain rollback-safe through the `features` override; non-graduated Gate A flags remain default-dark.
- `./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppPhase4GateATest.php tests/Integration/Core/AppFollowFeedTest.php tests/Integration/Core/AppLeaderboardTest.php` → 47 tests / 286 assertions, green for the graduated tags/feeds/reputation acceptance path.
- `cd tests/browser && npm run evidence` → 29 passed / 1 skipped across desktop
  and mobile viewports, refreshing screenshots in `docs/evidence/browser/`.
- `cd tests/browser && npm run a11y` → 8 passed, no serious/critical axe
  violations in the covered admin dark-surface, member appeal/server draft,
  poll, and topic-workflow surfaces.
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

## Design-System and Runbook References

- Imported Imladris design system: `docs/design-system/imladris/README.md`.
- Activated surface map: `docs/design-system/imladris/ACTIVATED_FEATURES.md`
  (`tags`, `expanded_feeds`, `reputation_ledger`).
- Operations runbook: `docs/runbooks/phase4-tags-feeds-reputation.md`.

## Remaining Evidence Gaps

Security/privacy reads were improved with regression tests, local browser capture
is refreshed, and the local backup/restore rehearsal passes. Production rollout
still needs the remaining PHASE_4_PLAN release-operations evidence set:
accessibility tooling, SEO/crawl behavior, performance/load budgets, production
backup/rollback operations, explicit operator runbook rehearsal, and
product-owner closeout. This is recorded as release-operations evidence in
`docs/adr/0003-phase-4-closeout-deferrals.md`.
