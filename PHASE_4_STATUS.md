# Phase 4 Status

**Status:** engineering closeout complete with explicit deferrals; product-owner accepted as the Phase 5 entry baseline on 2026-06-28
**Last updated:** 2026-07-01
**Branch:** accepted baseline on `main`; current checkout includes later deploy-dark carryover code and closeout evidence
**Suite:** accepted baseline `./vendor/bin/phpunit` → 456 tests / 1635 assertions, green. Current checkout `composer test` → 866 tests / 4519 assertions, green

> 2026-06-30 carryover note: later branches implement additional ADR 0003
> carryovers behind dark flags, but they do not convert those carryovers into
> broad-rollout acceptance. See
> `docs/evidence/phase4-closeout/carryover-partial-stopping-point.md` and
> `docs/evidence/phase4-closeout/phase3-4-closeout-ledger.md`.

## Accepted Gate A Scope

Phase 4 Gate A has a reconciled additive schema
(`database/migrations/0048_phase4_gate_a.php`, originally `SCHEMA.md` v1.14),
reversible Phase 4 flags, and local regression coverage for the accepted Gate A
advanced-community slice. `topic_workflow`, `tags`, `expanded_feeds`, and
`reputation_ledger` now default on; the remaining Gate A workstreams stay
deploy-dark until intentionally enabled. The current consolidated schema is
reconciled through `SCHEMA.md` v1.24 for later deploy-dark carryovers.

- Topic workflow: canonical status/history, personal snooze, assignment, inbox filters, and staff-set status protection.
- Group DMs: bounded creation, membership intervals, owner actions, unread/history boundaries, admin-actionable reports, inactive-account rejection, and DM-report rate limiting.
- Advanced canonical content: task lists, tables, horizontal rules, and sanitizer coverage on the existing Markdown pipeline.
- Discovery graph: board/tag follows distinct from subscriptions, public tag lifecycle controls, tag aliases/merge, hidden/disabled tag gating, board `tags_enabled` enforcement, and follower removal.
- Recognition: reputation-event ledger, week/month/all-time and board-scoped leaderboards, opt-out handling, delete/restore reversal, no `FOR UPDATE` ledger hot path, and legacy `repair:reputation` routed through the ledger rebuild.
- Community memory: manual summaries with source display, publish/retire/restore, curated related topics, wiki edit history, board `wiki_enabled` enforcement, and wiki revert.
- Rollback controls: graduated Gate A features remain reversible through the
  `features` override; non-graduated Gate A flags default dark until intentionally
  enabled.

## Explicit Deferrals

`docs/adr/0003-phase-4-closeout-deferrals.md` is the Phase 4 carryover ledger.
The 2026-06-28 Phase 5 release-train instruction accepts these deferrals as
explicit carryovers, not shipped behavior.

The following remain not accepted for broad rollout:

- 2026-06-30 carryover implementations for moderation appeals, moderator
  split/merge, account lifecycle/export/delete, advanced theming, email
  domain/broadcast, and limited custom profile fields still need
  browser/a11y/runbook evidence before broad enablement.
- Production rollout/a11y/load/SEO artifacts beyond the local automated suite,
  Playwright browser capture, and backup/restore rehearsal.

Implementation gates are now recorded for policy-heavy carryovers:
`docs/adr/0006-account-lifecycle-export-delete-policy.md`,
`docs/adr/0007-moderation-appeals-policy.md`,
`docs/adr/0008-email-domain-send-blocking-policy.md`,
`docs/adr/0009-advanced-theming-custom-css-policy.md`,
`docs/adr/0010-server-draft-sync-scope.md`, and
`docs/adr/0011-public-plugin-runtime-scope.md`.

2026-06-30 review-hardening pass: `appeals` and `account_lifecycle` default
deploy-dark and are route-gated with dark-assertion coverage; account lifecycle
request/deactivate/reactivate/cancel and profile updates run inside
`$db->transaction()`; the deletion purge is wired to
`php bin/console worker:purge-accounts` and refuses to anonymize any account no
longer `pending_deletion`; the staff appeal queue is board-scoped like the report
queue; and broadcast announcement emails carry an unsubscribe link. The previously
tracked split/merge, appeals, and schema reconciliation gaps are now addressed by
focused tests and `SCHEMA.md` v1.23+.

The carryover branch has deploy-dark implementation evidence for
badge rules, post/DM/summary content references, link previews, expanded files,
polls, custom emoji, slash/GIPHY insertion, board folders, saved feed filters,
deterministic since-last-read context, scheduled related-topic refresh, avatar
upload/removal, signature hardening, moderation appeals, moderator split/merge,
account lifecycle/export/delete, email domain/broadcast, advanced theming,
bookmark folders, and bounded custom profile fields. These remain behind flags
or operator gates where applicable until the missing browser/a11y/upgrade/worker
runbook evidence is attached, except for polls plus the personal-organization
slice (`board_folders`, `bookmark_folders`, `saved_feeds`) that have graduated to
default-on.

Deploy-dark defaults are inventoried in
`docs/evidence/deploy-dark-features.md`; `src/Core/FeatureFlags.php` remains the
runtime source of truth.

## Evidence Index

- Standalone index: `docs/evidence/phase4-gate-a.md`.
- Deferral ADR: `docs/adr/0003-phase-4-closeout-deferrals.md`.
- Carryover ledger: `docs/evidence/phase4-closeout/phase3-4-closeout-ledger.md`.
- Current carryover stopping point: `docs/evidence/phase4-closeout/carryover-partial-stopping-point.md`.
- Full suite: `composer test` → 866 tests / 4519 assertions.
- Current Phase 4 focused spine: `AppPhase4GateATest`, `AppPhase4CarryoverFoundationTest`, `AppAdminBadgeRulesTest`, `AppExpandedFilesTest`, `AppLinkPreviewTest`, `AppPollTest`, `AppCustomEmojiGiphyTest`, `AppContentReferenceTest`, `AppAutomatedContextTest`, `RelatedTopicRefreshWorkerTest`, `AppProfileMediaTest`, `AppThreadSplitMergeTest`, `AppBoardFoldersSavedFeedsTest` → 83 tests / 546 assertions.
- Later carryover-adjacent focused suite: `AppModerationAppealsTest`, `AppAccountLifecycleTest`, `AppBrandingThemeTest`, `AppAdminEmailTest` → 36 tests / 218 assertions.
- Focused Phase 4 regressions: `tests/Integration/Core/AppPhase4GateATest.php`.
- Deploy-dark flag regression: `tests/Integration/Core/AppFeatureFlagTest.php`.
- Graduated tags/feeds/reputation focused sweep:
  `AppFeatureFlagTest`, `AppPhase4GateATest`, `AppFollowFeedTest`,
  `AppLeaderboardTest` → 47 tests / 286 assertions.
- Markdown sanitizer regression: `tests/Unit/SanitizationTest.php`.
- Browser evidence: `cd tests/browser && npm run evidence` → 29 passed / 1
  skipped across 30 Playwright tests, refreshing
  `docs/evidence/browser/{desktop,mobile}`.
- Accessibility: `cd tests/browser && npm run a11y` → 8 passed.
- Slash/GIPHY browser evidence: focused desktop + mobile `slash menu` Playwright
  runs generated `26-slash-menu` and `27-giphy-inserted`.
- Backup/restore evidence: `tests/backup/rehearse.sh` → latest saved closeout log
  `docs/evidence/backup-restore/prodlike-rehearsal-2026-06-30.log`, current
  result 105 tables / 116 rows.
- Adjacent regression sweeps covered by full suite: `AppFollowFeedTest`, `AppLeaderboardTest`, `AppReactionTest`, `AppBadgeSolvedTest`, `AppDirectMessageTest`, `AppPostingTest`, `AppModeratorScopeTest`, `AppModerationTest`.

## Operating Notes

- `php bin/console repair:reputation`, `repair:reputation-ledger`, and `reputation:reconcile` now rebuild `reputation_events` from canonical reactions/accepted answers, reverse stale events, and reconcile `users.reputation`.
- Phase 4 Gate A feature flags still default `false`: `group_dms`, `badge_rules`, `community_memory`, `content_references`.
- `topic_workflow` graduated to default-ON on 2026-07-01 (acceptance evidence: `AppFeatureFlagTest::test_topic_workflow_is_available_by_default_and_can_be_disabled`, browser `29-topic-workflow`, `.wf-actions`/`.wf-bar` axe pass, `docs/runbooks/topic_workflow.md`). Reversible via the `features` override.
- `tags`, `expanded_feeds`, and `reputation_ledger` graduated to default-ON on 2026-07-01 (acceptance evidence: `AppFeatureFlagTest`, `AppPhase4GateATest`, `AppFollowFeedTest`, `AppLeaderboardTest`, `docs/runbooks/phase4-tags-feeds-reputation.md`, and `docs/design-system/imladris/ACTIVATED_FEATURES.md`). Reversible via the `features` override.
- `board_folders`, `bookmark_folders`, and `saved_feeds` graduated to default-ON on 2026-07-01 (acceptance evidence: `AppPhase4CarryoverFoundationTest`, `AppBoardFoldersSavedFeedsTest`, and `docs/design-system/imladris/ACTIVATED_FEATURES.md`). Reversible via the `features` override.
- All-time leaderboard remains governed by the existing `community` flag; windowed/board leaderboard modes require `reputation_ledger`, which is now default-on.
