<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Mail\ArrayMailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\SubscriptionRepository;
use App\Worker\DailyDigestWorker;
use Tests\Support\TestCase;

/**
 * Daily digest worker (P2-04): timezone/hour gating, watermark (run once per
 * day), never sent empty, and dedup across re-runs.
 */
final class DailyDigestWorkerTest extends TestCase
{
    private function worker(ArrayMailer $mailer): DailyDigestWorker
    {
        return new DailyDigestWorker(
            $this->db,
            new EmailDeliveryRepository($this->db),
            new EmailSuppressionRepository($this->db),
            $mailer,
            $this->config,
        );
    }

    private function makeDigestUser(int $hour, string $tz = 'UTC'): array
    {
        $u = $this->makeUser();
        $this->db->run('UPDATE users SET timezone = ?, digest_hour = ? WHERE id = ?', [$tz, $hour, (int) $u['id']]);
        return $u;
    }

    public function testSendsOneNonEmptyDigestThenNotAgainSameDay(): void
    {
        $author = $this->makeUser();
        $recipient = $this->makeDigestUser(9);
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Daily topic', 'OP.');
        // New activity (by someone other than the recipient) in a daily-subscribed thread.
        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Fresh reply.']);
        (new SubscriptionRepository($this->db))->set((int) $recipient['id'], 'thread', $thread['thread_id'], true, true, 'daily');

        $mailer = new ArrayMailer();
        $stats = $this->worker($mailer)->run('2026-06-26 09:30:00');
        self::assertSame(1, $stats['sent']);
        self::assertCount(1, $mailer->to($recipient['email']));

        // Watermark advanced ⇒ a second run at the same hour sends nothing.
        $again = $this->worker($mailer)->run('2026-06-26 09:45:00');
        self::assertSame(0, $again['sent'], 'digest is not duplicated the same day');
        self::assertCount(1, $mailer->to($recipient['email']));
    }

    public function testNotDueOutsideTheDigestHour(): void
    {
        $author = $this->makeUser();
        $recipient = $this->makeDigestUser(9);
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Daily topic', 'OP.');
        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Fresh reply.']);
        (new SubscriptionRepository($this->db))->set((int) $recipient['id'], 'thread', $thread['thread_id'], true, true, 'daily');

        $mailer = new ArrayMailer();
        $stats = $this->worker($mailer)->run('2026-06-26 15:00:00'); // local hour 15 != 9
        self::assertSame(0, $stats['sent']);
        self::assertSame(0, $mailer->count());
    }

    public function testNeverSendsAnEmptyDigest(): void
    {
        $recipient = $this->makeDigestUser(9);
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Quiet topic', 'OP.');
        // Subscribed, but no activity after the watermark (only the OP, by the author).
        (new SubscriptionRepository($this->db))->set((int) $recipient['id'], 'thread', $thread['thread_id'], true, true, 'daily');
        // Watermark in the future so the OP is excluded.
        $this->db->run('UPDATE users SET last_daily_digest_at = ? WHERE id = ?', ['2026-06-25 09:00:00', (int) $recipient['id']]);
        // Make the OP older than the watermark by backdating it.
        $this->db->run('UPDATE posts SET created_at = ? WHERE thread_id = ?', ['2026-06-20 00:00:00', $thread['thread_id']]);

        $mailer = new ArrayMailer();
        $stats = $this->worker($mailer)->run('2026-06-26 09:30:00');
        self::assertSame(0, $stats['sent']);
        self::assertSame(1, $stats['skipped_empty']);
        self::assertSame(0, $mailer->count());
    }
}
