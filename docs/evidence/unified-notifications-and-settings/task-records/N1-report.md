# N1: notification visibility and privacy

Status: complete within N1 scope. Runtime/evidence commit: `6b02ad9b` (parent `a12195f9`). No push or merge. Parent-owned `AGENTS.md` change was left unstaged.

Implemented shared request-local `NotificationVisibilityService::scope(User, bool $fresh = false)` and `NotificationReadService::page()/unreadCount()`. Scope carries current board memberships, raw board assignments, features, and the same site REPORT_HANDLE authority probe used by ReportService queue discovery. Raw assignments preserve suspended assigned-moderator read access. App binds both services; shell receives a lazy exception-safe `notification_unread` closure. HTML center, Notices, bell, settings labels and workers use current eligibility.

`NotificationRepository::pageForScope`, `unreadCountForScope`, and `findForScope` share one eligibility predicate. Filtering happens before LIMIT. User IDs are prepared bindings; cursor/limit and internal board IDs are integer-clamped. No named placeholder is repeated. Deleted/pending/moved content, both block directions, feature-disabled DMs, retained DM membership/time bounds, and DM-report authority are enforced. Exact actor-block exemptions: mod, announcement, badge; target access is never exempt. Anonymous content authors are masked before scoped rows leave the repository, including clearing actor_id from returned models. Legacy unfiltered persistence helpers remain for existing tests; no runtime notification surface calls them.

Final thread click resolution uses canonical ThreadReadService. Direct owned-ID scope lookup replaces the recent-100 search as a prerequisite. The later N2 action service still owns full DM/appeal target behavior and mutation sequencing. Existing DM ConversationController GET readability is verified with historical membership and group_dms=false.

Settings lists retain inaccessible owned subscriptions with `available=false`, neutral labels, and NULL slugs; N2 owns controls/owner-only opt-out and N4 owns presentation. The optional scope parameter preserves manual listForUserWithContext callers and constructs fallback scope only from the repository's injected DB.

Instant send checks reload the recipient, explicitly refresh scope, use canonical ThreadReadService, and reject held/deleted/inaccessible posts and blocks. Digest selection rechecks fresh scope and shared SQL board/block predicates. Suspended assigned moderators retain delivery read access. NULL/empty digest timezones use UTC. Sender/domain checks, transport behavior and current sent/skipped/status semantics are unchanged; N3 owns the outbox/status refactor and lifecycle recipient suppression.

Constructor compatibility: both worker constructors accept an optional trailing visibility collaborator; CLI supplies it explicitly. DailyDigestWorker already receives Database. NotificationEmailWorker's existing six/nine-argument callers in NotificationEmailWorkerTest, AppAdminEmailTest, and bin/console were inspected. The fallback uses additive PostRepository::database() to retain the exact injected connection; it never reconstructs Database from Config. This preserves old direct constructors and is tested by existing helper/Admin tests. App bindings use the same request DB/AuthorityGate.

## RED evidence

1. `flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppNotificationPrivacyTest|DailyDigestWorkerTest|NotificationEmailWorkerTest'`
   Result: exit 1; 20 tests / 77 assertions / 3 failures. `/notifications` returned 200 and disclosed PRIVATE-AFTER-REVOCATION; revoked-private digest sent one mail; held-post instant mail sent one. The HTML failure was the actual marker, not a missing route. Subsequent GREEN covers both HTML lists, pane marker, JSON and settings.
2. `flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter test_dm_notice_must_fall_within_retained_membership_interval`
   Result: exit 1; 1 test / 1 assertion / 1 failure; pre-membership DM notice inflated count from 1 to 2. Added joined-at lower bound, then GREEN.

## Final verification

`flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppNotificationPrivacyTest|AppNotificationTest|DailyDigestWorkerTest|NotificationEmailWorkerTest|AppAdminEmailTest|AppUserSettingsTest|AppAnonymousPostingTest|AppFeatureFlagTest|AppReportQueueTest|AppSetupTest|NotificationBroadcastTest|AppModerationAppealsTest'`

Exit 0: **140 tests / 920 assertions**, 2.914 seconds. No failures, warnings, risky tests or skips reported. The feature-flag suite emits its expected malformed-features diagnostic. Exact output: `docs/evidence/unified-notifications-and-settings/n1-phpunit.log`.

Additional `git diff --cached --check` and PHP syntax checks for bin/console and the three new runtime classes passed. Full suite and browser evidence intentionally remain parent-owned release gates.

## Query evidence

Reproducible command (test DB only; guard rejects other names):

`flock /tmp/retroboards-unified-phpunit.lock env DB_TEST_DATABASE=retroboards_unified_test MAIL_DRIVER=sendmail MAIL_FROM='' php docs/evidence/unified-notifications-and-settings/notification-query-plan.php`

Raw capture: `docs/evidence/unified-notifications-and-settings/notification-query-plan.json` (EXPLAIN page/count and ANALYZE FORMAT=JSON count). All seeded rows rolled back in finally. MariaDB 11.8.6; 10,000 notifications / 100 subscriptions, half inaccessible: 5,000 eligible unread, full 30-item page, 100 safe subscription rows including 50 neutral unavailable labels. **Six queries** total for scope + page + count + subscription labels, **zero queries** for repeated scope/count. No per-notification/per-subscription application membership lookup. Count uses SQL COUNT(*) and never materializes notification rows in PHP.

Observed combined elapsed time: 16.013 ms, not a threshold. Page optimizer chose descending PRIMARY traversal with eq_ref target joins; count chose a 10,000-row scan because every seeded notification belongs to the same member, with eq_ref thread/board/post joins and indexed block/membership subqueries. This records actual database work rather than claiming an index-only count or constant runtime. No evidence justified adding a schema index in this slice.

## Cross-task contracts / remaining work

- N2: use NotificationReadService page model (`items`, `unread`, `next_before`, `unread_only`); findForScope supplies direct eligible owned-ID lookup. Move mutations/DM/targetless mod appeal routing into the action service, enforce safe return targets, complete settings reductions and subscription controls. Scope preserves DM group history even with group_dms=false. Subscriptions expose `available` plus neutral labels/NULL slugs.
- N3: retain explicit fresh=true on every send/retry, continue shared board/block rules, implement honest suppressed statuses and recipient lifecycle guards; no N1 transport/status semantic changes were made. Current announcement email rendering still belongs to N3's per-job recipient contract.
- N4/N5: consume page model and lazy notification_unread closure; no policy in templates. Finish shared responsive rows/filter/history/badge presentation. Current public methods support pagination; UI controls remain later tasks.
- Parent: full PHPUnit, fresh browser proof, release matrix and account work. Existing DM notification rows have timestamps/conversation IDs rather than a message ID; scope respects membership times and joined-after message bounds, while ConversationController remains authoritative for actual displayed history.

## Independent-review correction: solved-event actor provenance

Correction commit: `e4b5c0f9`, parent `6bca7a20`. This fixes the sole confirmed must-fix in `N1-review.md`. Read-only reviewer reproduction `/tmp/N1SolvedBlockReviewTest.php` showed both directions of a block hiding the solved notification while its email sent, because the target answer author is the recipient and not the accepter.

Every newly queued instant job now writes the existing JSON payload with exactly the provenance fields `version:1`, `type:instant`, `event_type:reply|new_thread|mention|solved`, `actor_id:int`, and `post_id:int`. No content, title, actor name or body is snapshotted. All producers pass their actual event actor, including mention edits and solved acceptance. Idempotency keys remain `post:user`; no schema, transport, sender/domain policy or status semantics changed. Payload survives clearing in-app history.

The worker validates payload shape/version/target and applies blocks against its actual actor on every attempt, independently of canonical post/thread authorization. `NotificationVisibilityService::canReadPost` accepts an optional explicit actor-ID list; an empty list fails closed. New worker calls always pass the resolved list. Legacy three-argument callers retain the post-author default for ordinary post activity. **N3 must preserve the payload and explicit actor gate; it must not revert instant jobs to checking only `post.user_id`.**

Legacy jobs with NULL payload consult surviving notification rows for that recipient/post and require every candidate actor to pass; missing/NULL candidate actor IDs fail closed. When no event rows remain, only an unchanged post owned by someone other than the recipient safely reconstructs ordinary initial-post provenance. Self-authored targets (including solved answers) and edited targets without surviving actor evidence are unavailable: the worker does not guess an accepter/editor. Malformed non-NULL payloads also fail closed. This retains normal legacy pending-mail compatibility while intentionally withholding ambiguous legacy events; N3 may translate the unchanged unavailable outcome into its planned suppressed status.

Genuine RED command:

`flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'testSolvedEmailRespects|testLegacySolvedEmailWithNoActorEvidenceFailsClosed'`

Exit 1: **3 tests / 5 assertions / 3 failures**, each transport count 1 instead of 0. The two new-job cases clear notification history before send, proving the correction must preserve independent provenance.

Final focused GREEN:

`flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppNotificationPrivacyTest|AppNotificationTest|NotificationEmailWorkerTest|DailyDigestWorkerTest|AppAdminEmailTest'`

Exit 0: **67 tests / 369 assertions**, 0.937 seconds. Includes both block directions after enqueue and after a first failed transport attempt, same-worker retry, unblocked new solved delivery after notification clearing, legacy solved delivery with surviving actor evidence, blocked legacy solved retry, missing legacy self-authored/edited provenance, and pre-existing ordinary legacy mail positives.

Original reviewer reproduction rerun verbatim:

`flock /tmp/retroboards-unified-phpunit.lock env DB_TEST_DATABASE=retroboards_unified_test MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --bootstrap tests/bootstrap.php /tmp/N1SolvedBlockReviewTest.php`

Exit 0: **2 tests / 4 assertions**. `git diff --cached --check` passed. Durable RED/GREEN output is in `docs/evidence/unified-notifications-and-settings/n1-solved-actor-{red,green}.log`. Parent changes were excluded using an explicit-path `git commit --only`; no push/merge. Ownership released for N2. Full suite/browser remain parent-owned.

## Final legacy-provenance correction

Commit: `9195fe72`. This addresses the residual ambiguity confirmed by `/tmp/N1LegacyActorReviewTest.php` and appended independent review: an original NULL-payload email-only job may outlive the creation of a later moderator-edit mention. Because `post:user` deduplication retains the original queued job, that later notification does not prove the job's original actor.

For NULL-payload jobs with surviving candidate notifications, the worker now requires both the original post author and every inferred candidate actor to pass the block gate. Deduplicating that integer list preserves ordinary legacy/solved positives while preventing a later unblocked actor from weakening the prior author gate. Explicit v1 event payloads still apply their recorded actual actor alone; a new editor-mention job remains deliverable when only the original post author is blocked. Missing or invalid provenance fail-closed paths remain unchanged. Only the worker helper, its tests, and two evidence logs changed; N2 repository/actions source was untouched.

RED:

`flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'testLaterMentionCannotReplaceLegacyActor|testExplicitMentionActorIsNotReplacedByBlockedPostAuthor'`

Exit 1: **3 tests / 11 assertions / 2 failures**. Both legacy block directions sent one message instead of zero; the explicit-payload positive passed before the correction.

GREEN:

`flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppNotificationPrivacyTest|AppNotificationTest|NotificationEmailWorkerTest|DailyDigestWorkerTest|AppAdminEmailTest'`

Exit 0: **73 tests / 416 assertions**, 1.134 seconds. Existing ordinary legacy, new solved provenance, legacy actor-evidence positives, missing-evidence rejection and blocked retry checks remain green alongside the new scenarios.

`flock /tmp/retroboards-unified-phpunit.lock env DB_TEST_DATABASE=retroboards_unified_test MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --bootstrap tests/bootstrap.php /tmp/N1LegacyActorReviewTest.php`

Exit 0: **1 test / 4 assertions**. Diff whitespace checks passed. Durable logs: `docs/evidence/unified-notifications-and-settings/n1-legacy-actor-{red,green}.log`. Commit used exact `--only` paths; parent docs/browser changes remain excluded. Source ownership released for N3; no full suite, external mail, schema change, or delivery-status redesign.
