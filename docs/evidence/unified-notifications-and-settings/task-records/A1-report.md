# A1 lifecycle repair implementation report

Status: implemented and locally committed; ready for independent review and shared integration gates.

Commit: `e793088b` — `Fix lifecycle transitions preserving moderation and deletion restrictions`.

## Implemented behavior

- Lifecycle transitions lock and reread the current user, durable pending deletion, and live site restrictions; password reauthentication uses the locked current password hash.
- Deactivation requires effective active state without pending deletion or live restrictions. Reactivation requires self-deactivated state without those independent restrictions. Both preserve the deliberate suspension timestamp. Expired timed suspensions retain existing effective-active behavior.
- Deletion requests accept active/self-deactivated eligible accounts, retain the 30-day grace, and repeat idempotently without overwriting later moderation. Cancellation removes the durable request while retaining cached bans/suspensions and recovering live restriction state from the bans record where necessary.
- Moderator suspend/ban/lift uses the same target/request/restriction lock order and rereads the governable target inside the transaction. Lift removes only site moderation, preserves self-deactivated/deleted states, and restores a pending deletion hold when its request exists.
- Admin owner-loss actions acquire shared protected-owner and active-admin rowsets before the target. Role-change guard calls were aligned to this order and current target role reread. Ordinary member operations start at the target and do not subsequently acquire owner rowsets. A concurrent promotion causes a validation retry rather than acquiring owner rowsets after locking the target.
- Purge locks and rechecks the pending request ID and deadline. A later ban/suspension cannot strand a due request; a canceled request cannot purge even if the cached user status is pending.
- The lifecycle template receives the service availability model, hides invalid action forms, leaves valid cancellation/reactivation reachable, and shows stale-tab error alerts even when the originating form disappeared.
- New BanRepository is hand-bound in App; existing direct constructor callers remain supported.

## RED

Before source changes:

```bash
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppAccountLifecycleTest|AppUserModerationTest|AppProtectedOwnerTest'
```

Exit 1: **37 tests, 139 assertions, 10 failures**. Failures reproduced the direct suspension bypass, pending-deletion bypass, suspended deletion/cancel bypass, invalid lifecycle forms during moderation, live-ban/cached-active mismatch, erased expired suspension timestamp, stale-password reauthentication, ban-stranded purge, lift clearing pending deletion, and lift clearing self-deactivation.

Full transient output: `/tmp/a1-red.log`. Durable failure-name/count summary: `docs/evidence/unified-notifications-settings/a1-phpunit-red.log` (large rendered HTML intentionally omitted).

## GREEN

First focused green: same command, exit 0, **37 tests / 172 assertions**.

Final focused run expanded to the touched role-change behavior:

```bash
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppAccountLifecycleTest|AppUserModerationTest|AppProtectedOwnerTest|AppChangeRoleTest'
```

Exit 0: **43 tests / 187 assertions**, PHP 8.5.4, 0 failures/warnings/skips reported. Durable complete output: `docs/evidence/unified-notifications-settings/a1-phpunit-green.log`.

`git diff --cached --check` passed before commit. No full-suite claim: parent explicitly owns final combined full-suite execution, overriding per-slice broad-suite skill guidance.

## Actual two-connection race evidence

```bash
env DB_LIFECYCLE_RACE_DATABASE=retroboards_unified_lifecycle_race MAIL_DRIVER=sendmail MAIL_FROM='' php tests/concurrency/account-lifecycle.php
```

Exit 0. The standalone harness uses committed uniquely named fixtures in the fixed isolated schema, separate PHP processes/connections, and observes the competing process's server-side `FOR UPDATE` query before committing the first transaction. It does not use the PHPUnit transaction harness. Only this invocation's fixture IDs are deleted; final fixture count is zero.

| Race | Child result | Child exit | Final result |
| --- | --- | --- | --- |
| Deactivate, moderator commits first | refused | 0 | banned, normal write gate 403 |
| Deactivate, lifecycle commits first | applied | 0 | banned, normal write gate 403 |
| Cancel deletion, moderator commits first | applied | 0 | banned, pending request canceled, normal write gate 403 |
| Cancel deletion, lifecycle commits first | applied | 0 | banned, pending request canceled, normal write gate 403 |
| Two owner deactivations | second refused | 0 | remaining owner active |

Durable output: `docs/evidence/unified-notifications-settings/a1-lifecycle-races.log`.

An initial race run failed with MariaDB error 1020 (`Record has changed since last read`) because a plain snapshot SELECT preceded the locking user SELECT inside the transaction. The environment is MariaDB 11.8.6, REPEATABLE-READ, `innodb_snapshot_isolation=1`. Removed that snapshot read; current transition-state reads are locking reads. Final harness passes using the unchanged database isolation settings. Failed-run cleanup also returned zero fixture rows.

## Browser handoff and observed result

Parent owns `tests/browser/account-settings-repairs.spec.ts` and its shared fixture to avoid concurrent file ownership. Parent reported both A1 no-JS cases passing on port 8034: suspended stale lifecycle actions return 422 and a normal profile write remains 403; cancellation after a subsequent suspension succeeds but the normal profile write remains 403. Parent owns durable captures and the final browser matrix.

Required lifecycle fixture/action coverage for subsequent review: ordinary deactivate/reactivate; pending-deletion stale deactivate returning 422 with visible alert then profile POST 403; moderation during pending deletion leaving cancellation visible and invalid actions absent; cancellation preserving suspension timestamp or ban and subsequent profile POST 403. PHPUnit includes both ban and suspension legs.

## Explicit rulings, deviations, and outstanding integration gates

- Parent approved retaining the existing conservative purge guard for legacy cached-active/deactivated + pending-request anomalies. Durable pending requests survive imposed bans/suspensions, but the repair does not expand automatic destructive purge to legacy active inconsistencies. The [account lifecycle runbook](../../../runbooks/account_lifecycle.md#pre-release-restriction-reconciliation) records this ruling and read-only diagnostic queries. Every discovered row requires targeted reconciliation before release; never blanket-reactivate. Deployment-data diagnostics have not been run by this agent.
- N2's restricted-state owned notification opt-outs are intentionally not implemented here. Integrate tests showing reductions still succeed for suspended, banned, deactivated, and pending-deletion authenticated members while normal writes remain blocked.
- N3 must pair an actual AccountLifecycleService purge (which detaches `email_deliveries.user_id` to NULL) with the shared ArrayMailer drain and assert `recipient_missing` suppression plus delivery of a later valid row. The existing purge detachment behavior is retained; N3 outbox changes are outside A1 ownership.
- Existing protected-owner regression coverage was run unchanged; the new two-owner race adds committed-transaction evidence.
- No real mail was sent. No migration, push, or merge was performed. Parent infrastructure/browser edits remain uncommitted and were not included in the A1 commit.

## Independent-review correction: suspension expiry (2026-09-20)

Reviewed `A1-review.md` and the reviewer's `/tmp/A1ExpiryReviewTest.php`; confirmed the review's supported HTTP sequences were valid. Correction commit: **`794249a3add16f189c9bd69730a2ee69014c0efc`**, `Preserve lifecycle holds when timed suspensions expire`, on top of N1 commit `6b02ad9b`.

Added five regressions before changing source. The same focused command was used for RED and GREEN:

```bash
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppAccountLifecycleTest|AppUserModerationTest|AppProtectedOwnerTest|AppChangeRoleTest'
```

- RED: exit 1, **48 tests / 211 assertions / 5 failures**. Pending deletion and self-deactivation both incorrectly allowed profile writes after timed moderation expired; explicit moderation lift also lost self-deactivation. A new suspension downgraded a full ban both with a correct banned cache and with a stale active cache backed by a live full-site-ban record.
- GREEN: exit 0, **48 tests / 222 assertions**. All focused lifecycle/moderation/protected-owner/role tests pass.
- Complete durable output: `docs/evidence/unified-notifications-settings/a1-expiry-phpunit-red.log` and `a1-expiry-phpunit-green.log`. Trailing whitespace from PHPUnit's empty response-body output was trimmed in the stored RED log; contents/results were otherwise retained. Final staged diff whitespace check passed.

The bounded change uses the already locked pending request and restriction rows when imposing a suspension. It preserves cached `pending_deletion` and `deactivated` holds while recording the separate live suspension/expiry. Those holds continue to block normal writes after expiry and after explicit moderation lift; permitted explicit cancellation/reactivation restores the member's access. Existing availability already checks the live restrictions, so pending cancellation remains visible, reactivation is hidden during suspension, and reactivation reappears after expiry/lift. These form assertions are covered in the regression tests without template changes.

Full-ban cache precedence is retained: an existing cached ban or a live full-site-ban record keeps `status=banned` when an additional suspension is imposed. This preserves N3 recipient suppression semantics and prevents a lesser timed restriction from downgrading the full ban. Ordinary timed-only suspensions retain their existing effective-active expiry behavior. The runbook records these precedence rules explicitly.

No locking queries/order changed: `lockModerationState` now returns both results it already locked instead of discarding the restriction rows. Consequently the five committed two-connection races were not repeated solely for this policy correction; the existing race evidence remains applicable. Parent owns browser rechecks and final shared suite. No App.php, browser, N1, AGENTS, or N6 drafts were edited. Source ownership released after this correction commit.
