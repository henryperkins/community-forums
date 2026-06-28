# Phase 4 Status

**Status:** engineering closeout complete with explicit deferrals; product-owner accepted as the Phase 5 entry baseline on 2026-06-28
**Last updated:** 2026-06-28  
**Branch:** `main`
**Suite:** `./vendor/bin/phpunit` → 456 tests / 1635 assertions, green

## Accepted Gate A Scope

Phase 4 now has a reconciled additive schema
(`database/migrations/0048_phase4_gate_a.php`, `SCHEMA.md` v1.14),
deploy-dark Phase 4 flags, and local regression coverage for the accepted Gate A
advanced-community slice:

- Topic workflow: canonical status/history, personal snooze, assignment, inbox filters, and staff-set status protection.
- Group DMs: bounded creation, membership intervals, owner actions, unread/history boundaries, admin-actionable reports, inactive-account rejection, and DM-report rate limiting.
- Advanced canonical content: task lists, tables, horizontal rules, and sanitizer coverage on the existing Markdown pipeline.
- Discovery graph: board/tag follows distinct from subscriptions, public tag lifecycle controls, tag aliases/merge, hidden/disabled tag gating, board `tags_enabled` enforcement, and follower removal.
- Recognition: reputation-event ledger, week/month/all-time and board-scoped leaderboards, opt-out handling, delete/restore reversal, no `FOR UPDATE` ledger hot path, and legacy `repair:reputation` routed through the ledger rebuild.
- Community memory: manual summaries with source display, publish/retire/restore, curated related topics, wiki edit history, board `wiki_enabled` enforcement, and wiki revert.
- Rollback controls: Phase 4 Gate A flags default dark until intentionally enabled.

## Explicit Deferrals

`docs/adr/0003-phase-4-closeout-deferrals.md` is the Phase 4 carryover ledger.
The 2026-06-28 Phase 5 release-train instruction accepts these deferrals as
explicit carryovers, not shipped behavior.
The following are not shipped behavior:

- Custom badge rule engine / preview / backfill / revoke UI.
- Board/thread/post reference cards and persisted `content_references` rendering.
- Moderator split/merge services and redirect flows.
- Gate B rich-expression and automation surfaces: previews/embeds, expanded non-image files, polls, custom emoji, slash-command/GIF insertion, automated since-last-read context, profile-media/signature/board-folder polish, and saved feed organization.
- Production rollout/a11y/load/SEO artifacts beyond the local automated suite,
  Playwright browser capture, and backup/restore rehearsal.

## Evidence Index

- Standalone index: `docs/evidence/phase4-gate-a.md`.
- Deferral ADR: `docs/adr/0003-phase-4-closeout-deferrals.md`.
- Full suite: `./vendor/bin/phpunit` → 456 tests / 1635 assertions.
- Focused Phase 4 regressions: `tests/Integration/Core/AppPhase4GateATest.php`.
- Deploy-dark flag regression: `tests/Integration/Core/AppFeatureFlagTest.php`.
- Markdown sanitizer regression: `tests/Unit/SanitizationTest.php`.
- Browser evidence: `cd tests/browser && npm run evidence` → 10 Playwright tests
  across desktop/mobile, refreshing `docs/evidence/browser/{desktop,mobile}`.
- Backup/restore evidence: `tests/backup/rehearse.sh` →
  `docs/evidence/backup-restore/rehearsal.log`, current result 53 tables / 83 rows.
- Adjacent regression sweeps covered by full suite: `AppFollowFeedTest`, `AppLeaderboardTest`, `AppReactionTest`, `AppBadgeSolvedTest`, `AppDirectMessageTest`, `AppPostingTest`, `AppModeratorScopeTest`, `AppModerationTest`.

## Operating Notes

- `php bin/console repair:reputation`, `repair:reputation-ledger`, and `reputation:reconcile` now rebuild `reputation_events` from canonical reactions/accepted answers, reverse stale events, and reconcile `users.reputation`.
- Phase 4 Gate A feature flags default `false`: `topic_workflow`, `group_dms`, `tags`, `expanded_feeds`, `reputation_ledger`, `badge_rules`, `community_memory`.
- All-time leaderboard remains governed by the existing `community` flag; windowed/board leaderboard modes require `reputation_ledger`.
