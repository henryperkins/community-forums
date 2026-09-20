<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Mail\ArrayMailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\SettingRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserPreferenceRepository;
use App\Service\EmailPreferenceService;
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
            new SettingRepository($this->db),
            null,
            new EmailPreferenceService(new UserPreferenceRepository($this->db)),
        );
    }

    private function makeDigestUser(int $hour, string $tz = 'UTC'): array
    {
        $u = $this->makeUser();
        $this->db->run('UPDATE users SET timezone = ?, digest_hour = ? WHERE id = ?', [$tz, $hour, (int) $u['id']]);
        return $u;
    }

    public function testPrivateAccessIsRecheckedBeforeSending(): void
    {
        $author = $this->makeUser();
        $recipient = $this->makeDigestUser(9);
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'PRIVATE-AFTER-REVOCATION');
        (new SubscriptionRepository($this->db))->set((int) $recipient['id'], 'thread', $thread['thread_id'], true, true, 'daily');
        $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$board['id']]);
        $mailer = new ArrayMailer();
        $this->worker($mailer)->run('2026-06-26 09:30:00');
        self::assertSame(0, $mailer->count());
    }

    public function testDigestRechecksBothBlockDirectionsAndFreshAssignedAccess(): void
    {
        $author = $this->makeUser();
        $recipient = $this->makeDigestUser(9);
        $uid = (int) $recipient['id'];
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author);
        (new SubscriptionRepository($this->db))->set($uid, 'thread', $thread['thread_id'], true, true, 'daily');
        $mailer = new ArrayMailer();
        $worker = $this->worker($mailer);
        $blocks = new \App\Repository\BlockRepository($this->db);
        foreach ([[$uid, (int) $author['id']], [(int) $author['id'], $uid]] as [$a, $b]) {
            $blocks->block($a, $b);
            $this->db->run('UPDATE users SET last_daily_digest_at = NULL WHERE id = ?', [$uid]);
            self::assertSame(0, $worker->run('2026-06-26 09:30:00')['sent']);
            $blocks->unblock($a, $b);
        }
        $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$board['id']]);
        $mods = new \App\Repository\BoardModeratorRepository($this->db);
        $mods->assign((int) $board['id'], $uid);
        $this->db->run("UPDATE users SET status = 'suspended', suspended_until = '2099-01-01', last_daily_digest_at = NULL, timezone = NULL WHERE id = ?", [$uid]);
        self::assertSame(1, $worker->run('2026-06-26 09:30:00')['sent'], 'NULL zone uses UTC; suspended assignment retains read access');
        $mods->unassign((int) $board['id'], $uid);
        $this->db->run('UPDATE users SET last_daily_digest_at = NULL WHERE id = ?', [$uid]);
        self::assertSame(0, $worker->run('2026-06-26 09:30:00')['sent']);
        self::assertSame(1, $mailer->count());
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

    public function testDigestBodyUsesOperatorSiteName(): void
    {
        (new SettingRepository($this->db))->set('site_name', 'Lakeside Forum');
        $author = $this->makeUser();
        $recipient = $this->makeDigestUser(9);
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Branded digest', 'OP.');
        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Fresh reply.']);
        (new SubscriptionRepository($this->db))->set((int) $recipient['id'], 'thread', $thread['thread_id'], true, true, 'daily');

        $mailer = new ArrayMailer();
        $stats = $this->worker($mailer)->run('2026-06-26 09:30:00');

        self::assertSame(1, $stats['sent']);
        $message = $mailer->to($recipient['email'])[0];
        self::assertStringContainsString('Lakeside Forum daily digest', $message['text']);
        self::assertStringNotContainsString('RetroBoards', $message['text']);
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

    public function testDigestUsesRecipientLocalTimezoneNotUtc(): void
    {
        // Recipient wants the digest at 09:00 America/Chicago (UTC-5 in June).
        // A UTC-only comparison would (wrongly) send at UTC 09:30; the correct,
        // timezone-aware worker only sends when it is 09:xx in Chicago = UTC 14:xx.
        $author = $this->makeUser();
        $recipient = $this->makeDigestUser(9, 'America/Chicago');
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Daily topic', 'OP.');
        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Fresh reply.']);
        (new SubscriptionRepository($this->db))->set((int) $recipient['id'], 'thread', $thread['thread_id'], true, true, 'daily');

        // UTC 09:30 == 04:30 in Chicago → NOT the local digest hour → no send.
        $early = $this->worker(new ArrayMailer())->run('2026-06-26 09:30:00');
        self::assertSame(0, $early['sent'], 'must compare the digest hour in the recipient timezone, not UTC');

        // UTC 14:30 == 09:30 in Chicago → the local digest hour → send once.
        $mailer = new ArrayMailer();
        $due = $this->worker($mailer)->run('2026-06-26 14:30:00');
        self::assertSame(1, $due['sent']);
        self::assertCount(1, $mailer->to($recipient['email']));
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

    public function testPausedEmailUserDoesNotReceiveDigest(): void
    {
        $author = $this->makeUser();
        $recipient = $this->makeDigestUser(9);
        (new UserPreferenceRepository($this->db))->merge((int) $recipient['id'], ['pause_all_email' => true]);
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Paused digest', 'OP.');
        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Fresh reply.']);
        (new SubscriptionRepository($this->db))->set((int) $recipient['id'], 'thread', $thread['thread_id'], true, true, 'daily');

        $mailer = new ArrayMailer();
        $stats = $this->worker($mailer)->run('2026-06-26 09:30:00');

        self::assertSame(0, $stats['sent']);
        self::assertSame(1, $stats['suppressed']);
        self::assertSame(0, $mailer->count());
        self::assertSame('2026-06-26 09:30:00', (string) $this->db->fetchValue('SELECT last_daily_digest_at FROM users WHERE id = ?', [(int) $recipient['id']]));
    }
}
