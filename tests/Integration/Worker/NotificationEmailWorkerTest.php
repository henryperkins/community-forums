<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Mail\ArrayMailer;
use App\Mail\SendmailMailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
use App\Repository\UserPreferenceRepository;
use App\Service\EmailPreferenceService;
use App\Worker\NotificationEmailWorker;
use Tests\Support\TestCase;

/**
 * Instant email worker (P2-04): at-most-once delivery per (post, recipient),
 * suppression, fail-closed transport, and failure recording.
 */
final class NotificationEmailWorkerTest extends TestCase
{
    private function worker(ArrayMailer|SendmailMailer $mailer): NotificationEmailWorker
    {
        return new NotificationEmailWorker(
            new EmailDeliveryRepository($this->db),
            new EmailSuppressionRepository($this->db),
            new PostRepository($this->db),
            $this->users(),
            $mailer,
            $this->config,
            new SettingRepository($this->db),
            null,
            new EmailPreferenceService(new UserPreferenceRepository($this->db)),
        );
    }

    /** @return array{post_id:int, user:array<string,mixed>} */
    private function queuedDelivery(): array
    {
        $author = $this->makeUser();
        $recipient = $this->makeUser(['email' => 'r@example.test']);
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Email me', 'OP.');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        (new EmailDeliveryRepository($this->db))->enqueue((int) $recipient['id'], 'r@example.test', 'instant', null, $postId . ':' . (int) $recipient['id']);
        return ['post_id' => $postId, 'user' => $recipient];
    }

    public function testHeldPostIsCheckedAtSendTime(): void
    {
        $d = $this->queuedDelivery();
        $this->db->run('UPDATE posts SET is_pending = 1 WHERE id = ?', [$d['post_id']]);
        $mailer = new ArrayMailer();
        $this->worker($mailer)->run();
        self::assertSame(0, $mailer->count());
    }

    public function testBothBlockDirectionsAreRecheckedForQueuedMail(): void
    {
        $d = $this->queuedDelivery();
        $author = (int) $this->db->fetchValue('SELECT user_id FROM posts WHERE id = ?', [$d['post_id']]);
        $recipient = (int) $d['user']['id'];
        $blocks = new \App\Repository\BlockRepository($this->db);
        $mailer = new ArrayMailer();
        $worker = $this->worker($mailer);
        foreach ([[$author, $recipient], [$recipient, $author]] as [$a, $b]) {
            $blocks->block($a, $b);
            $this->db->run("UPDATE email_deliveries SET status = 'queued', next_attempt_at = NULL WHERE user_id = ?", [$recipient]);
            $worker->run();
            self::assertSame(0, $mailer->count());
            $blocks->unblock($a, $b);
        }
    }

    public function testWorkerRefreshesAssignedModeratorAccessBetweenAttempts(): void
    {
        $d = $this->queuedDelivery();
        $recipient = (int) $d['user']['id'];
        $board = (int) $this->db->fetchValue('SELECT t.board_id FROM threads t JOIN posts p ON p.thread_id = t.id WHERE p.id = ?', [$d['post_id']]);
        $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$board]);
        $this->db->run("UPDATE users SET status = 'suspended', suspended_until = '2099-01-01' WHERE id = ?", [$recipient]);
        $mods = new \App\Repository\BoardModeratorRepository($this->db);
        $mods->assign($board, $recipient);
        $mailer = new ArrayMailer();
        $worker = $this->worker($mailer);
        self::assertSame(1, $worker->run()['sent']);
        $mods->unassign($board, $recipient);
        $this->db->run("UPDATE email_deliveries SET status = 'queued', next_attempt_at = NULL WHERE user_id = ?", [$recipient]);
        self::assertSame(0, $worker->run()['sent']);
        self::assertSame(1, $mailer->count());
    }

    public function testSolvedEmailRespectsRecipientBlockingAccepter(): void
    {
        $this->checkSolvedBlockDirection(false);
    }

    public function testSolvedEmailRespectsAccepterBlockingRecipient(): void
    {
        $this->checkSolvedBlockDirection(true);
    }

    private function checkSolvedBlockDirection(bool $reverse, bool $blocked = true, bool $legacy = false, bool $retry = false): void
    {
        $actor = $this->makeUser();
        $recipient = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $actor);
        $postId = (new \App\Repository\PostRepository($this->db))->create([
            'thread_id' => $thread['thread_id'], 'user_id' => (int) $recipient['id'],
            'body' => 'Accepted answer', 'body_html' => '<p>Accepted answer</p>',
        ]);
        $notices = new \App\Repository\NotificationRepository($this->db);
        $deliveries = new \App\Repository\EmailDeliveryRepository($this->db);
        $suppression = new \App\Repository\EmailSuppressionRepository($this->db);
        $blocks = new \App\Repository\BlockRepository($this->db);
        $mailer = new \App\Mail\ArrayMailer();
        $service = new \App\Service\NotificationService($this->db, $notices,
            new \App\Repository\SubscriptionRepository($this->db), $deliveries,
            $suppression, $blocks, $this->users(),
            new \App\Core\FeatureFlags(new \App\Repository\SettingRepository($this->db)), $mailer);
        $service->notifySolved((int) $recipient['id'], (int) $actor['id'], $thread['thread_id'], $postId);
        $payload = json_decode((string) $this->db->fetchValue('SELECT payload FROM email_deliveries WHERE user_id = ?', [$recipient['id']]), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame((int) $actor['id'], $payload['actor_id']);
        self::assertSame('solved', $payload['event_type']);
        if ($legacy) {
            $this->db->run('UPDATE email_deliveries SET payload = NULL WHERE user_id = ?', [$recipient['id']]);
        }
        if ($retry) {
            $mailer->failNext = true;
            $worker = $this->worker($mailer);
            self::assertSame(1, $worker->run()['retrying']);
            $this->db->run('UPDATE email_deliveries SET next_attempt_at = NULL WHERE user_id = ?', [$recipient['id']]);
        }
        if ($blocked) {
            $blocks->block((int) ($reverse ? $actor['id'] : $recipient['id']), (int) ($reverse ? $recipient['id'] : $actor['id']));
        }
        $reader = new \App\Service\NotificationReadService($notices, new \App\Service\NotificationVisibilityService($this->db));
        self::assertSame($blocked ? 0 : 1, $reader->unreadCount($this->userEntity($recipient)));
        if (!$legacy) {
            $notices->clear((int) $recipient['id']);
        }
        $worker ??= new \App\Worker\NotificationEmailWorker($deliveries, $suppression,
            new \App\Repository\PostRepository($this->db), $this->users(), $mailer, $this->config);
        $worker->run();
        self::assertSame($blocked ? 0 : 1, $mailer->count(), 'Solved event actor governs email as well as the visible notification.');
    }
    public function testUnblockedSolvedEventStillSendsAfterHistoryIsCleared(): void
    {
        $this->checkSolvedBlockDirection(false, false);
    }

    public function testLegacySolvedEventResolvesSurvivingActorEvidence(): void
    {
        $this->checkSolvedBlockDirection(false, false, true);
    }

    public function testLegacySolvedEventStillChecksResolvedActorBlock(): void
    {
        $this->checkSolvedBlockDirection(false, true, true);
    }

    public function testSolvedRetryRechecksRecipientBlockAfterTransportFailure(): void
    {
        $this->checkSolvedBlockDirection(false, true, false, true);
    }

    public function testSolvedRetryRechecksAccepterBlockAfterTransportFailure(): void
    {
        $this->checkSolvedBlockDirection(true, true, false, true);
    }

    public function testLegacySolvedRetryRechecksResolvedActorBlock(): void
    {
        $this->checkSolvedBlockDirection(false, true, true, true);
    }

    public function testLegacyEditedPostWithoutEventActorFailsClosed(): void
    {
        $d = $this->queuedDelivery();
        $this->db->run('UPDATE posts SET edited_at = UTC_TIMESTAMP() WHERE id = ?', [$d['post_id']]);
        $mailer = new ArrayMailer();
        $this->worker($mailer)->run();
        self::assertSame(0, $mailer->count());
    }

    public function testLegacySolvedEmailWithNoActorEvidenceFailsClosed(): void
    {
        $d = $this->queuedDelivery();
        $this->db->run('UPDATE posts SET user_id = ? WHERE id = ?', [$d['user']['id'], $d['post_id']]);
        $mailer = new ArrayMailer();
        $this->worker($mailer)->run();
        self::assertSame(0, $mailer->count(), 'A self-authored target cannot identify the legacy solved-event actor.');
    }

    public function testLaterMentionCannotReplaceLegacyActorBlockedByRecipient(): void
    {
        $this->checkLegacyLaterMention(false);
    }

    public function testLaterMentionCannotReplaceLegacyActorBlockingRecipient(): void
    {
        $this->checkLegacyLaterMention(true);
    }

    public function testExplicitMentionActorIsNotReplacedByBlockedPostAuthor(): void
    {
        $this->checkLegacyLaterMention(false, false);
    }

    private function checkLegacyLaterMention(bool $reverse, bool $legacy = true): void
    {
        $author = $this->makeUser();
        $editor = $this->makeUser(['role' => 'admin']);
        $recipient = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author);
        $posts = new \App\Repository\PostRepository($this->db);
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        $notifications = new \App\Repository\NotificationRepository($this->db);
        $subscriptions = new \App\Repository\SubscriptionRepository($this->db);
        $deliveries = new \App\Repository\EmailDeliveryRepository($this->db);
        $suppression = new \App\Repository\EmailSuppressionRepository($this->db);
        $blocks = new \App\Repository\BlockRepository($this->db);
        $mailer = new \App\Mail\ArrayMailer();
        $service = new \App\Service\NotificationService($this->db, $notifications, $subscriptions,
            $deliveries, $suppression, $blocks, $this->users(),
            new \App\Core\FeatureFlags(new \App\Repository\SettingRepository($this->db)), $mailer);
        $subscriptions->set((int) $recipient['id'], 'thread', $thread['thread_id'], false, true, 'instant');
        $canonical = $this->threads()->findWithBoard($thread['thread_id']);
        if ($legacy) {
            $service->fanOutNewPost((int) $author['id'], $canonical, $postId, true, 'Original post');
            $this->db->run('UPDATE email_deliveries SET payload = NULL WHERE user_id = ?', [$recipient['id']]);
        }
        self::assertSame(0, $notifications->unreadCount((int) $recipient['id']));
        $blocks->block((int) ($reverse ? $author['id'] : $recipient['id']), (int) ($reverse ? $recipient['id'] : $author['id']));
        $this->db->run('UPDATE posts SET edited_at = UTC_TIMESTAMP() WHERE id = ?', [$postId]);
        $service->notifyMentions((int) $editor['id'], $canonical, $postId, [$recipient['username']]);
        if ($legacy) {
            self::assertNull($this->db->fetchValue('SELECT payload FROM email_deliveries WHERE user_id = ?', [$recipient['id']]), 'Existing legacy job wins deduplication.');
        }
        self::assertCount(1, $notifications->legacyInstantActors((int) $recipient['id'], $postId));
        $worker = new \App\Worker\NotificationEmailWorker($deliveries, $suppression, $posts, $this->users(), $mailer, $this->config);
        $worker->run();
        self::assertSame($legacy ? 0 : 1, $mailer->count(), 'Only explicit event provenance can replace the original author as the known actor.');
    }

    public function testSendsQueuedThenDoesNotResendOnRerun(): void
    {
        $this->queuedDelivery();
        $mailer = new ArrayMailer();

        $first = $this->worker($mailer)->run();
        self::assertSame(1, $first['sent']);
        self::assertSame(1, $mailer->count());
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM email_deliveries WHERE status = 'sent'"));

        // Re-running the worker must not resend an already-sent row.
        $second = $this->worker($mailer)->run();
        self::assertSame(0, $second['sent']);
        self::assertSame(1, $mailer->count(), 'no duplicate delivery on worker re-run');
    }

    public function testInstantEmailUsesOperatorSiteName(): void
    {
        (new SettingRepository($this->db))->set('site_name', 'Lakeside Forum');
        $this->queuedDelivery();
        $mailer = new ArrayMailer();

        $stats = $this->worker($mailer)->run();

        self::assertSame(1, $stats['sent']);
        $message = $mailer->to('r@example.test')[0];
        self::assertSame('New activity on Lakeside Forum', $message['subject']);
        self::assertStringContainsString('following on Lakeside Forum', $message['text']);
        self::assertStringNotContainsString('RetroBoards', $message['subject'] . $message['text'] . (string) $message['html']);
    }

    public function testSuppressedAddressIsDequeuedWithoutSending(): void
    {
        $d = $this->queuedDelivery();
        (new EmailSuppressionRepository($this->db))->suppress('r@example.test', 'bounce');
        $mailer = new ArrayMailer();

        $stats = $this->worker($mailer)->run();
        self::assertSame(1, $stats['suppressed']);
        self::assertSame(0, $mailer->count());
        self::assertSame("suppressed", (string) $this->db->fetchValue("SELECT status FROM email_deliveries LIMIT 1"));
    }

    public function testPausedEmailUserQueuedRowIsDequeuedWithoutSending(): void
    {
        $d = $this->queuedDelivery();
        (new UserPreferenceRepository($this->db))->merge((int) $d['user']['id'], ['pause_all_email' => true]);
        $mailer = new ArrayMailer();

        $stats = $this->worker($mailer)->run();

        self::assertSame(1, $stats['suppressed']);
        self::assertSame(0, $mailer->count());
        self::assertSame('suppressed', (string) $this->db->fetchValue('SELECT status FROM email_deliveries LIMIT 1'));
    }

    public function testFailsClosedWhenTransportUnconfigured(): void
    {
        $this->queuedDelivery();
        $stats = $this->worker(new SendmailMailer(''))->run();
        self::assertSame(0, $stats['sent']);
        self::assertSame("queued", (string) $this->db->fetchValue("SELECT status FROM email_deliveries LIMIT 1"), 'row stays queued for later');
    }

    public function testTransportFailureMarksRowFailed(): void
    {
        $this->queuedDelivery();
        $this->db->run('UPDATE email_deliveries SET max_attempts = 1');
        $mailer = new ArrayMailer();
        $mailer->failNext = true;

        $stats = $this->worker($mailer)->run();
        self::assertSame(1, $stats['failed']);
        self::assertSame("failed", (string) $this->db->fetchValue("SELECT status FROM email_deliveries LIMIT 1"));
    }

    public function testTransportFailureSchedulesRetryWithBackoff(): void
    {
        $this->queuedDelivery();
        $mailer = new ArrayMailer();
        $mailer->failNext = true;

        $stats = $this->worker($mailer)->run();

        self::assertSame(0, $stats['failed']);
        self::assertSame(1, $stats['retrying']);
        $row = $this->db->fetch('SELECT status, attempt_count, max_attempts, last_attempt_at, next_attempt_at FROM email_deliveries LIMIT 1');
        self::assertSame('queued', (string) $row['status']);
        self::assertSame(1, (int) $row['attempt_count']);
        self::assertSame(5, (int) $row['max_attempts']);
        self::assertNotNull($row['last_attempt_at']);
        self::assertNotNull($row['next_attempt_at']);
        $seconds = (int) $this->db->fetchValue('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), next_attempt_at) FROM email_deliveries LIMIT 1');
        self::assertGreaterThanOrEqual(250, $seconds);
        self::assertLessThanOrEqual(310, $seconds);
    }

    public function testMaxAttemptsOnePreservesTerminalFailureBehavior(): void
    {
        $this->queuedDelivery();
        $this->db->run('UPDATE email_deliveries SET max_attempts = 1');
        $mailer = new ArrayMailer();
        $mailer->failNext = true;

        $stats = $this->worker($mailer)->run();

        self::assertSame(1, $stats['failed']);
        self::assertSame(0, $stats['retrying']);
        $row = $this->db->fetch('SELECT status, attempt_count, max_attempts, next_attempt_at FROM email_deliveries LIMIT 1');
        self::assertSame('failed', (string) $row['status']);
        self::assertSame(1, (int) $row['attempt_count']);
        self::assertSame(1, (int) $row['max_attempts']);
        self::assertNull($row['next_attempt_at']);
    }

    public function testBoundedDrainRespectsLimitAndResumesWithoutLoss(): void
    {
        // A backlog larger than the per-run limit must drain in bounded batches,
        // oldest-first, losing nothing (PHASE_2_PLAN §9 "queue backlog").
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Backlog', 'OP.');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        $deliveries = new EmailDeliveryRepository($this->db);
        for ($i = 1; $i <= 3; $i++) {
            $r = $this->makeUser(['email' => "b$i@example.test"]);
            $deliveries->enqueue((int) $r['id'], "b$i@example.test", 'instant', null, $postId . ':' . (int) $r['id']);
        }
        $mailer = new ArrayMailer();

        $first = $this->worker($mailer)->run(2);
        self::assertSame(2, $first['sent']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM email_deliveries WHERE status = 'queued'"));
        // Oldest-first: the two earliest-enqueued rows drained, the newest waits.
        self::assertCount(1, $mailer->to('b1@example.test'));
        self::assertCount(1, $mailer->to('b2@example.test'));
        self::assertCount(0, $mailer->to('b3@example.test'), 'newest-enqueued row is not drained before the older ones');

        $second = $this->worker($mailer)->run(2);
        self::assertSame(1, $second['sent']);
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM email_deliveries WHERE status = 'queued'"));
        self::assertSame(3, $mailer->count(), 'every queued send delivered exactly once across batches');
    }

    public function testSystemAnnouncementEmailIncludesUnsubscribeLink(): void
    {
        // A broadcast announcement email is bulk mail; like every other email path
        // it must carry a one-click unsubscribe link (CAN-SPAM / deliverability).
        $recipient = $this->makeUser(['email' => 'sys@example.test']);
        (new EmailDeliveryRepository($this->db))->enqueue(
            (int) $recipient['id'],
            'sys@example.test',
            'system',
            'Heads up',
            null,
            ['type' => 'announcement', 'version' => 1, 'message' => 'Scheduled maintenance tonight.'],
        );
        $mailer = new ArrayMailer();

        $stats = $this->worker($mailer)->run();

        self::assertSame(1, $stats['sent']);
        $message = $mailer->to('sys@example.test')[0];
        self::assertStringContainsString('Scheduled maintenance tonight.', (string) $message['text']);
        self::assertStringContainsString('/unsubscribe?', (string) $message['text'], 'system email text must include an unsubscribe link');
        self::assertStringContainsString('/unsubscribe?', (string) $message['html'], 'system email html must include an unsubscribe link');
    }

    public function testConcurrentRunBacksOffWhileOutboxIsLocked(): void
    {
        // EMAIL-1: a second worker run must not drain the outbox while another
        // worker (a separate connection) holds the advisory drain lock, or the
        // same queued row would be sent twice.
        $this->queuedDelivery();
        $mailer = new ArrayMailer();

        $other = new \App\Core\Database($GLOBALS['__RB_TEST_DBCONFIG']); // separate connection
        self::assertSame(1, (int) $other->fetchValue("SELECT GET_LOCK('rb_email_outbox', 0)"));
        try {
            $stats = $this->worker($mailer)->run();
            self::assertSame(0, $stats['sent'], 'a concurrent run must not send while the outbox is locked');
            self::assertSame(0, $mailer->count());
            self::assertSame('queued', (string) $this->db->fetchValue("SELECT status FROM email_deliveries LIMIT 1"), 'row stays queued for the holder');
        } finally {
            $other->run("SELECT RELEASE_LOCK('rb_email_outbox')");
        }

        // Once the lock is free the next run drains it exactly once.
        $after = $this->worker($mailer)->run();
        self::assertSame(1, $after['sent']);
        self::assertSame(1, $mailer->count());
    }
    public function testUnavailableIsTerminalSuppressedAndNeverReportedSent(): void
    {
        $d = $this->queuedDelivery();
        $this->db->run('UPDATE posts SET is_deleted = 1 WHERE id = ?', [$d['post_id']]);
        $mailer = new ArrayMailer();
        $this->worker($mailer)->run();
        $row = $this->db->fetch('SELECT * FROM email_deliveries LIMIT 1');
        self::assertSame('suppressed', $row['status']);
        self::assertSame('content_unavailable', $row['error']);
        self::assertNull($row['sent_at']);
        self::assertNull($row['message_id']);
        self::assertSame(0, $mailer->count());
    }

    public function testMalformedMissingAndBannedRowsDoNotAbortLaterValidRows(): void
    {
        $recipient = $this->makeUser();
        $this->db->run('UPDATE users SET digest_hour = 9 WHERE id = ?', [$recipient['id']]);
        $banned = $this->makeUser(['status' => 'banned']);
        $repo = new EmailDeliveryRepository($this->db);
        $ids = [
            $repo->enqueue(null, 'gone@example.test', 'instant', null, '999:999') => ['suppressed', 'recipient_missing'],
            $repo->enqueue((int) $banned['id'], $banned['email'], 'system', null, null, ['type' => 'announcement', 'message' => 'blocked']) => ['suppressed', 'recipient_banned'],
            $repo->enqueue((int) $recipient['id'], $recipient['email'], 'digest', null) => ['failed', 'unreplayable_legacy_digest'],
            $repo->enqueue((int) $recipient['id'], $recipient['email'], 'digest', null, null, ['version' => 999]) => ['failed', 'invalid_digest_payload'],
        ];
        $good = $repo->enqueue((int) $recipient['id'], $recipient['email'], 'test', 'test');
        $mailer = new ArrayMailer(); $stats = $this->worker($mailer)->run();
        self::assertSame(1, $stats['sent']); self::assertSame(2, $stats['failed']); self::assertSame(2, $stats['suppressed']);
        foreach ($ids as $id => [$status, $reason]) {
            $row = $repo->find($id);
            self::assertSame($status, $row['status']); self::assertSame($reason, $row['error']);
            self::assertNull($row['sent_at']); self::assertNull($row['message_id']);
            self::assertSame(0, $repo->requeue($id));
        }
        self::assertSame('sent', $repo->find($good)['status']);
        self::assertSame(1, $mailer->count());
        self::assertStringContainsString('This is a test email', $mailer->to($recipient['email'])[0]['text']);
    }

    public function testOperatorDiagnosticRetryPreservesSeparatePauseContract(): void
    {
        $admin = $this->makeAdmin();
        (new UserPreferenceRepository($this->db))->merge((int) $admin['id'], ['pause_all_email' => true]);
        (new EmailSuppressionRepository($this->db))->suppress($admin['email'], 'manual');
        (new EmailDeliveryRepository($this->db))->enqueue((int) $admin['id'], $admin['email'], 'test', 'test');
        $mailer = new ArrayMailer();
        self::assertSame(1, $this->worker($mailer)->run()['sent']);
        self::assertSame(1, $mailer->count());
    }

    public function testQueuedInstantFailureThenActualHttpOptOutInEveryRestrictedState(): void
    {
        $this->makeAdmin();
        foreach (['suspended', 'banned', 'deactivated', 'pending_deletion'] as $state) {
            $recipient = $this->makeUser(); $uid = (int) $recipient['id'];
            $author = $this->makeUser(); $board = $this->makeBoard($this->makeCategory());
            $thread = $this->makeThread($board, $author);
            $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ?', [$thread['thread_id']]);
            $subs = new \App\Repository\SubscriptionRepository($this->db);
            $subs->set($uid, 'thread', $thread['thread_id'], true, true, 'instant');
            $repo = new EmailDeliveryRepository($this->db);
            $id = $repo->enqueue($uid, $recipient['email'], 'instant', null, $postId . ':' . $uid);
            $mailer = new ArrayMailer(); $mailer->failNext = true;
            self::assertSame(1, $this->worker($mailer)->run()['retrying']);
            $this->db->run("UPDATE users SET status = ?, suspended_until = '2099-01-01' WHERE id = ?", [$state, $uid]);
            $this->actingAs($recipient);
            $sid = $subs->get($uid, 'thread', $thread['thread_id'])['id'];
            $this->assertRedirect($this->post('/settings/notifications/subscriptions/' . $sid, ['frequency' => 'off']), '/settings/notifications');
            $this->db->run('UPDATE email_deliveries SET next_attempt_at = NULL WHERE id = ?', [$id]);
            self::assertSame(1, $this->worker($mailer)->run()['suppressed']);
            self::assertSame(0, $mailer->count());
            self::assertSame('suppressed', $repo->find($id)['status']);
            self::assertSame(0, $repo->requeue($id));
        }
    }

    public function testAnnouncementAvailabilityIsRecheckedBeforeRetry(): void
    {
        $recipient = $this->makeUser();
        $repo = new EmailDeliveryRepository($this->db);
        $id = $repo->enqueue((int) $recipient['id'], $recipient['email'], 'system', 'notice', null, ['type' => 'announcement', 'message' => 'private broadcast']);
        $mailer = new ArrayMailer(); $mailer->failNext = true;
        $worker = $this->worker($mailer);
        self::assertSame(1, $worker->run()['retrying']);
        (new SettingRepository($this->db))->set('features', ['email' => false]);
        $this->db->run('UPDATE email_deliveries SET next_attempt_at = NULL WHERE id = ?', [$id]);
        self::assertSame(1, $worker->run()['suppressed']);
        self::assertSame(0, $mailer->count());
        self::assertSame('delivery_disabled', $repo->find($id)['error']);
    }

    public function testCurrentPermittedStatesKeepDeliveryAndBanAfterFailureAllowsLaterJob(): void
    {
        $repo = new EmailDeliveryRepository($this->db);
        $mailer = new ArrayMailer();
        foreach (['active', 'suspended', 'deactivated', 'pending_deletion'] as $state) {
            $recipient = $this->makeUser(['status' => $state, 'suspended_until' => '2099-01-01']);
            $repo->enqueue((int) $recipient['id'], $recipient['email'], 'system', 'notice', null, ['type' => 'announcement', 'message' => 'eligible']);
        }
        self::assertSame(4, $this->worker($mailer)->run()['sent']);
        self::assertSame(4, $mailer->count());
        $recipient = $this->makeUser();
        $id = $repo->enqueue((int) $recipient['id'], $recipient['email'], 'system', 'notice', null, ['type' => 'announcement', 'message' => 'eligible']);
        $mailer->failNext = true;
        self::assertSame(1, $this->worker($mailer)->run()['retrying']);
        $this->db->run("UPDATE users SET status = 'banned' WHERE id = ?", [$recipient['id']]);
        $this->db->run('UPDATE email_deliveries SET next_attempt_at = NULL WHERE id = ?', [$id]);
        $later = $this->makeUser();
        $repo->enqueue((int) $later['id'], $later['email'], 'system', 'notice', null, ['type' => 'announcement', 'message' => 'later']);
        $stats = $this->worker($mailer)->run();
        self::assertSame(1, $stats['suppressed']); self::assertSame(1, $stats['sent']);
        self::assertSame('recipient_banned', $repo->find($id)['error']);
        self::assertSame(5, $mailer->count());
    }

}
