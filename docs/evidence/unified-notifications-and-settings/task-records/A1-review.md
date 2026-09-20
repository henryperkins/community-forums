# A1 independent review

Reviewed commit `e793088baee7038deaee71d9d1674b4f1d4bc622` against `a51dec5c`, using the committed source/diff, A1 brief, shared design, AGENTS.md, ADR 0006, and the explicit conservative-purge ruling in progress.md. Unrelated N1/browser changes were outside this review. No application code was edited.

**Verdict: changes required.** The original self-service bypasses are addressed, but one reproducible lifecycle-hold write bypass remains in the required lifecycle/moderation state contract.

## Must fix

**P1 — Preserve deletion and self-deactivation holds when a timed suspension expires.** `src/Service/UserModerationService.php:307-308` reads the pending request and then discards it while unconditionally writing `status=suspended`. For a member who requested deletion and is subsequently suspended during the grace period, expiry makes `User::isActive()` return true (`src/Domain/User.php:71-85`). `WriteGate` checks only the cached status, so ordinary profile/post writes become available while the durable deletion request remains pending. This is a new inconsistency produced by supported actions, so the deployment diagnostic for pre-existing anomalies cannot close it.

Reproduction through the real HTTP kernel:

1. Member POSTs `/settings/account/delete/request`; response 303.
2. Admin POSTs `/mod/u/{id}/suspend` with a future timed expiry; response 303.
3. Let that expiry pass while the 30-day deletion grace remains open. The focused test advances only `users.suspended_until` and the site's suspension `bans.expires_at` to the past.
4. Confirm `account_deletion_requests.status` remains `pending`.
5. Member POSTs `/settings/account` with a new display name: actual **303**, required **403**.

Fresh reproduction command (temporary test outside the repository):

```bash
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit /tmp/A1ExpiryReviewTest.php
```

Initial result: exit 1, **1 test / 4 assertions / 1 failure**: `Failed asserting that 303 is identical to 403`. Test fixture writes rolled back through the normal harness. The tested lifecycle/moderation/domain/write-gate source matches the reviewed commit; the worktree also contains unrelated concurrent N1 integration edits.

Authority: ADR 0006 requires the account to remain write-blocked throughout the deletion grace period; the A1 transition contract requires an existing pending deletion to remain write-blocked independently of moderation, and the shared design's Account-state safety section prohibits a moderation/deletion downgrade.

Minimal direction: use the already locked pending request and current self-deactivated status when applying a suspension, keeping those lifecycle holds authoritative after timed expiry, while retaining the suspension record/expiry so cancellation still preserves a live suspension. Preserving pending/deactivated cache states specifically for suspension is one bounded approach. Keep full-ban cache precedence intact because N3 must suppress banned recipients' email. Add HTTP regressions for automatic expiry over both pending deletion and self-deactivation, and retain existing ordinary expired-suspension activation coverage. Explicit moderation lift must likewise retain the self-deactivated state until the member confirms reactivation. Do not implement a blanket pending-over-ban cache rule.

### Confirmed self-deactivation leg and UI assessment

The analogous supported sequence is also broken: member deactivates (303), admin applies a timed suspension (303), suspension clocks expire, member submits an ordinary profile write without ever requesting reactivation: **303 instead of 403**. Added `test_self_deactivation_survives_timed_suspension_expiry` to the same temporary test. Combined focused run: exit 1, **2 tests / 7 assertions / 2 failures**, both expected 403 / actual 303. ADR 0006 requires self-deactivation to remain in effect until the member confirms reactivation.

The current availability calculation already consults live `bans` rows independently of cached lifecycle state (`AccountLifecycleService::actions`). Preserving cached `pending_deletion` or `deactivated` while storing suspension in the site restriction record therefore keeps invalid actions hidden, including for indefinite suspensions. Cancellation remains reachable for a pending request. Once moderation expires, a preserved deactivated account offers explicit Reactivate; ordinary writes remain blocked until that action succeeds.

The lifecycle template does not currently display a separate status badge or suspension expiry. It shows a generic pending-deletion/site-restriction explanation and the scheduled deletion date/cancellation form. No additional availability API is required for correct action visibility. If the repair adds explicit restriction details or a cancellation warning, derive them from the live site restriction model rather than inferring suspension solely from `row.status`; a pending cache will intentionally conceal that orthogonal restriction in the status field. Add rendered assertions for pending+live suspension (cancellation present, invalid forms absent) and deactivated+live suspension (reactivation absent), followed by explicit reactivation availability after expiry.

## Other reviewed behavior and limits

- Locked user/request/site-restriction reads, fresh lifecycle password reauthentication, and deliberate suspension timestamp writes address the original stale-state transitions.
- Owner-loss lifecycle paths acquire shared protected-owner/active-admin rowsets before the individual target; recovery/moderation paths start with the target. The committed standalone harness supplies two-connection outcomes and cleanup evidence, including both moderation commit orders and two owner deactivations.
- Cancellation and explicit moderation lift preserve independent restrictions/pending requests; purge rereads the durable request identity/deadline under lock and rejects canceled requests. The intentional conservative refusal for legacy active/deactivated anomalies is accepted, not a finding.
- UI availability and stale-form error fallback satisfy the covered lifecycle actions. Fresh parent browser evidence and the final combined browser gate remain integration requirements.
- Reported 43-test focused green and five race outcomes were inspected as evidence rather than rerun without cause. Only the new concrete expiry concern was executed in this review.
- N2 restricted-owner opt-outs, N3 actual-purge/drainer integration, final full-suite/browser evidence, and deployment-data reconciliation remain explicitly scheduled combined gates; they are not missing A1 implementation findings.

## Correction re-review — 794249a3

Reviewed the committed correction `794249a3add16f189c9bd69730a2ee69014c0efc` against `9d398b69`, retaining the original A1 review context. **Final A1 source/spec/quality verdict: approved; the P1 above is resolved, with no new must-fix findings in this correction.** This supersedes the initial changes-required verdict, while preserving its reproduction history.

The suspension transition now retains pending-deletion and self-deactivation cache holds, so automatic suspension expiry cannot authorize ordinary writes. The live suspension record still prevents premature reactivation and enables cancellation to recover the independent restriction. Explicit lift retains self-deactivation until member reactivation. Cached full bans and live full-site-ban records take precedence over added suspensions, preserving banned-recipient semantics for N3. Ordinary timed-only suspensions continue to expire automatically.

Reviewed the five added HTTP regressions and complete committed GREEN artifact: **48 tests / 222 assertions**, covering expiry across both holds, explicit lift after self-deactivation, and additional suspension over cached/live full bans. Tests check ordinary write responses and rendered action availability, with successful explicit recovery afterward. The updated runbook accurately documents the precedence. `git diff --check 9d398b69 794249a3` passed in this review.

Verified the locking SQL and acquisition order are unchanged: the helper returns its previously acquired request/restriction results, and all call sites consume the new return shape correctly. No new concrete concern warranted another database run; the review inspected the committed focused evidence and retained prior race evidence. No application edits were made.

Approval is for the bounded A1 implementation and correction. Parent's no-JS expiry capture, final shared PHPUnit/browser gates, N2 opt-out/N3 purge-drainer integration, and deployment diagnostic/reconciliation obligations remain the previously identified release gates, not claims made complete by this review.
