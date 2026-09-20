# N2 independent spec and correctness review

Reviewed commit `2f8892d7` against `e4b5c0f9`. Review date: 2026-09-20. Read-only source review; no application/test edits or commits. This review excludes concurrent parent documentation/browser work and N1's separate legacy-actor correction.

## Verdict

**Spec: approved after correction `19c06f39`.** The sole target/redirect mismatch below is resolved. N2's owned actions, history, opt-out policy, settings validation and draft preservation conform to the reviewed contracts.

**Quality: approved; no grounded remaining N2 findings.** The original P2 was reproduced and subsequently closed by source review and a fresh run of all three focused correction tests (3 tests / 25 assertions, exit 0). Existing wider focused evidence remains 159 tests / 1,285 assertions. This is not a full-suite/release verdict.

## Closed finding (original review of `2f8892d7`)

### P2 — Private board assignment is treated as authorization for a destination that returns 404

**Primary location:** `src/Service/SubscriptionService.php:91-95` (specifically the `assigned_board_ids` exception at line 92).

**Related:** `src/Controller/SubscriptionController.php:58-60`; `src/Controller/BoardController.php:35-52`.

`targetUrl()` permits an assigned moderator who is not a board member to access a private board and returns `/c/{slug}`. The actual board controller only authorizes that route through `BoardPolicy::canRead()` with explicit membership, so this destination returns 404. The assigned-moderator exception belongs to the canonical **thread** read contract; applying it independently to the board destination does not change the destination's real authorization.

Fresh reproduction on the configured dedicated test DB:

1. Create a moderator and private board; assign the moderator via `BoardModeratorRepository::assign`, without inserting `board_members`.
2. Seed the moderator's existing board subscription as Daily with both channels enabled.
3. Confirm GET `/c/{slug}` returns 404.
4. POST `/b/{id}/subscribe` with `frequency=off`.
5. The subscription correctly becomes Off, but the response redirects to `/c/{slug}` and following it returns 404. N2 requires an inaccessible success to return safely to settings.

The same helper also authorizes non-reduction board subscription writes, even though its stated destination is unreadable. That secondary inconsistency follows directly from `apply()` calling `targetUrl()` at line 75; no unauthorized content disclosure was demonstrated.

**Fix direction:** Align board target authorization and success redirects with the actual board read policy. The narrow N2 fix is to use BoardPolicy plus real membership for the board route and preserve the ThreadReadService assignment exception for thread targets. Do not apply a content read gate before an owned reduction. If product intent is instead to broaden board-page reading for assignments, implement that change deliberately across the canonical board route and tests rather than silently relying on this helper. Add a private assigned/non-member board case asserting that Off succeeds and returns `/settings/notifications`; ensure escalation follows the selected authoritative board policy.

**Reproduction evidence:** Temporary, outside-repository `/tmp/N2ReviewProbeTest.php`; command:

```sh
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' DB_TEST_DATABASE=retroboards_unified_test vendor/bin/phpunit /tmp/N2ReviewProbeTest.php
```

Result: **1 test, 5 assertions, exit 0**. Assertions intentionally confirm the observed defect (404 before, redirect to that same board after Off, 404 afterward, Off persisted). No mail was sent. The temporary test was removed after review.

## Correction re-review: `19c06f39`

Bounded re-review completed with N1 final `9195fe72` also present; independent N3 work was excluded.

- `SubscriptionService::targetUrl()` now uses the actual `BoardPolicy` plus `BoardMemberRepository::isMember()` for board targets. App explicitly binds the added dependencies. BoardController permissions were not widened.
- Owner reductions still execute before the target read check. The controller catches the subsequent board NotFoundException and redirects to `/settings/notifications`; Off remains persisted with both channels disabled.
- Board escalations through both modern owned-row and legacy target routes now fail 404 without changing the persisted subscription for an assigned non-member on a private board.
- Settings projection now clears assignment IDs only for the board-target predicate. Such board rows are neutral/unlinked. The thread-target predicate retains assignment access and the canonical thread service remains unchanged, so assigned moderators retain readable thread subscriptions, links and updates.
- The three new regressions assert Off and safe redirect, modern/legacy escalation refusal with unchanged data, preserved thread reading/updating, neutral board labels, and restored board visibility when actual membership is added.

Fresh serialized verification (configured dedicated test DB; sender empty):

```sh
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' DB_TEST_DATABASE=retroboards_unified_test vendor/bin/phpunit --filter 'test_assigned_nonmember_private_board_off_returns_to_accessible_settings|test_assigned_nonmember_board_escalation_denied_while_thread_assignment_is_readable|test_subscription_labels_distinguish_private_board_and_thread_read_authority' tests/Integration/Core/AppNotificationPreferencesRepairTest.php
```

**Result: OK (3 tests, 25 assertions), exit 0, 0.119 seconds.** No runtime edits or mail transport were performed by this review. The sole finding is closed.

## Verified contracts and evidence

- Direct `findOwned` establishes ownership independently of list size; foreign/missing IDs fail 404 before mutation. `open()` refreshes visibility, resolves content, and only then marks read. Unavailable content remains unread. The >100 regression and inaccessible/foreign HTTP assertions exercise these paths.
- Thread/post notices use canonical ThreadReadService after SQL publication/access/block eligibility. Follow, badge, announcement, DM and targeted moderation branches are explicit; unsupported or malformed target shapes do not receive an arbitrary redirect.
- Targetless moderation notices resolve to `/appeals`; the changed appeal integration case resolves a real appeal and checks its member-visible resolution. Disabling appeals produces the acknowledgment fallback. No invented appeal identifier is used.
- DM notices use historical conversation membership and time/message visibility bounds; `dms=false` fails closed while `group_dms=false` preserves retained history. Report notices use the shared REPORT_HANDLE scope, not mere conversation ownership. Existing privacy tests plus N2 click assertions cover these distinctions.
- Both history entry points pass path and query separately, use All/Unread descending-ID keyset paging with an extra eligible row, and expose Next/Latest. Cursors normalize safely. Mutation returns reconstruct only the two notification routes and permitted validated query fields.
- Subscription updates lock owner-scoped rows, validate before classification, preserve Off overrides, and normalize both disabled channels to Off. Existing row reductions bypass WriteGate/read gates; mixed/enabling/frequency changes perform those checks before the single mutation. Modern and legacy routes use the same service. Four restricted states, revoked access, foreign ownership and CSRF are covered.
- Notification settings validate ordinary and malformed timezone/hour/pause inputs before a transaction, reload the user with `findForUpdate`, compare current settings, and apply WriteGate to effective escalation. Digest and pause changes occur together; empty/NULL zones normalize to UTC. Bounce suppression and signed unsubscribe remain untouched.
- Settings and subscription controllers catch ValidationException. Shared settings forms retain escaped ordinary drafts; accessible legacy thread failures preserve draft/error bags in thread tools; unavailable thread and board failures use neutral settings fallbacks. Restricted accounts retain the watch section. Array inputs do not undergo unsafe template casts.
- Inspected `docs/evidence/unified-notifications-and-settings/n2-phpunit-green.log`: 159 tests / 1,285 assertions, exit-success output. The two A1 copy assertion changes retain their 422 checks and are consistent with the reported prior A1 message change.
- Parent-reported four no-JS restricted opt-out cases and populated light/dark accessibility checks are supporting evidence supplied to this review, not claimed as independently rerun.

## Explicitly separate gates

N3 owns combined queued instant/digest retry-after-opt-out scenarios and worker state/transport behavior. N4 owns final shared notification presentation and visual integration. Neither is reported as a missing N2 feature. Final full PHPUnit, wider browser evidence, Imladris reconciliation and cleanup remain parent release gates.
