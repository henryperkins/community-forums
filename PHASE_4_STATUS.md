# Phase 4 Status

**Status:** engineering closeout complete with explicit deferrals; product-owner accepted as the Phase 5 entry baseline on 2026-06-28
**Last updated:** 2026-06-29
**Branch:** accepted baseline on `main`; carryover progress on `phase3-4-closeout-completion`
**Suite:** accepted baseline `./vendor/bin/phpunit` → 456 tests / 1635 assertions, green. Current carryover branch `./vendor/bin/phpunit` → 720 tests / 2744 assertions, green

> 2026-06-29 carryover note: branch `phase3-4-closeout-completion` implements
> additional ADR 0003 carryovers behind dark flags, but it does not complete all
> carryovers or replace this accepted-with-deferrals baseline. See
> `docs/evidence/phase4-closeout/carryover-partial-stopping-point.md` and
> `docs/evidence/phase4-closeout/phase3-4-closeout-ledger.md`.

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

The following remain incomplete or not accepted for broad rollout:

- Moderation appeals.
- Moderator split/merge services and redirect flows.
- Account deactivation/reactivation, self-serve export/delete, bookmark folders,
  and limited custom profile fields.
- Production rollout/a11y/load/SEO artifacts beyond the local automated suite,
  Playwright browser capture, and backup/restore rehearsal.

The 2026-06-29 carryover branch has deploy-dark implementation evidence for
badge rules, post/DM/summary content references, link previews, expanded files,
polls, custom emoji, slash/GIPHY insertion, board folders, saved feed filters,
deterministic since-last-read context, scheduled related-topic refresh, avatar
upload/removal, and signature hardening. These remain behind flags until the
missing browser/a11y/upgrade/worker/runbook evidence is attached.

## Evidence Index

- Standalone index: `docs/evidence/phase4-gate-a.md`.
- Deferral ADR: `docs/adr/0003-phase-4-closeout-deferrals.md`.
- Carryover ledger: `docs/evidence/phase4-closeout/phase3-4-closeout-ledger.md`.
- Current carryover stopping point: `docs/evidence/phase4-closeout/carryover-partial-stopping-point.md`.
- Full suite: `./vendor/bin/phpunit` → 456 tests / 1635 assertions.
- Current carryover branch full suite: `./vendor/bin/phpunit` → 720 tests / 2744 assertions.
- Current carryover focused suite: `AppContentReferenceTest`, `AppAutomatedContextTest`, `AppProfileMediaTest`, `RelatedTopicRefreshWorkerTest` → 13 tests / 72 assertions.
- Slash/GIPHY focused suite: `AppCustomEmojiGiphyTest` → 5 tests / 26 assertions.
- Focused Phase 4 regressions: `tests/Integration/Core/AppPhase4GateATest.php`.
- Deploy-dark flag regression: `tests/Integration/Core/AppFeatureFlagTest.php`.
- Markdown sanitizer regression: `tests/Unit/SanitizationTest.php`.
- Browser evidence: `cd tests/browser && npm run evidence` → 27 passed / 1
  skipped across 28 Playwright tests, refreshing
  `docs/evidence/browser/{desktop,mobile}`.
- Slash/GIPHY browser evidence: focused desktop + mobile `slash menu` Playwright
  runs generated `26-slash-menu` and `27-giphy-inserted`.
- Backup/restore evidence: `tests/backup/rehearse.sh` →
  `docs/evidence/backup-restore/rehearsal.log`, current result 53 tables / 83 rows.
- Adjacent regression sweeps covered by full suite: `AppFollowFeedTest`, `AppLeaderboardTest`, `AppReactionTest`, `AppBadgeSolvedTest`, `AppDirectMessageTest`, `AppPostingTest`, `AppModeratorScopeTest`, `AppModerationTest`.

## Operating Notes

- `php bin/console repair:reputation`, `repair:reputation-ledger`, and `reputation:reconcile` now rebuild `reputation_events` from canonical reactions/accepted answers, reverse stale events, and reconcile `users.reputation`.
- Phase 4 Gate A feature flags default `false`: `topic_workflow`, `group_dms`, `tags`, `expanded_feeds`, `reputation_ledger`, `badge_rules`, `community_memory`.
- All-time leaderboard remains governed by the existing `community` flag; windowed/board leaderboard modes require `reputation_ledger`.
