<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Mail\ArrayMailer;
use App\Mail\SendmailMailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
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
}
