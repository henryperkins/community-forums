# Unified Notifications and Account Settings Implementation Plan

> **For agentic workers:** Use `superpowers:executing-plans` to implement this plan task by task. Steps use checkboxes for tracking. The user subsequently authorized implementation on 2026-09-20. The completed local branch and evidence are recorded below; deployment remains a separate action.

**Status:** Completed and locally verified on `codex/unified-notifications-settings`. [Combined evidence](../../evidence/unified-notifications-and-settings/README.md): 2,926 PHP tests, 224 browser passes, build checks and independent reviews. Checked steps record fulfilled behavior; embedded code remains the original planning sketch.

**Goal:** Unify the existing notification experience, repair its privacy/delivery failures, and close all nine account-settings review findings through the linked account workstream.

**Architecture:** Retain the existing notification/subscription tables, PHP forms, short polling, and email outbox. Centralize recipient eligibility and presentation; make digests replayable outbox jobs. Complete existing settings workflows without building the deferred preference platform.

**Tech Stack:** PHP 8.2+, MySQL/MariaDB, server-rendered PHP, progressive-enhancement JavaScript, Imladris CSS, PHPUnit, Playwright/axe.

**Spec:** [Unified notifications and account-settings repair design](../specs/2026-09-20-unified-notifications-and-account-settings-design.md). The [account-settings task plan](2026-09-20-account-settings-repairs.md) is part of this delivery, not optional follow-up.

## Global Constraints

- PHP 8.2+, existing MySQL/MariaDB schema and prepared statements; no new application framework or runtime dependency.
- Server-rendered forms and GET navigation work without JavaScript; progressive enhancement and short polling only.
- Strict CSP; no inline scripts/styles. Escape all new member-controlled names, labels, and drafts.
- Existing flags keep their defaults; disabling a feature hides its controls and gates its routes/worker source.
- Services own policy, repositories own queries, and new services are hand-bound in `App::buildContainer()` and explicit worker construction in `bin/console`.
- Shell lookups tolerate missing tables/unreachable DB and preserve pre-setup/health behavior.
- No notification matrix, quiet hours, preview/test-send, email editing, new staff inbox, or new event bus.
- No database migration is planned. Measure queries before proposing additional indexes; any justified migration is additive and updates `SCHEMA.md` and upgrade evidence.
- Use severity words for findings. P0–P3 remain product priority tiers, not bug severities or implementation phases.

## Review Focus

1. Access changes after an event is queued: revoked private membership, blocks in either direction, moved/deleted/held content, and assigned moderators must behave consistently in HTML, JSON, counts, clicks, and mail. Tests: N1 and N3.
2. Cross-account IDs and stale state: forged notification/subscription/feed IDs, stale tabs, pending deletion, and concurrent moderation must never expose content or restore write access. Tests: N2, A1, A4.
3. Time and failure boundaries: NULL timezone, invalid settings, DST, repeated scheduler invocations, transient failure, legacy jobs, and a retry after source disablement. Tests: N2, N3, A4.
4. Long histories and adversarial text: unread events beyond the first 30/100, many inaccessible rows, long names/titles, and anonymous authors. Tests: N1, N2, N4, N5.
5. Recovery and constrained clients: no JavaScript, mobile keyboard/touch, invalid TOTP/avatar/feed submissions, passwordless accounts, and absent later tables. Tests: A2–A5, N4–N6.

## Delivery order and review boundaries

| Order | Deliverable | Dependencies |
|---|---|---|
| 1 | **A1: close lifecycle restriction bypass** | None; independently releasable critical fix |
| 2 | **N1: private-content eligibility across reads and delivery** | None; independently releasable critical privacy fix |
| 3 | N2: owned actions, history, subscription management, UTC settings | N1 |
| 4 | N3: durable digest jobs and honest delivery outcomes | N1, N2 |
| 5 | A2: TOTP/passwordless recovery; A3: avatar draft retention | A1 for lifecycle UI state |
| 6 | A4: saved feeds, folders, retained validation, digest sources | N1, N3 |
| 7 | N4: shared notification center; N5: shared header counts | N1, N2 |
| 8 | A5: mobile settings navigation and session labels | Account forms above |
| 9 | N6: combined evidence, documentation, release rehearsal | All preceding tasks |

Keep changes reviewable at these boundaries. Critical server fixes must not wait for the visual consolidation. The two plans may be executed sequentially in one workspace; no parallel fresh-schema PHPUnit runs or shared browser fixture mutation.

## File and responsibility map

| Component | Files | Responsibility |
|---|---|---|
| Eligibility and reading | New `src/Service/NotificationVisibilityService.php`, `src/Service/NotificationReadService.php`; existing `NotificationRepository`, `ThreadReadService`, conversation repositories | Authorized recipient scope; shared list/count/open operations |
| Presentation | New `src/Support/NotificationPresenter.php`, `templates/partials/notification_list.php`, `templates/partials/notification_row.php` | One event label/icon map and accessible row model |
| Settings | New `src/Service/SubscriptionService.php`, `src/Service/NotificationSettingsService.php`; existing `SubscriptionRepository`, `SubscriptionController`, `SettingsController`, account notification template | Owned updates, state-independent opt-out, read-gated labels, channel/frequency settings |
| Email | New `src/Service/DigestService.php`, `src/Repository/DigestActivityRepository.php`; existing two notification workers, `EmailDeliveryRepository`, `EmailOpsService` | Fixed-window digest selection/rendering, durable scheduling, retries |
| Integration | `src/Core/App.php`, `bin/console`, notification/home controllers, topbars, `public/assets/app.js` | Wiring, commands, shared counts and entry points |
| Design and proof | `public/assets/app.css`, `resources/imladris/components.css`, generated assets and documented mirrors; focused tests, evidence, runbook | Responsive rendering, fidelity, regression and release proof |

The map is not a request to restructure unrelated repositories. Notification fan-out remains in `NotificationService`; do not move posting, moderation, or announcement business rules into controllers.

## Task N1: Share visibility rules and stop content disclosure

**Files:** Modify `src/Repository/NotificationRepository.php`, `src/Repository/SubscriptionRepository.php`, `src/Service/NotificationService.php`, `src/Worker/DailyDigestWorker.php`, `src/Worker/NotificationEmailWorker.php`, `src/Controller/NotificationController.php`, `src/Controller/HomeController.php`, `src/Controller/SettingsController.php`, `src/Core/App.php`, `bin/console`. Create the visibility/read services above. Test `tests/Integration/Core/AppNotificationTest.php`, new `tests/Integration/Core/AppNotificationPrivacyTest.php`, `tests/Integration/Worker/NotificationEmailWorkerTest.php`, and `tests/Integration/Worker/DailyDigestWorkerTest.php`.

**Interfaces:**

```php
// NotificationVisibilityService: memoized HTTP recipient context.
// Workers pass fresh=true on EVERY job/attempt; never reuse stale access across retries.
public function scope(\App\Domain\User $viewer, bool $fresh = false): array;
// Keys: user_id:int, is_admin:bool, member_board_ids:list<int>,
// assigned_board_ids:list<int>, features:array<string,bool>,
// may_review_dm_reports:bool (derived from the existing report-route authority).

// NotificationRepository: both methods share one internal eligibility predicate.
public function pageForScope(array $scope, bool $unreadOnly, ?int $beforeId, int $limit = 30): array;
public function unreadCountForScope(array $scope): int;

// NotificationReadService, consumed by both controllers and shell.
// Result: items:list<array>, unread:int, next_before:?int, unread_only:bool.
public function page(\App\Domain\User $viewer, bool $unreadOnly = false, ?int $beforeId = null, int $limit = 30): array;
public function unreadCount(\App\Domain\User $viewer): int;
```

- [x] Add a regression that makes an already-subscribed public board private, changes the topic title afterward, and checks both HTML lists, bell JSON, and settings. Use a marker that must never occur in any response:

```php
public function test_revoked_content_is_absent_from_every_notification_surface(): void
{
    $this->makeAdmin();
    $author = $this->makeUser();
    $member = $this->makeUser();
    $board = $this->makeBoard($this->makeCategory());
    $thread = $this->makeThread($board, $author, 'Original title');
    (new \App\Repository\NotificationRepository($this->db))->create([
        'user_id' => (int) $member['id'], 'type' => 'reply',
        'actor_id' => (int) $author['id'], 'thread_id' => $thread['thread_id'],
    ]);
    (new \App\Repository\SubscriptionRepository($this->db))->set(
        (int) $member['id'], 'thread', $thread['thread_id'], true, true, 'daily'
    );
    $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$board['id']]);
    $this->db->run('UPDATE threads SET title = ? WHERE id = ?', ['PRIVATE-AFTER-REVOCATION', $thread['thread_id']]);
    $this->actingAs($member);
    foreach ([
        ['/notifications', []],
        ['/', ['pane' => 'notices']],
        ['/notifications/bell', []],
        ['/settings/notifications', []],
    ] as [$path, $query]) {
        $response = $this->get($path, $query);
        $this->assertStatus(200, $response);
        if ($path === '/') {
            self::assertStringContainsString('data-directory-pane="notices"', $response->body());
        }
        self::assertStringNotContainsString('PRIVATE-AFTER-REVOCATION', $response->body());
    }
    $payload = json_decode($this->get('/notifications/bell')->body(), true, 512, JSON_THROW_ON_ERROR);
    self::assertSame(0, $payload['unread']);
}
```

- [x] Run `vendor/bin/phpunit --filter 'AppNotificationPrivacyTest|DailyDigestWorkerTest|NotificationEmailWorkerTest'`; confirm the new privacy cases fail because the marker is disclosed, not because a route returned 404. `TestCase::get()` accepts path and query separately; browser URLs may contain queries, in-process helper paths may not. Keep the pane-marker assertion so a silently selected Boards pane cannot produce a false pass.
- [x] Build and memoize the recipient scope once per HTTP request from current memberships, assigned-board authority, and route feature/authority gates. Each worker attempt explicitly requests a fresh scope. Centralize the prepared eligibility predicate used by page and count. Join content safely; filter deleted/pending content and applicable block directions before `LIMIT`, and never reuse a named PDO placeholder. Actor-block exemptions are exactly `mod`, `announcement`, and `badge`; target authorization is never exempt. Clamp/concatenate limits and cursor integers.
- [x] Route HTML/JSON/counts through `NotificationReadService`; move no policy into templates. Wire the same scope into current digest selection and instant-mail send checks now, before the later outbox refactor. Reuse canonical `ThreadReadService` on final target resolution and test query eligibility against its readable public/hidden/private cases.
- [x] Include authorized admin and assigned-moderator positives, suspended assigned-moderator read access, anonymous-author masking, thread move, post deletion, held content, both block directions, and retained conversation membership. With `dms=false`, exclude DM notices; with `dms=true` and `group_dms=false`, keep owned existing group notices readable/openable, matching `ConversationController::show()`. Test a blocked broadcasting admin's announcement in list, count, and bell, plus blocked-actor appeal resolution and actorless badge notices. None may reveal inaccessible target details.
- [x] Seed more than 30 inaccessible rows ahead of a readable row; assert a full eligible page and matching count. Capture query plans/query counts for a member with 10,000 notifications and 100 subscriptions: no per-row membership queries and no full result materialization to count unread. Record results instead of inventing a timing threshold.
- [x] Rerun the focused tests and record the critical privacy fix as independently reviewable. No sender configuration/domain-block behavior may change.

## Task N2: Repair targets, owned actions, history, and preference writes

**Files:** Modify notification/read services, `NotificationRepository`, `SubscriptionRepository`, `NotificationController`, `SubscriptionController`, `SettingsController`, `ThreadController`, `src/Core/App.php`, `templates/account/notifications.php`, `templates/partials/thread_tools.php`. Create `src/Service/SubscriptionService.php` and `src/Service/NotificationSettingsService.php`. Test `AppNotificationTest.php`, `AppNotificationPrivacyTest.php`, `AppUserPreferencesTest.php`, `AppAccountConsoleTest.php`, and existing appeal integration tests for resolution clicks.

**Interfaces:**

```php
// Owned lookup is independent of the recent-list window.
public function findOwned(int $userId, int $notificationId): ?array;
// NotificationReadService::open returns outcome opened|acknowledged|unavailable.
public function open(\App\Domain\User $viewer, int $notificationId): array;
// Result: outcome:string, url:?string. Foreign/missing ID throws NotFoundException.

// SubscriptionService; updateOwned receives the subscription row ID, not a user ID.
public function listForUser(\App\Domain\User $viewer): array;
public function updateOwned(\App\Domain\User $viewer, int $subscriptionId, array $input): void;
public function updateTarget(\App\Domain\User $viewer, string $targetType, int $targetId, array $input): void;
// New POST /settings/notifications/subscriptions/{id}; existing /t/{id}/subscribe
// and /b/{id}/subscribe delegate to updateTarget for the same validation/policy.

// NotificationSettingsService: transactional digest/timezone/pause mutation;
// reduces delivery without WriteGate, otherwise requires it.
public function update(\App\Domain\User $viewer, array $input): void;
// SettingsController: reused by both settings and subscription controllers on 422.
public function notificationsView(\App\Domain\User $viewer, array $data = [], int $status = 200): \App\Core\Response;
```

- [x] Add the >100-row regression using existing test helpers:

```php
public function test_old_owned_notification_can_still_be_opened(): void
{
    $member = $this->makeUser();
    $repo = new \App\Repository\NotificationRepository($this->db);
    $oldId = $repo->create(['user_id' => (int) $member['id'], 'type' => 'badge']);
    for ($i = 0; $i < 105; $i++) {
        $id = $repo->create(['user_id' => (int) $member['id'], 'type' => 'badge']);
        $repo->markRead((int) $member['id'], $id);
    }
    $this->actingAs($member);
    $response = $this->post('/notifications/' . $oldId . '/read');
    $this->assertRedirect($response, '/u/' . $member['username']);
}
```

- [x] Add HTTP cases for DM open, a foreign notification ID, an inaccessible owned target, accessible and inaccessible subscription Off, thread-Off-over-board precedence, and read-all/clear return paths. Resolve a real appeal with `AppealService`, open its targetless `mod` notification, and assert `/appeals` shows the member's resolution. Confirm current failures with `vendor/bin/phpunit --filter 'AppNotificationTest|AppNotificationPrivacyTest|AppUserPreferencesTest|AppAccountConsoleTest|AppAppeal'`.
- [x] Replace `recent(100)` target search with `findOwned`. Resolve every stored type: `reply`, `new_thread`, legacy `new_post`, `mention`, `reaction`, `solved` → authorized topic/post; `dm` → permitted conversation, including retained group history when only `group_dms` is off; `follow` → available actor profile; `badge` → recipient profile; `announcement` → home; `mod` with a target → its authorized target. Targetless `mod` is the current appeal-resolution producer: route to `/appeals` when enabled; only with `appeals=false` use a generic acknowledgment. No appeal ID is stored, so do not guess one. Unsupported/malformed content is unavailable. Mark read only after resolution/acknowledgment; stale inaccessible targets get neutral feedback, foreign IDs get 404.
- [x] Implement All/Unread GET query `filter=all|unread` and `before={positive notification id}` for both entry points. Use descending-ID keyset paging with one extra eligible row, stable Next/Latest links, and direct ID clicks. Malformed cursors normalize to first page; mutation return values accept only the known notification routes and validated query keys, not arbitrary local/external URLs.
- [x] In `SubscriptionService`, establish ownership and validate input before classifying the change. Off or removal of an existing channel without enabling another is a delivery reduction: allow it for any authenticated account state, even when content is inaccessible. Enabling a channel, changing live frequency, or other escalation requires `WriteGate` and current content access. Mixed reduction/escalation requests fail atomically if restricted. Persist Off rather than deleting an override; all-channel-disabled normalizes to Off rather than silently enabling in-app. A legacy target route may turn off only the authenticated user's existing row; no read gate or slug lookup may precede that reduction. For an inaccessible success redirect safely to settings.
- [x] `SettingsController::updateNotifications()` catches `ValidationException` from `NotificationSettingsService::update()` and calls `notificationsView($user, ['errors' => $e->errors, 'old' => $e->old], 422)`. The new subscription action in `SubscriptionController` catches its service exception and uses the same renderer with a subscription-specific old/errors bag and row ID. Legacy thread subscription errors use the existing `ThreadController::renderThread($request, $authorizedThread, ['subscription_errors' => $e->errors, 'subscription_old' => $e->old])->withStatus(422)` and render those fields in `thread_tools.php`. If that target became inaccessible, render the neutral owned row in settings instead. The legacy board endpoint has no board-page subscription form today; its validation errors also render the shared settings form with its target/draft. No exception falls through to the kernel and no validation failure redirects away the draft.
- [x] Render the existing frequency/channel controls in settings and preserve settings context after success. `NotificationSettingsService` normalizes empty zone to UTC, validates Off or integer 0–23, and retains ordinary input on 422. Digest Off and global pause=true are allowed reductions in every authenticated state; unpausing, enabling a digest, or changing its active schedule requires `WriteGate`. Keep signed unauthenticated email unsubscribe intact. Global pause never clears bounce suppression. N3 separately fixes recipient selection for legacy NULL zones, without requiring a settings re-save.
- [x] Add NULL/empty UTC, malformed timezone/hour, all-channel-disabled, and CSRF cases. For each `suspended`, `banned`, `deactivated`, and `pending_deletion` fixture, assert owned Off/channel-disable/digest-Off/pause succeed while enable/unpause/change-frequency and mixed requests return 403 without partial mutation. Include queued instant/digest jobs, reduce delivery before retry, and prove no captured mail arrives. Run both new settings and legacy subscription routes with accessible and revoked targets, foreign IDs, and no JavaScript. Rerun the focused group, including a correctly separated path/query request that finds the old unread row.

## Task N3: Make every digest replayable and delivery status truthful

**Files:** Create `src/Service/DigestService.php`, `src/Repository/DigestActivityRepository.php`. Modify `src/Worker/DailyDigestWorker.php`, `src/Worker/NotificationEmailWorker.php`, `src/Repository/EmailDeliveryRepository.php`, `src/Service/EmailOpsService.php`, `src/Controller/AdminEmailController.php`, `templates/admin/email.php`, `src/Core/App.php`, `bin/console`, `ADMIN.md` §7.6, and `docs/runbooks/operations.md` §3. Test both worker test files, `tests/Integration/Repository/EmailOpsRepositoryTest.php`, `tests/Integration/Service/EmailOpsServiceTest.php`, `tests/Integration/Admin/AppAdminEmailTest.php`, and purge/outbox interaction in `AppAccountLifecycleTest.php`.

**Interfaces:**

```php
// DigestService
public function snapshot(\App\Domain\User $viewer, string $fromUtc, string $toUtc, int $maxPostId): array;
public function render(?\App\Domain\User $viewer, array $payload): ?array;
// render returns subject:string, text:string, html:?string; null means no eligible content.
// null viewer returns null defensively; the worker records recipient_missing first.
// Invalid version/shape throws ValidationException, caught as a permanent job failure.

// Version 1 payload (source filters contain IDs/settings, never rendered content):
$payload = [
    'version' => 1,
    'window_start_utc' => '2026-09-19 09:15:00',
    'window_end_utc' => '2026-09-20 09:15:00',
    'max_post_id' => 1200,
    'sources' => [
        'subscriptions' => [['target_type' => 'thread', 'target_id' => 42]],
        'saved_feeds' => [], // A4 supplies id + original filter_json when enabled.
    ],
];

// Extend existing worker/repository APIs; kind null drains all supported kinds.
// NotificationEmailWorker::run returns existing counters plus blocked_reason:?string.
// blocked_reason is sender_unconfigured|domain_unverified|null, not a per-job error.
public function run(int $limit = 100, ?string $kind = null): array;
// EmailDeliveryRepository
public function pending(int $limit = 100, ?string $kind = null): array;
public function markSuppressed(int $id, ?string $reason = null): void;
```

- [x] Add a transient-failure regression to `DailyDigestWorkerTest` using its existing `makeDigestUser()` and `worker()` helpers, updated to construct the shared drainer. Widen the worker helper argument from `ArrayMailer` to `Mailer` so the unconfigured transport tests can use `new SendmailMailer('')` without sending:

```php
public function test_failed_digest_retries_without_scheduling_a_duplicate(): void
{
    $author = $this->makeUser();
    $recipient = $this->makeDigestUser(9);
    $thread = $this->makeThread($this->makeBoard($this->makeCategory()), $author);
    $this->db->run('UPDATE posts SET created_at = ? WHERE thread_id = ?', ['2026-09-20 08:00:00', $thread['thread_id']]);
    (new \App\Repository\SubscriptionRepository($this->db))->set(
        (int) $recipient['id'], 'thread', $thread['thread_id'], true, true, 'daily'
    );
    $mailer = new \App\Mail\ArrayMailer();
    $mailer->failNext = true;
    $this->worker($mailer)->run('2026-09-20 09:15:00');
    self::assertSame(0, $mailer->count());
    $job = $this->db->fetch("SELECT * FROM email_deliveries WHERE user_id = ? AND kind = 'digest'", [$recipient['id']]);
    self::assertSame('queued', $job['status']);
    self::assertNotNull($job['payload']);
    $this->db->run('UPDATE email_deliveries SET next_attempt_at = NULL WHERE id = ?', [$job['id']]);
    $this->worker($mailer)->run('2026-09-20 09:20:00');
    self::assertSame(1, $mailer->count());
    self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM email_deliveries WHERE user_id = ? AND kind = 'digest'", [$recipient['id']]));
}
```

- [x] Run `vendor/bin/phpunit --filter 'DailyDigestWorkerTest|NotificationEmailWorkerTest|EmailOpsRepositoryTest|EmailOpsServiceTest|AppAdminEmailTest'`; verify the new replay/status cases fail before refactoring.
- [x] Extract digest selection/rendering, preserving current branding and unsubscribe behavior. Use N1 eligibility for subscription candidates and a fresh scope at every final render. Freeze UTC bounds and source selection; enforce `created_at > start AND created_at <= end AND id <= max_post_id`. **Replace the recipient predicate with `WHERE digest_hour IS NOT NULL AND status <> 'deleted'`**: remove the NULL-timezone exclusion and bring banned rows into the due-window consumption path without scheduling mail for them. Select `suspended_until` with the current state, and normalize NULL/empty zone to UTC before due evaluation. Do not require legacy users to re-save settings or add a backfill migration. Invalid non-empty stored zones get an observable per-recipient skip without a watermark change. Give existing historical-date worker fixtures explicit activity timestamps inside their frozen windows.
- [x] Replace `testNotDueOutsideTheDigestHour` deliberately: its current 15:00/09:00 expectation contradicts the new late-cron policy. Add separate before-hour (zero), after-hour first run (one), same-date repeat (zero), spring-gap, and fall-fold cases. Change the test name and preserve an assertion that distinguishes early from late; do not merely delete the old test.
- [x] Under a recipient row lock, recompute whether a local-date job exists; enqueue with `digest:{uid}:{local_date}` and update the consumed watermark atomically. Due means at or after the scheduled local hour on that date; spring gaps send at the first valid time afterward, fall folds schedule once, and timezone changes cannot reverse UTC bounds. Member pause/suppression or banned/deleted state consumes a due window without catch-up mail. An unconfigured sender or blocked domain is different: **schedule an otherwise eligible job before consulting transport guards**, then leave it queued. A failed scheduling transaction leaves the old watermark; test actual rollback with a committed fixture outside the harness's outer transaction.
- [x] Make transport checks the shared drainer's responsibility. On an unconfigured sender or unverified domain, return `blocked_reason`, send nothing, increment no job attempt, and preserve queued payload/window/error. Remove worker calls to `markQueuedBlocked()` rather than using `error` for global configuration state. Existing admin transport/domain status supplies the banner; CLI prints the block reason. Update tests and operations prose that expected a domain guard to overwrite every queued row's error. No new schema field is needed.
- [x] Move recipient lookup, preference/suppression checks, payload decode, rendering, send, and status recording inside the per-row `try/catch`. For instant/digest/announcement rows, require a current non-deleted user: NULL `user_id` after purge or missing user → `suppressed/recipient_missing`; deleted → `recipient_deleted`; banned → `recipient_banned`; unknown state fails closed. Active, suspended, deactivated, and pending-deletion users retain delivery only while their preferences permit it. Reload this state before **every** attempt; never reuse a cached `User` or visibility scope from a failed attempt. A purged recipient must not reach a non-nullable call or abort later jobs.
- [x] Dispatch explicitly by kind, keeping instant and announcement semantics and rendering `test` as the existing operator test message rather than trying an instant post key. Re-evaluate effective Off/email-disabled preferences for queued instant posts as well as digest sources; absence of a subscription must not accidentally suppress independently eligible mention/solved notifications. Catch malformed/version-invalid digest payloads as permanent `failed/invalid_digest_payload`, and missing legacy digest payloads as `failed/unreplayable_legacy_digest`. Other rendering/transport exceptions use the existing retry schedule. Continue to the next valid row after a recorded failure; inability to record because the DB is unavailable is a surfaced batch error. A successful send alone gets `sent_at` and a message ID.
- [x] Make `worker:digest` schedule and drain due digest jobs, even when no new recipient is due. Keep `worker:email` able to drain digest retries. Preserve one advisory drain lock across both commands and update both container and CLI construction. Report queued, sent, suppressed/skipped, retrying, failed, and global blocked reason distinctly. Keep terminal reason codes in `error`; `markSuppressed` clears any next attempt and leaves `sent_at`/`message_id` NULL for unsent rows.
- [x] Declare `suppressed` terminal in the repository, service, admin UI, and CSV documentation. Admin requeue accepts only replayable `failed` jobs; it rejects suppressed rows and permanent malformed/legacy digest failures through a direct POST as well as the hidden/disabled control. Removing a recipient suppression enables future jobs, not replay of opted-out activity. Requeue a valid failed digest through the actual admin route and verify one captured email after draining. Remove/replace the blanket failed-row SQL replay advice in `docs/runbooks/operations.md`, which bypasses this classification. Update ADMIN §7.6 with queued/terminal outcome meanings and the transport-success/crash duplicate-delivery limitation.
- [x] Add all cases in the outcome table below, including a full `run()` for a legacy NULL-zone recipient, not only an `isDue()` unit test. Pair a missing/banned/malformed first row with a valid later row to prove batch progress. Also test revoke/block/delete/pause/source disablement between attempts, duplicate scheduling, digest Off, large windows, DST, and a permanent transport failure followed by explicit requeue. Do not infer exactly-once SMTP from queue deduplication.
- [x] Rerun worker/admin tests. Capture sanitized structured results for the audit matrix; no real email is sent during automated verification.

**Required outcomes (assert database state and captured transport calls):**

| Condition | Scheduled/drained outcome |
|---|---|
| Legacy `timezone=NULL`, due hour, eligible activity, no settings POST | One UTC digest; subsequent same-local-date run creates no second job |
| Due activity, `SendmailMailer('')` | Durable queued payload + watermark; zero sends/attempts; `blocked_reason=sender_unconfigured` |
| Due activity, domain verification blocked | Same durable queue outcome; `blocked_reason=domain_unverified`; prior per-job error is unchanged |
| Configuration recovers | Existing fixed-window job sends once through the capture mailer; no duplicate scheduling |
| Purged/NULL-ID or missing recipient followed by valid job | First suppressed as `recipient_missing`; second still sent; no batch TypeError |
| Ban imposed after first transport failure | Same job becomes suppressed as `recipient_banned`, zero additional mail; later jobs still processed |
| Global pause/digest Off/address suppression, or the final eligible source is disabled before retry | Terminal suppressed reason matches the cause; no operator replay after restoration |
| One source disabled while other digest sources remain eligible | Remove only that source's activity; send the remaining eligible digest, never the opted-out content |
| Invalid payload or unreplayable legacy digest followed by valid job | First permanently failed and non-requeueable; second processed normally |
| Replayable failed transport job | Admin requeue resets retry state; each later attempt rechecks recipient/content eligibility |

## Task N4: Render both notification screens with one component

**Files:** Create `src/Support/NotificationPresenter.php`, `templates/partials/notification_list.php`, `templates/partials/notification_row.php`, `tests/browser/notifications-unified.spec.ts`, `tests/browser/notifications-unified-fixture.php`. Modify `templates/notifications.php`, `templates/home.php`, `templates/account/notifications.php`, the two controllers, `public/assets/app.css`, and the Imladris source/mirror when shared component styles change. Update the existing class/selector contracts in `tests/Integration/Core/AppImladrisFidelityHighImpactTest.php`, `AppAnonymousPostingTest.php`, and `AppForumIndexRemediationTest.php`. Test new `tests/Unit/Support/NotificationPresenterTest.php`, notification integration tests, and **`tests/browser/board-index-remediation.spec.ts`**, which owns Notices evidence (not the separate Inbox topic-queue spec).

**Interfaces:** `NotificationPresenter::item(array $authorizedRow, User $viewer): array` returns only `id`, `type`, `icon`, `message`, `context`, `is_read`, `created_at`, `relative_time`, `full_time`, and `action_label`. `created_at` is the stored UTC DATETIME; the template converts it with existing `iso_datetime()` for the HTML attribute. The shared list partial consumes N1's page result, a validated `base_path`, and `return_path`; it never receives a repository or checks permission. Add `data-notification-list` as a stable test/PE hook.

- [x] Add presenter tests for all stored types, masked anonymous actors, missing actors, current targetless appeal resolutions, long labels, and escaped output at the template boundary. Add browser assertions for the same fixture at both entry points, including one unread event older than 35 read events and a parseable ISO timestamp carrying UTC information.
- [x] Verify baseline failures with `vendor/bin/phpunit --filter 'NotificationPresenterTest|AppNotificationTest|AppImladrisFidelityHighImpactTest|AppAnonymousPostingTest|AppForumIndexRemediationTest'` and the new focused browser spec on its isolated fixture. These existing tests are part of N4's focused gate, not failures to discover only in the full suite.
- [x] Extract the event vocabulary and row markup once. Use real POST forms for opening/acknowledging, “Unread” screen-reader text, `<time datetime>`, matching bulk-action states, All/Unread controls, history links, and a Notification settings link. Keep the former Notices URL valid and preserve pane return behavior.

```php
<?php if (!$item['is_read']): ?>
    <span class="notification-unread-dot" aria-hidden="true"></span>
    <span class="sr-only">Unread. </span>
<?php endif; ?>
<span class="notification-message"><?= $e($item['message']) ?></span>
<time datetime="<?= $e(iso_datetime((string) $item['created_at'])) ?>" title="<?= $e($item['full_time']) ?>">
    <?= $e($item['relative_time']) ?>
</time>
```

- [x] Make the timestamp secondary on mobile and allow topic/message text to wrap without clipping controls. Underline prose links persistently. Preserve established Parchment/Twilight colors and focus tokens. Update `AppImladrisFidelityHighImpactTest`'s icon/body/dot assertions to the actual shared component, `AppAnonymousPostingTest::notificationList()` to extract only `[data-notification-list]` while retaining all masking checks, and `AppForumIndexRemediationTest`'s CSS selectors to the emitted shared classes. Remove old CSS only alongside those deliberate contract updates; do not weaken the privacy or styled-selector assertions.
- [x] Run desktop 1280/1440, mobile 390 and stress 320 widths, 40/64-character names, long/unbroken titles, 200% zoom, keyboard and JavaScript-disabled actions. Assert accessible unread names, disabled Mark all read at zero, reachable history, no clipped focus/touch targets, and no axe link-color failure. Do not substitute screenshot existence for geometry/behavior assertions.
- [x] Capture/open fresh images of populated, empty, unread-only, inaccessible-content and mobile states on both surfaces. Run `board-index-remediation.spec.ts`'s account-adjacent-pane checks and deliberately refresh `docs/evidence/imladris-board-index-remediation/05-pane-notices.png` from the reviewed matching desktop capture; keep its README/ADR evidence reference current. N6 isolates routine captures so other slices' committed PNGs are not overwritten as a side effect. Rerun the expanded focused PHPUnit group and browser checks.

## Task N5: Unify the visible bell and all unread badges

**Files:** Modify `src/Core/App.php` (`shareViewGlobals`), `templates/partials/topbar.php`, any admin topbar containing `data-bell`, `public/assets/app.js`, `public/assets/app.css`, shared Imladris chrome styles if needed. Preserve and test the existing `public/assets/tour.js` notification-step selector. Test `tests/Integration/Core/AppNotificationTest.php`, existing pre-setup/DB-down tests, `tests/browser/notifications-unified.spec.ts`, `tests/browser/unified-chrome.spec.ts`, and account tour replay coverage.

**Interfaces:** Shell global `notification_unread: Closure(): int` is lazy and memoized within a request, returning zero on missing user/disabled feature/lookup failure. It calls the request-memoized `NotificationReadService::unreadCount(User)` only when a rendered component needs a badge. N1 scope and count caching are per HTTP request; workers explicitly request fresh scope. Every count uses `data-notification-count`; entry links use `data-notification-link`. Keep **`data-bell` on the visible primary link** for `tour.js`, and retain the bell endpoint's existing `unread`/safe-items contract.

- [x] Add an HTTP assertion that the initial page contains the real unread count before JavaScript. Add browser checks with the account menu closed and JavaScript disabled; include a member and admin, zero and 100+ counts, and a long account name.
- [x] Render a persistent header link, locally anchored badge, accessible full count, and visual `99+` cap. Templates invoke the guarded lazy closure only when showing a count; `shareViewGlobals` must not execute the query eagerly. Cache zero as well as nonzero results. The account menu's shortcut and pane/header counts share the same cached result.
- [x] Replace single-element polling updates with all matching count/link nodes; preserve current visibility pause, backoff, and dark-route behavior. Counts may update without replacing focused rows or generating a live-region announcement every minute.

```javascript
document.querySelectorAll('[data-notification-count]').forEach((node) => {
  node.textContent = unread > 99 ? '99+' : String(unread);
  node.hidden = unread === 0;
});
document.querySelectorAll('[data-notification-link]').forEach((node) => {
  node.setAttribute('aria-label', unread === 0 ? 'Notifications' : `Notifications, ${unread} unread`);
});
```

- [x] Verify safe defaults inside the closure when later tables are missing or DB is down; health/setup paths still respond. Add query-budget checks: plain/auth/health and unrelated JSON endpoints incur no notification count work, `/notifications/bell` resolves scope/count once, and a rendered header plus pane/menu does not repeat the query. Verify feature-off and guest output has no dead entry or polling request.
- [x] Replay the tour with the account menu closed: the Notifications step must be present and highlight the visible bell. Keep the `data-bell` compatibility hook; migrating polling to new selectors is not permission to silently delete the tour target.
- [x] Run the notification and unified-chrome specs in both projects. Keep mobile search/compose/rail controls usable and focus rings unclipped. Record the persistent bell and later A4 rail groups as deliberate ADR 0032 adaptations; do not claim the original prototype already included them.

## Task N6: Close the combined audit with durable evidence

**Files:** Create `docs/evidence/unified-notifications-and-settings/README.md`, structured worker/HTTP results and reviewed screenshots beneath that directory, `docs/runbooks/unified-notifications.md`, and `tests/browser/run-notifications-settings.cjs`. Update `tests/browser/package.json`, capture-root handling in every spec listed below, `.github/workflows/browser-evidence.yml`, `USER.md`, `ADMIN.md`, `PRODUCT_DESIGN.md`, `docs/runbooks/operations.md`, `PHASE_5_STATUS.md`, `docs/history/PHASE_1-4_HISTORY.md` (forward reference, preserve historical facts), `AGENTS.md`'s stale status pointer, `docs/evidence/phase4-closeout/phase3-4-closeout-ledger.md`, ADR 0014/0028/0032 and proposed ADR 0035, and Imladris reconciliation/baseline files through the documented procedure. `PHASE_4_STATUS.md` does not exist; the Phase 1–4 records are archived in the history file, and `CLAUDE.md` already points to the current Phase 5 status.

- [x] Map every N1–N10 and A1–A9 entry to a regression test and fresh artifact. Mark N2/A2 as one shared remediation with coverage in settings and notifications. Preserve existing accepted carryovers in ADR 0014/0021. Use [proposed ADR 0035](../../adr/0035-member-settings-completion-carryovers.md) for the newly recorded security-activity and email-discoverability gaps and the remaining username/dropdown accounting; record its disposition rather than silently treating it as an accepted or shipped feature. Update USER/product/status/ledger links consistently.
- [x] Finish A1–A5 and verify integration between saved-feed sources, digest preferences, rail links, account states, and notification settings. No enabled UI may claim functioning behavior solely because its row is stored.
- [x] Make all eight specs below honor `RB_EVIDENCE_DIR`. `account-console` and `chamfer-removal` already do; `profile-surface`, `totp`, `unified-chrome`, and `board-index-remediation` currently hard-code their output roots and must gain the override before this command is used. Keep their old paths only as defaults when no override is supplied. The two new specs use the override from their first version. Create output directories explicitly. Add this package script and runner after the specs exist:

```json
"evidence:notifications-settings": "node run-notifications-settings.cjs"
```

```javascript
// tests/browser/run-notifications-settings.cjs
const { spawnSync } = require('node:child_process');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const database = process.env.DB_DATABASE ?? 'retroboards_unified_e2e';
if (!/^retroboards_unified_e2e(?:_[a-z0-9]+)*$/.test(database)) {
  throw new Error('Select a disposable retroboards_unified_e2e database.');
}
const evidenceRoot = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/unified-notifications-and-settings');
const suites = [
  'notifications-unified', 'account-settings-repairs', 'account-console', 'totp',
  'profile-surface', 'chamfer-removal', 'unified-chrome', 'board-index-remediation',
];
function run(command, args, env) {
  const result = spawnSync(command, args, { cwd: __dirname, env, stdio: 'inherit' });
  if (result.error) throw result.error;
  if (result.status !== 0) throw new Error(`${command} exited ${result.status ?? result.signal ?? 'without a status'}`);
}
for (const suite of suites) {
  for (const project of ['desktop', 'mobile']) {
    const env = {
      ...process.env, DB_DATABASE: database, APP_ENV: 'test',
      MAIL_DRIVER: 'array', MAIL_FROM: 'notification-evidence@example.test',
      RB_EVIDENCE_DIR: path.join(evidenceRoot, suite, project),
    };
    run('bash', ['prepare.sh'], env);
    run('npx', ['playwright', 'test', `${suite}.spec.ts`, `--project=${project}`], env);
  }
}
```

- [x] Prepare separate disposable browser and PHPUnit schemas and unique rate-limit/upload/package directories. Serialize schema resets. Provision `retroboards_unified_e2e` and its grants in CI before this runner; the existing CI database name does not satisfy the runner's explicit guard. Select a dedicated free `E2E_PORT` and matching local APP_URL/WebAuthn RP host; reject reuse of an unrelated server. Cleanup fixtures/files in `finally`, including when a subprocess fails. The runner reseeds per suite/project to prevent fixture contamination and partitions evidence paths so fixed screenshot names do not collide. It never writes account-console's earlier 15-PNG slice or other historical roots by default.
- [x] Run required verification after the final code change; retain complete logs and exit codes:

```bash
composer build:imladris
composer check:imladris
composer verify:imladris
MAIL_DRIVER=sendmail MAIL_FROM='' COMPOSER_PROCESS_TIMEOUT=0 composer test
```

```bash
# Run from tests/browser after selecting the isolated database/environment.
npm run evidence:notifications-settings
```

- [x] Check the Imladris source/mirror and runtime digest according to `docs/design-system/imladris/LOCAL_RECONCILIATION.md`; refreshing a baseline is a reviewed design-contract change, not a way to silence a failing check. Run the N4 focused class/anonymous/CSS tests before the full suite. Keep `profile-surface.spec.ts` in the gate for A3, `chamfer-removal.spec.ts` for N2's `select[name="timezone"]` focus/surface contract, and `board-index-remediation.spec.ts` for the real Notices pane. Include related composer/rail coverage when A4 changes the sidebar and admin-email coverage for N3. Review and deliberately promote the N4 canonical Notices image; historical evidence updates must be explicit.
- [x] Exercise both worker commands against a separately seeded disposable upgraded database containing valid queued digests, a NULL-user purged job, a legacy failed digest, paused/suppressed recipients, and a saved-feed subscriber. **Pin the transport on every rehearsal command**, not only the browser server:

```bash
APP_ENV=test DB_DATABASE=retroboards_unified_workers MAIL_DRIVER=array MAIL_FROM=notification-evidence@example.test php bin/console worker:digest
APP_ENV=test DB_DATABASE=retroboards_unified_workers MAIL_DRIVER=array MAIL_FROM=notification-evidence@example.test php bin/console worker:email 100
```

- [x] Capture CLI exit codes/counters and delivery-row state. Use the same shared `ArrayMailer` in in-process worker tests to retain message bodies for assertions; separate CLI processes do not retain its memory. Test unconfigured transport only with a deliberately unconfigured double/`SendmailMailer('')`, never a configured real transport. Use a cached/fake domain status for the blocked-domain case. If a measured index migration was introduced, run `php bin/console verify:upgrade` only against its dedicated scratch target and record the result.
- [x] Document release order: quiesce both email cron workers during the worker code switch, deploy the coherent code/bindings/templates/assets, run a captured local smoke test, then resume workers. Do not roll back to the known privacy or account-state bypass. If delivery fails after release, pause mail draining while retaining queued jobs and fixed in-app behavior.
- [x] Open and inspect final screenshots, record accessibility and no-JS outcomes, classify each browser skip, and clean up all disposable data/files and test-mutated state. Review `git status` so only intentional application/docs/evidence changes remain.

**Completion gate:** All audit rows have an implemented fix and passing evidence; the full PHPUnit process has exited successfully; relevant browser and Imladris checks pass; saved-feed digest behavior is exercised; carryovers and operational limits are explicit. Baseline passing tests or schema alone do not close a finding.

## Planning review

The plan incorporates the subsequent execution review. It now pins path/query handling, state-independent opt-out, NULL-recipient batch behavior, banned-recipient retries, legacy NULL-zone selection, schedule-before-transport ordering, per-job versus global error semantics, and terminal suppression. It names the live appeal producer, block-exempt event types, existing group-history behavior, lazy shell count, tour hook, exact collateral tests, output isolation, safe worker rehearsal, and current documentation paths. The design and account workstream use the same contracts. That planning revision changed documentation only. Subsequent implementation and final results are recorded in the combined evidence index above.
