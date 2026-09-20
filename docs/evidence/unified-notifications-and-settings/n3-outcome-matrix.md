# N3 durable delivery evidence

2026-09-20. Focused PHP verification: **114 tests, 1,029 assertions, zero failures/errors**. See `n3-phpunit-summary.json` for the sanitized case inventory and exact command. No real mail was sent.

This is the N3 task-time record. The later malformed-date correction is recorded
in `n3-review-fix-summary.json` (116 tests, 1,060 assertions). A4 subsequently
implemented saved-feed sources, and the actual CLI rehearsal now verifies all
ten outcomes in [worker-cli.json](worker-cli.json). The [combined index](README.md)
owns final release results and reviewed browser evidence.

| Contract | Evidence |
|---|---|
| Reproduce unreplayable failure and false Sent | Initial focused run: 68 tests, 312 assertions, two expected failures: digest was Failed instead of Queued; unavailable instant was Sent instead of Suppressed |
| NULL zone needs no settings POST | `testNullTimezoneSchedulesDurablyBeforeUnconfiguredTransportAndRecovery`: fixed UTC snapshot, watermark, zero attempts, recovery and one job per date |
| Sender/domain block durable scheduling | Same NULL-zone case plus `testDomainBlockPreservesQueuedWindowAndPreviousErrorWithoutAttempt`: unchanged error/payload and zero attempts |
| Late cron and DST | `testBeforeHourWaitsAndLateCronSchedulesOnce`, `testDstGapAndFoldAndInvalidZoneAreObservable`, `testTimezoneChangeCannotReverseWindowOrRecreateScheduledDate` |
| Frozen source/window, live eligibility | `testRetryUsesFixedBoundsOriginalSourcesAndCurrentSourceSettings`: source disable, newly subscribed source excluded, backdated post above max ID excluded |
| No silent large-window truncation | `testLargeWindowIncludesAllThreadsAndBothTimeBounds`: all 106 eligible threads, inclusive upper and exclusive lower UTC bounds |
| Transaction rollback | `testSchedulingRollbackLeavesWatermarkAndOutboxUnchanged`: committed fixture, injected UPDATE failure after enqueue, actual rollback; trigger and fixture cleaned |
| Restricted-state opt-outs | Real settings POSTs after initial transport failure, then same-job retry, for suspended/banned/deactivated/pending deletion in both worker classes |
| Other restrictions are not automatic opt-out | `testCurrentPermittedStatesKeepDeliveryAndBanAfterFailureAllowsLaterJob` and existing suspended assigned-board digest case |
| Missing recipient and batch progress | Actual account purge regression unlinks user ID; purged job suppressed and later announcement sent; malformed/banned first rows also do not abort the batch |
| Terminal reasons and fresh retry gates | `testRetryRechecksEveryTerminalCauseAndDoesNotReplayAfterRestoration`: ban/delete/pause/digest Off/address suppression/source Off/block/private/post deletion |
| Paused/banned/suppressed due windows consumed | `testDuePausedSuppressedAndBannedWindowsAreConsumedWithoutCatchup` |
| Shared lock and both commands | `testBothCommandsShareDrainLockAndEmailWorkerDrainsDigestRetries`; existing bounded-drain/lock tests |
| Explicit kinds | Instant actor provenance regressions, system announcement, operator diagnostic message and diagnostic pause contract |
| Permanent malformed/legacy jobs | Batch test, repository and service no-op classification, actual admin GET and direct POST |
| Valid failed digest replay | `testOnlyReplayableFailedDigestCanBeRequeuedThroughRealAdminRoute`: real transport failure, admin requeue, captured mail and success metadata |
| No-JS admin browser | Parent reported one passing case (3.5s), real terminal UI/direct POST and valid requeue through captured CLI drain; parent owns final browser artifact promotion |
| CLI rehearsal | Parent owns `worker-rehearsal-before-a4/worker-cli.json`: N3 outcomes passed including actual purge, NULL zone and repeat drains; saved-feed outcome pending A4 |

Suppressed jobs are terminal. Invalid and unreplayable legacy digests are permanently failed. Sender/domain blocks are global status and do not overwrite job errors. Transport success followed by a crash can still duplicate external delivery; queue deduplication does not promise exactly-once SMTP. Saved-feed source entries remain reserved until A4. Full release PHPUnit and final combined browser/Imladris checks remain the parent's release gate.
