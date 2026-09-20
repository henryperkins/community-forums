# N3 implementation report

Commit: `b211ea36` — Make digest scheduling durable and delivery outcomes truthful.

Implemented in the isolated `codex/unified-notifications-settings` worktree. Runtime ownership is released after the N3 commit; parent may start A4. Main checkout untouched.

## Changes

- New `DigestActivityRepository` freezes original daily subscription source IDs and applies shared visibility/block predicates, current source settings, exclusive/inclusive UTC bounds and upper post ID. No thread limit truncates large digests.
- New `DigestService` snapshots v1 payloads and validates/renders each attempt with fresh visibility. Preserves site branding and adds signed unsubscribe links. `sources.saved_feeds=[]` is reserved for A4.
- Digest scheduler locks each recipient and atomically enqueues `digest:uid:local_date` with watermark consumption. NULL/empty timezone is UTC, late cron and DST work, invalid zones are counted without watermark changes, and UTC windows never reverse. Paused/suppressed/banned windows consume without mail. SMTP runs only after the scheduling transaction.
- Shared drainer supports instant/digest/system announcement/test. Recipient lookup through status recording is inside each row's exception boundary. Each attempt reloads state/preferences and content; legacy actor provenance remains intact. Purged NULL recipients suppress and later rows continue.
- Global transport blocks return `sender_unconfigured` or `domain_unverified`, preserving payload/error/attempts. Suppressed outcomes have terminal reason, no sent timestamp/message ID and no pending retry. Valid transport failures retry with the existing schedule; malformed/legacy digests permanently fail.
- Repository, service, admin UI and direct POST all reject terminal/unreplayable requeues. Valid failed digest requeue tested through the actual route and captured transport.
- Explicit new service bindings in App and CLI; both cron entry points use the shared drainer. Fixed pre-existing purge CLI constructor omission of WebAuthnCredentialRepository, as requested by parent for actual purge rehearsal.
- ADMIN section 7.6 and operations section 3 describe honest statuses, replay limits, global blocks and the SMTP crash/duplicate boundary; removed blanket SQL replay guidance.

## Verification

TDD RED: focused worker/admin/repository/service set initially produced 68 tests / 312 assertions / two expected failures: failed digest lacked retry queue status, unavailable instant falsely reported Sent. Expanded RED tests also caught admin terminal retry visibility, operator diagnostic pause behavior, and system email flag retry behavior; corrected and verified.

Final command:

```sh
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'DailyDigestWorkerTest|NotificationEmailWorkerTest|EmailOpsRepositoryTest|EmailOpsServiceTest|AppAdminEmailTest|AppAccountLifecycleTest|AppNotificationPreferencesRepairTest' --log-junit /tmp/n3-junit.xml
```

Result: **114 tests, 1,029 assertions, all passing**, 2.352s. Prior 112-test pass and final pass confirm rollback cleanup leaves reusable DB clean. One earlier injected-rollback cleanup initially omitted deleting fixture posts before their parent thread; fixed cleanup and reset only dedicated `retroboards_unified_test` with `RB_TEST_FRESH=1`, then repeated focused verification successfully. No trigger remains from the test. No real mail: ArrayMailer captures; unconfigured case uses SendmailMailer with empty From.

`php -l` passed on DigestService, DigestActivityRepository and bin/console. `git diff --check` passed on all owned source/docs/tests. Parent separately reported no-JS admin browser GREEN (one case / 3.5s) and actual CLI rehearsal with all N3 outcomes passing; saved-feed case intentionally awaits A4. Full suite and final combined browser evidence remain parent release gates.

Sanitized tracked artifacts: `docs/evidence/unified-notifications-and-settings/n3-phpunit-summary.json`, `n3-outcome-matrix.md`.

## Self-review and handoff

Reason codes: `recipient_missing`, `recipient_deleted`, `recipient_banned`, `recipient_ineligible`, `recipient_paused`, `address_suppressed`, `delivery_disabled`, `content_unavailable`; permanent failures `invalid_digest_payload`, `unreplayable_legacy_digest`, `unsupported_kind`. Operator diagnostics bypass member pause/address suppression, preserving existing authorized test-send semantics; other current state checks remain.

Backwards-compatible constructors: NotificationEmailWorker adds optional DigestService last; DailyDigestWorker adds optional DigestService then NotificationEmailWorker last. Worker `run(limit, kind)` and repository `pending(limit, kind)` accept null to drain all kinds. Saved-feed support must extend payload validation and repository selection/render behavior in A4, maintaining original-source and fresh current eligibility contracts.

Self-review checked native prepared statements, injected Database compatibility, watermark transaction boundary, final visibility refresh, strict terminal requeue classification, parent-file exclusion and absence of real-mail side effects. The implementation guarantees durable fixed-window scheduling, not exactly-once SMTP across a transport-success/crash boundary.


## Independent-review P2 follow-up (2026-09-20)

Follow-up commit: `92bbe65b` — Reject malformed digest date payloads without throwing.

Verified N3-review.md finding: JSON-decoded date strings containing NUL caused `DateTimeImmutable::createFromFormat` to throw ValueError. The worker retried those permanently malformed jobs; an already Failed row with an older parser error also made admin GET/direct requeue fail during classification.

Added two regressions before the fix. RED: 2 tests / 2 assertions / 2 expected failures (admin HTTP 500, NUL rows retrying). `DigestService::validPayload` now catches the parser's ValueError and returns false. Tests cover both UTC fields containing NUL, an invalid calendar date, permanent `invalid_digest_payload`, later valid announcement delivery, and a historical Failed parser-error row whose admin page returns 200 and direct requeue leaves the entire row unchanged with no audit.

GREEN command: same seven-class focused filter above with `--log-junit /tmp/n3-review-fix-junit.xml`. Result: **116 tests / 1,060 assertions / zero failures or errors**, 2.296s. PHP syntax and owned-path whitespace checks pass. No real mail. Sanitized artifact: `docs/evidence/unified-notifications-and-settings/n3-review-fix-summary.json`.

Only DigestService, the two regression test files, and the new evidence artifact are included in the follow-up commit; A2 changes and parent's uncommitted docs/browser fixtures remain untouched. Runtime source ownership is released after this commit for A3.
