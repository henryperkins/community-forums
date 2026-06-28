# Phase 4 Gate A Status

**Status:** mid-Gate-A checkpoint, not a release candidate  
**Last updated:** 2026-06-28  
**Branch:** `phase-4-gate-a`  
**Suite:** `./vendor/bin/phpunit` → 448 tests / 1572 assertions, green

## Gate A Summary

Gate A now has a reconciled additive schema (`database/migrations/0048_phase4_gate_a.php`, `SCHEMA.md` v1.13), deploy-dark Phase 4 flags, and regression coverage for the blocker/high gaps identified in the 2026-06-28 review:

- Windowed/board leaderboards no longer join a non-existent `user_preferences.namespace`.
- Advanced Markdown table sanitization tests match the intended table feature.
- Group-DM reports are actionable in the admin report queue and notify admins.
- Group-DM participant adds reject inactive accounts; personal mute no longer writes shared event history.
- Board `tags_enabled` / `wiki_enabled` toggles are server-enforced.
- Members may apply approved tags where the board permits.
- Profile owners can remove followers.
- Post delete/restore routes reputation changes through `reputation_events`, with a canonical rebuild command.
- Phase 4 Gate A flags default dark until intentionally enabled.

## Carryover Ledger

| Item | State | Disposition |
|---|---|---|
| Custom badge rules / preview / backfill / revoke UI | Schema-only | Carry to Gate A completion or explicit deferral; `badge_rules` and `badge_award_history` are documented but rule engine is not built. |
| Content references / reference cards | Schema-only | Carry; `content_references` is documented but parser/rendering is not built. |
| Split/merge operations | Schema-only | Carry; `thread_operations` and `thread_redirects` are documented but operation services/routes are not built. |
| Summary retire/revert and source display | Partial | Carry; manual publish and related rendering exist, retire/revert/source UI still missing. |
| Wiki revert | Partial | Carry; wiki enable/edit revisions exist, revert flow missing. |
| Tag merge/delete/visibility management | Partial | Carry; approved catalogue + aliases exist, broader lifecycle incomplete. |
| Feed filters / digest controls / saved inbox views | Partial | Carry; query-time following/latest and board/tag follows exist behind `expanded_feeds`. |
| Full negative/security/a11y/SEO/perf/rollback evidence | Incomplete | Carry; PHPUnit coverage improved, browser/security/load evidence still required by PHASE_4_PLAN §10/§14. |

## Evidence Index

- Standalone index: `docs/evidence/phase4-gate-a.md`.
- Full suite: `./vendor/bin/phpunit` → 448 tests / 1572 assertions.
- Focused Phase 4 regressions: `tests/Integration/Core/AppPhase4GateATest.php`.
- Deploy-dark flag regression: `tests/Integration/Core/AppFeatureFlagTest.php`.
- Markdown sanitizer regression: `tests/Unit/SanitizationTest.php`.
- Adjacent regression sweeps run on 2026-06-28: `AppFollowFeedTest`, `AppLeaderboardTest`, `AppReactionTest`, `AppBadgeSolvedTest`, `AppDirectMessageTest`, `AppPostingTest`, `AppModeratorScopeTest`, `AppModerationTest`.

## Operating Notes

- New command: `php bin/console repair:reputation-ledger` (alias `reputation:reconcile`) rebuilds `reputation_events` from canonical reactions/accepted answers, reverses stale events, and reconciles `users.reputation`.
- Phase 4 Gate A feature flags default `false`: `topic_workflow`, `group_dms`, `tags`, `expanded_feeds`, `reputation_ledger`, `badge_rules`, `community_memory`.
- All-time leaderboard remains governed by the existing `community` flag; windowed/board leaderboard modes require `reputation_ledger`.
