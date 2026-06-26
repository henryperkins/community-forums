<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Mail\ArrayMailer;
use App\Mail\SendmailMailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\PostRepository;
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
        $mailer = new ArrayMailer();
        $mailer->failNext = true;

        $stats = $this->worker($mailer)->run();
        self::assertSame(1, $stats['failed']);
        self::assertSame("failed", (string) $this->db->fetchValue("SELECT status FROM email_deliveries LIMIT 1"));
    }
}
