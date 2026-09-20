# N1 independent review

Reviewed commit `6b02ad9b` against `a12195f9` only. Read N1 brief/report, shared design, repository guidance, changed runtime/tests, and canonical ThreadReadService, ReportService, ConversationController/DM membership, and notification producer contracts. Parent N6 work and concurrent account corrections are excluded.

**Spec verdict: changes required. Quality verdict: changes required for the actor-provenance defect below; otherwise the bounded shared eligibility implementation is sound.** One confirmed must-fix N1 finding; no other confirmed must-fix N1 gaps remain from this review.

## P2 — Accepted-answer email checks the recipient against themselves instead of the event actor

Location: `src/Service/NotificationVisibilityService.php:75-76`, called by `src/Worker/NotificationEmailWorker.php:132-133`.

`canReadPost()` always applies `blockedEitherWay(viewer.id, post.user_id)`. For ordinary new-post activity the post author is the event actor. For `solved`, `SolvedAnswerService::mark()` calls `NotificationService::notifySolved(answerAuthorId, actorId, threadId, postId)`: the queued post belongs to the recipient, while the accepter is the actor. The new check consequently tests the recipient against themselves. After either party blocks the other, scoped notification lists/counts correctly exclude the solved event, but its queued instant mail still sends. The shared design explicitly includes `solved` in actor-driven activity subject to both block directions. This is an N1 eligibility defect, not a deferred N3 transport/status feature.

Reproduction: create a topic owned by A and a reply by B; call the existing `notifySolved(B, A, topic, reply)` producer; block A→B or B→A after enqueue; read B's scoped unread count and drain with ArrayMailer. In both cases unread is 0, but one email is sent. Dedicated-database regression reproduction is retained at `/tmp/N1SolvedBlockReviewTest.php`:

```sh
flock /tmp/retroboards-unified-phpunit.lock env DB_TEST_DATABASE=retroboards_unified_test MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --bootstrap tests/bootstrap.php /tmp/N1SolvedBlockReviewTest.php
```

Result: **2 tests / 4 assertions / 2 failures**, each `Failed asserting that 1 is identical to 0` for the transport count. The fixtures roll back; only ArrayMailer was invoked. Relevant runtime files have no changes relative to the reviewed commit, so this reproduction exercises the N1 implementation.

Bounded fix direction: retain event type and actual actor provenance in the existing instant-job JSON payload, and apply the actor gate independently of the canonical post/thread read gate on every attempt. Preserve queued legacy `post:user` compatibility: ordinary post-author activity must remain renderable; legacy solved jobs need actor resolution from existing notification/source evidence where available and a conservative unavailable outcome when provenance cannot be established safely. Do not guess that the answer author accepted their own answer, or require all older jobs to have the new payload. Cover new and legacy solved jobs, both block directions imposed after enqueue/retry, and ordinary legacy instant-mail positives. This does not require a schema migration or the N3 outbox refactor.

## Confirmed coverage and quality

- Page, direct ID lookup, and SQL unread count share eligibility before LIMIT; pagination includes eligible rows behind more than 30 inaccessible entries. Limits/cursors are clamped integers, parameters do not repeat named PDO placeholders, and SQL count avoids full PHP row materialization.
- Public/hidden/private board checks agree with ThreadReadService, including raw assigned-moderator read authority during suspension. Pending/deleted posts and threads and moved private content are excluded. Final thread resolution uses the canonical service.
- Ordinary activity uses both actor-block directions; exactly mod/announcement/badge are exempt, with target authorization still enforced. Anonymous content rows clear actor username, display identity, and actor ID before returning scoped models.
- DM rules use historical membership, timestamps and joined-after bounds; dms disables DM activity while group_dms does not remove retained group history. DM-report scope follows the existing site REPORT_HANDLE authority probe and moderation_queue availability. Existing notifications have no exact DM-message identifier; the report correctly documents that representation limit.
- Settings queries return neutral labels and NULL slugs for inaccessible owned subscriptions. Worker post/digest selection explicitly refreshes scope, checks current publication/access and relevant post-author blocks, and retains suspended assigned-reader access. The exception is solved-event actor identity above.
- Inspected the recorded 140-test/920-assertion adjacent suite and reproducible 10,000-notification/100-subscription evidence: six queries, zero repeated scope/count queries, 16.013 ms observed. Those are implementer evidence, not a rerun by this reviewer. Browser/full-suite proof remains the parent release gate.
- Minor maintainability debt: scope service performs two membership SQL queries directly, and the optional subscription compatibility path constructs a service from the repository. These cross the preferred service/repository boundary, but they are bounded compatibility choices, not a reason for a broad refactor. Keep policy orchestration in services when extending these paths.

N2 read-action routing/mutation order/opt-outs/history UI and N3 durable digest/account-state/NULL-recipient/transport/status obligations are deliberately not reported as missing N1 features. No application source changes or commits were made during this review.

## Correction review: `e4b5c0f9` against `6bca7a20`

**Final N1 verdict: changes still required for one legacy-provenance P2 below. The original new-job solved-actor defect is corrected.** Reviewed only committed correction files; concurrent N2 work is excluded. Relevant runtime files remained identical to this commit during the targeted reproduction.

The new version-1 instant payload stores the actual event actor and post identity for all four emitted event types (`reply`, `new_thread`, `mention`, `solved`). The worker validates those fields and checks that actor on every attempt independently of content readability. Clearing notification history cannot lose the actor. Legacy unchanged ordinary post mail still has its positive fallback, legacy solved jobs with surviving actor rows check those actors, and missing self-authored/edited provenance fails closed. System announcement handling is unchanged; no emitted explicit instant type is accidentally rejected. Inspected the committed 67-test/369-assertion GREEN artifact and the report that the original 2-test/4-assertion reviewer repro passes; no broad suite rerun was necessary.

### P2 — Later notifications can substitute a different actor for an older legacy job

Location: `src/Worker/NotificationEmailWorker.php:173-176`; query: `src/Repository/NotificationRepository.php:199-205`.

`legacyInstantActors()` gathers all surviving notifications for a recipient/post without establishing that they produced the queued job. When any candidate exists, `instantActors()` returns those actors immediately and bypasses the conservative edited-post fallback. A later event therefore supplies apparently valid provenance for an older `post:user` job whose original event had no in-app row or was cleared. `EmailDeliveryRepository::enqueue()` uses INSERT IGNORE, so the later event does not actually replace that legacy job or its provenance.

Concrete reproduction: A publishes a post to B's email-only subscription (no in-app row); its legacy outbox job has NULL payload. B blocks A. An unblocked moderator C later edits that post and mentions B. The mention creates a C-actor notification, while enqueue deduplication preserves A's original NULL-payload job. The worker now resolves only C and sends the older job despite its blocked actor A. This defeats the stated conservative legacy compatibility rule; it is not a N2/N3 deferred feature.

Saved reproduction: `/tmp/N1LegacyActorReviewTest.php`.

```sh
flock /tmp/retroboards-unified-phpunit.lock env DB_TEST_DATABASE=retroboards_unified_test MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --bootstrap tests/bootstrap.php /tmp/N1LegacyActorReviewTest.php
```

Result: **1 test / 4 assertions / 1 failure** (`1` email rather than `0`). The preceding assertions establish no initial notification, unchanged NULL payload after the later producer's deduplication, and one surviving later actor candidate. Only ArrayMailer was used; all fixtures rolled back.

Bounded correction: later matching notification rows cannot by themselves establish the actor of an older job. Tie legacy evidence to the queued event where possible, or conservatively retain all possible originating actors (including the original content author for ordinary non-self activity) and fail closed where source provenance remains ambiguous. A later mention must not make an edited or otherwise ambiguous legacy job eligible. Preserve explicit new payload behavior and ordinary unchanged legacy positives; add the email-only/later-mention regression alongside the existing solved tests. No other confirmed correction-slice findings.

## Final bounded correction review: `9195fe72` against `b95354c6`

**Final N1 spec verdict: approved. Final N1 quality verdict: approved within the reviewed slice. Both previously confirmed P2 findings are resolved; no remaining confirmed must-fix N1 findings. This verdict supersedes the earlier changes-required verdicts above.**

Reviewed the committed worker-only runtime change, three added regressions, and committed RED/GREEN evidence. Legacy inferred actor sets now always include the original post author, so a later surviving mention cannot weaken the old source-author block gate. All inferred actors still have to pass both block directions; NULL/invalid provenance remains unavailable. The explicit version-1 payload branch returns before this legacy handling, preserving its actual event actor rather than incorrectly adding the content author. Ordinary unchanged legacy fallback and legacy solved actor checks remain intact.

The committed tests reproduce both directions of the legacy block and also prove that an explicit new mention by an unblocked editor can still send even when the original content author is blocked. Evidence records RED **3 tests / 11 assertions / 2 failures**, then focused GREEN **73 tests / 416 assertions**. The implementer also reports the original `/tmp/N1LegacyActorReviewTest.php` reproduction now passes **1 test / 4 assertions**. These are inspected implementer results, not additional reviewer reruns. The bounded diff presents no further concrete concern requiring another test run.

No application edits or commits were made by the reviewer. N2 review and the parent's full final PHPUnit/browser/evidence matrix remain separate outstanding release gates; this approval does not claim those are complete.
