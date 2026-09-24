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
    private function worker(\App\Mail\Mailer $mailer): DailyDigestWorker
    {
        $deliveries = new EmailDeliveryRepository($this->db);
        $suppression = new EmailSuppressionRepository($this->db);
        $settings = new SettingRepository($this->db);
        $preferences = new EmailPreferenceService(new UserPreferenceRepository($this->db));
        $visibility = new \App\Service\NotificationVisibilityService($this->db);
        $digests = new \App\Service\DigestService(new \App\Repository\DigestActivityRepository($this->db), $visibility, $this->config, $settings);
        $drainer = new \App\Worker\NotificationEmailWorker($deliveries, $suppression,
            new \App\Repository\PostRepository($this->db), $this->users(), $mailer, $this->config,
            $settings, null, $preferences, $visibility, $digests);
        return new DailyDigestWorker($this->db, $deliveries, $suppression, $mailer, $this->config,
            $settings, null, $preferences, $visibility, $digests, $drainer);
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
        $this->db->run("UPDATE posts SET created_at = '2026-06-26 08:00:00' WHERE thread_id = ?", [$thread['thread_id']]);
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
        $this->db->run("UPDATE posts SET created_at = '2026-06-26 08:00:00' WHERE thread_id = ?", [$thread['thread_id']]);
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

        $this->db->run("UPDATE posts SET created_at = '2026-06-26 08:00:00' WHERE thread_id = ?", [$thread['thread_id']]);
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

        $this->db->run("UPDATE posts SET created_at = '2026-06-26 08:00:00' WHERE thread_id = ?", [$thread['thread_id']]);
        $mailer = new ArrayMailer();
        $stats = $this->worker($mailer)->run('2026-06-26 09:30:00');

        self::assertSame(1, $stats['sent']);
        $message = $mailer->to($recipient['email'])[0];
        self::assertStringContainsString('Lakeside Forum daily digest', $message['text']);
        self::assertStringNotContainsString('RetroBoards', $message['text']);
    }

    public function testBeforeHourWaitsAndLateCronSchedulesOnce(): void
    {
        $author = $this->makeUser();
        $recipient = $this->makeDigestUser(9);
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Daily topic', 'OP.');
        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Fresh reply.']);
        (new SubscriptionRepository($this->db))->set((int) $recipient['id'], 'thread', $thread['thread_id'], true, true, 'daily');

        $this->db->run("UPDATE posts SET created_at = '2026-06-26 08:00:00' WHERE thread_id = ?", [$thread['thread_id']]);
        $mailer = new ArrayMailer();
        self::assertSame(0, $this->worker($mailer)->run('2026-06-26 08:59:00')['sent']);
        self::assertSame(1, $this->worker($mailer)->run('2026-06-26 15:00:00')['sent']);
        self::assertSame(0, $this->worker($mailer)->run('2026-06-26 16:00:00')['sent']);
        self::assertSame(1, $mailer->count());
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
        $this->db->run("UPDATE posts SET created_at = '2026-06-26 08:00:00' WHERE thread_id = ?", [$thread['thread_id']]);
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

        $this->db->run("UPDATE posts SET created_at = '2026-06-26 08:00:00' WHERE thread_id = ?", [$thread['thread_id']]);
        $mailer = new ArrayMailer();
        $stats = $this->worker($mailer)->run('2026-06-26 09:30:00');

        self::assertSame(0, $stats['sent']);
        self::assertSame(1, $stats['suppressed']);
        self::assertSame(0, $mailer->count());
        self::assertSame('2026-06-26 09:30:00', (string) $this->db->fetchValue('SELECT last_daily_digest_at FROM users WHERE id = ?', [(int) $recipient['id']]));
    }
    public function test_failed_digest_retries_without_scheduling_a_duplicate(): void
    {
        $author = $this->makeUser();
        $recipient = $this->makeDigestUser(9);
        $thread = $this->makeThread($this->makeBoard($this->makeCategory()), $author);
        $this->db->run('UPDATE posts SET created_at = ? WHERE thread_id = ?', ['2026-09-20 08:00:00', $thread['thread_id']]);
        (new SubscriptionRepository($this->db))->set((int) $recipient['id'], 'thread', $thread['thread_id'], true, true, 'daily');
        $mailer = new ArrayMailer();
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

    private function digestFixture(): array
    {
        $recipient = $this->makeDigestUser(9);
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Original eligible topic');
        $this->db->run("UPDATE posts SET created_at = '2026-09-20 08:00:00' WHERE thread_id = ?", [$thread['thread_id']]);
        (new SubscriptionRepository($this->db))->set((int) $recipient['id'], 'thread', $thread['thread_id'], true, true, 'daily');
        return compact('recipient', 'author', 'board', 'thread');
    }

    public function testNullTimezoneSchedulesDurablyBeforeUnconfiguredTransportAndRecovery(): void
    {
        $f = $this->digestFixture();
        $uid = (int) $f['recipient']['id'];
        $this->db->run('UPDATE users SET timezone = NULL WHERE id = ?', [$uid]);
        $stats = $this->worker(new \App\Mail\SendmailMailer(''))->run('2026-09-20 09:15:00');
        self::assertSame('sender_unconfigured', $stats['blocked_reason']);
        self::assertSame(1, $stats['queued']);
        $job = $this->db->fetch("SELECT * FROM email_deliveries WHERE user_id = ? AND kind = 'digest'", [$uid]);
        self::assertSame('queued', $job['status']);
        self::assertSame(0, (int) $job['attempt_count']);
        self::assertNull($job['error']);
        $payload = json_decode($job['payload'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('2026-09-19 09:15:00', $payload['window_start_utc']);
        self::assertSame('2026-09-20 09:15:00', $payload['window_end_utc']);
        self::assertSame([], $payload['sources']['saved_feeds']);
        self::assertStringNotContainsString('Original eligible topic', $job['payload']);
        self::assertSame('2026-09-20 09:15:00', $this->users()->find($uid)['last_daily_digest_at']);
        $mailer = new ArrayMailer();
        self::assertSame(1, $this->worker($mailer)->run('2026-09-20 09:20:00')['sent']);
        self::assertSame(0, $this->worker($mailer)->run('2026-09-20 20:00:00')['queued']);
        self::assertSame(1, $mailer->count());
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM email_deliveries WHERE kind = 'digest'"));
    }

    public function testDomainBlockPreservesQueuedWindowAndPreviousErrorWithoutAttempt(): void
    {
        $f = $this->digestFixture();
        $settings = new SettingRepository($this->db);
        $cfg = new \App\Core\Config(array_replace_recursive($this->config->all(), ['mail' => ['from' => 'sender@example.test', 'require_verified_domain' => true]]));
        $verifier = new \App\Service\EmailDomainVerifier($cfg, $settings, new \App\Repository\EmailDomainStatusRepository($this->db));
        $mailer = new ArrayMailer();
        $worker = new DailyDigestWorker($this->db, new EmailDeliveryRepository($this->db), new EmailSuppressionRepository($this->db), $mailer, $cfg, $settings, $verifier);
        self::assertSame('domain_unverified', $worker->run('2026-09-20 09:15:00')['blocked_reason']);
        $job = $this->db->fetch("SELECT * FROM email_deliveries WHERE kind = 'digest'");
        self::assertNotNull($job['payload']);
        $this->db->run('UPDATE email_deliveries SET error = ? WHERE id = ?', ['previous transport failure', $job['id']]);
        $worker->run('2026-09-20 09:20:00');
        $after = (new EmailDeliveryRepository($this->db))->find((int) $job['id']);
        self::assertSame('previous transport failure', $after['error']);
        self::assertSame(0, (int) $after['attempt_count']);
        self::assertSame($job['payload'], $after['payload']);
        self::assertSame(0, $mailer->count());
        self::assertSame(1, $this->worker($mailer)->run('2026-09-20 09:25:00')['sent']);
    }

    public function testDstGapAndFoldAndInvalidZoneAreObservable(): void
    {
        foreach ([['2026-03-08 06:59:00', '2026-03-08 07:00:00', '2026-03-08 08:00:00', 2],
                  ['2026-11-01 04:59:00', '2026-11-01 05:30:00', '2026-11-01 06:30:00', 1]] as [$early, $due, $again, $hour]) {
            $f = $this->digestFixture();
            $uid = (int) $f['recipient']['id'];
            $this->db->run("UPDATE users SET timezone = 'America/New_York', digest_hour = ? WHERE id = ?", [$hour, $uid]);
            $this->db->run('UPDATE posts SET created_at = ? WHERE thread_id = ?', [$early, $f['thread']['thread_id']]);
            $mailer = new ArrayMailer();
            self::assertSame(0, $this->worker($mailer)->run($early)['sent']);
            self::assertSame(1, $this->worker($mailer)->run($due)['sent']);
            self::assertSame(0, $this->worker($mailer)->run($again)['sent']);
            self::assertSame(1, $mailer->count());
            $this->db->run('UPDATE users SET digest_hour = NULL WHERE id = ?', [$uid]);
        }
        $f = $this->digestFixture();
        $this->db->run("UPDATE users SET timezone = 'Invalid/Zone' WHERE id = ?", [$f['recipient']['id']]);
        $stats = $this->worker(new ArrayMailer())->run('2026-09-20 09:15:00');
        self::assertSame(1, $stats['invalid_timezone']);
        self::assertNull($this->users()->find((int) $f['recipient']['id'])['last_daily_digest_at']);
    }

    public function testTimezoneChangeCannotReverseWindowOrRecreateScheduledDate(): void
    {
        $f = $this->digestFixture();
        $uid = (int) $f['recipient']['id'];
        $this->worker(new \App\Mail\SendmailMailer(''))->run('2026-09-20 09:15:00');
        $this->db->run("UPDATE users SET timezone = 'Pacific/Kiritimati' WHERE id = ?", [$uid]);
        self::assertSame(0, $this->worker(new ArrayMailer())->run('2026-09-20 08:00:00')['queued']);
        $this->db->run('UPDATE users SET last_daily_digest_at = NULL WHERE id = ?', [$uid]);
        self::assertSame(0, $this->worker(new ArrayMailer())->run('2026-09-20 09:20:00')['queued']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM email_deliveries WHERE kind = 'digest'"));
    }

    public function testRetryUsesFixedBoundsOriginalSourcesAndCurrentSourceSettings(): void
    {
        $f = $this->digestFixture();
        $uid = (int) $f['recipient']['id'];
        $subs = new SubscriptionRepository($this->db);
        $second = $this->makeThread($f['board'], $f['author'], 'Remaining original source');
        $subs->set($uid, 'thread', $second['thread_id'], true, true, 'daily');
        $this->db->run("UPDATE posts SET created_at = '2026-09-20 08:00:00'");
        $mailer = new ArrayMailer(); $mailer->failNext = true;
        $this->worker($mailer)->run('2026-09-20 09:15:00');
        $job = $this->db->fetch("SELECT * FROM email_deliveries WHERE kind = 'digest'");
        $subs->set($uid, 'thread', $f['thread']['thread_id'], true, false, 'daily');
        $new = $this->makeThread($f['board'], $f['author'], 'Newly subscribed source');
        $subs->set($uid, 'thread', $new['thread_id'], true, true, 'daily');
        $posts = new \App\Repository\PostRepository($this->db);
        $posts->create(['thread_id' => $second['thread_id'], 'user_id' => (int) $f['author']['id'], 'body' => 'late', 'body_html' => '<p>late</p>']);
        // Backdated insertion after the snapshot is still excluded by max_post_id.
        $this->db->run("UPDATE posts SET created_at = '2026-09-20 08:30:00' WHERE id > ?", [json_decode($job['payload'], true)['max_post_id']]);
        $this->db->run('UPDATE email_deliveries SET next_attempt_at = NULL WHERE id = ?', [$job['id']]);
        self::assertSame(1, $this->worker($mailer)->run('2026-09-20 09:20:00')['sent']);
        $text = $mailer->to($f['recipient']['email'])[0]['text'];
        self::assertStringContainsString('Remaining original source (1 new)', $text);
        self::assertStringNotContainsString('Original eligible topic', $text);
        self::assertStringNotContainsString('Newly subscribed source', $text);
    }

    public function testRetryRechecksEveryTerminalCauseAndDoesNotReplayAfterRestoration(): void
    {
        foreach (['banned' => 'recipient_banned', 'deleted' => 'recipient_deleted', 'pause' => 'recipient_paused',
                  'off' => 'delivery_disabled', 'suppression' => 'address_suppressed', 'source' => 'content_unavailable',
                  'block' => 'content_unavailable', 'private' => 'content_unavailable', 'post' => 'content_unavailable'] as $change => $reason) {
            $f = $this->digestFixture(); $uid = (int) $f['recipient']['id'];
            $mailer = new ArrayMailer(); $mailer->failNext = true;
            self::assertSame(1, $this->worker($mailer)->run('2026-09-20 09:15:00')['retrying']);
            $job = $this->db->fetch("SELECT * FROM email_deliveries WHERE user_id = ? AND kind = 'digest'", [$uid]);
            match ($change) {
                'banned', 'deleted' => $this->db->run('UPDATE users SET status = ? WHERE id = ?', [$change, $uid]),
                'pause' => (new UserPreferenceRepository($this->db))->merge($uid, ['pause_all_email' => true]),
                'off' => $this->db->run('UPDATE users SET digest_hour = NULL WHERE id = ?', [$uid]),
                'suppression' => (new EmailSuppressionRepository($this->db))->suppress($f['recipient']['email'], 'manual'),
                'source' => (new SubscriptionRepository($this->db))->set($uid, 'thread', $f['thread']['thread_id'], false, false, 'off'),
                'block' => (new \App\Repository\BlockRepository($this->db))->block($uid, (int) $f['author']['id']),
                'private' => $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$f['board']['id']]),
                'post' => $this->db->run('UPDATE posts SET is_deleted = 1 WHERE thread_id = ?', [$f['thread']['thread_id']]),
            };
            $this->db->run('UPDATE email_deliveries SET next_attempt_at = NULL WHERE id = ?', [$job['id']]);
            $this->worker($mailer)->run('2026-09-20 09:20:00');
            $after = (new EmailDeliveryRepository($this->db))->find((int) $job['id']);
            self::assertSame('suppressed', $after['status'], $change);
            self::assertSame($reason, $after['error'], $change);
            self::assertNull($after['sent_at']); self::assertNull($after['message_id']); self::assertNull($after['next_attempt_at']);
            self::assertSame(0, $mailer->count());
            self::assertSame(0, (new EmailDeliveryRepository($this->db))->requeue((int) $job['id']));
            $this->db->run('UPDATE users SET digest_hour = NULL WHERE id = ?', [$uid]);
        }
    }

    public function testLargeWindowIncludesAllThreadsAndBothTimeBounds(): void
    {
        $f = $this->digestFixture(); $uid = (int) $f['recipient']['id'];
        $subs = new SubscriptionRepository($this->db);
        $subs->delete($uid, 'thread', $f['thread']['thread_id']);
        $subs->set($uid, 'board', (int) $f['board']['id'], true, true, 'daily');
        for ($i = 0; $i < 105; $i++) { $this->makeThread($f['board'], $f['author'], sprintf('Window topic %03d', $i)); }
        $this->db->run("UPDATE posts SET created_at = '2026-09-20 09:15:00'");
        $old = $this->makeThread($f['board'], $f['author'], 'EXCLUDED lower bound');
        $this->db->run("UPDATE posts SET created_at = '2026-09-19 09:15:00' WHERE thread_id = ?", [$old['thread_id']]);
        $future = $this->makeThread($f['board'], $f['author'], 'EXCLUDED future');
        $this->db->run("UPDATE posts SET created_at = '2026-09-20 09:15:01' WHERE thread_id = ?", [$future['thread_id']]);
        $mailer = new ArrayMailer(); $this->worker($mailer)->run('2026-09-20 09:15:00');
        $message = $mailer->to($f['recipient']['email'])[0];
        self::assertStringContainsString('106 active threads', $message['subject']);
        self::assertStringNotContainsString('EXCLUDED', $message['text']);
        for ($i = 0; $i < 105; $i++) { self::assertStringContainsString(sprintf('Window topic %03d', $i), $message['text']); }
    }

    public function testSchedulingRollbackLeavesWatermarkAndOutboxUnchanged(): void
    {
        $this->withoutHarnessTransaction(function (): void {
            $f = $this->digestFixture(); $uid = (int) $f['recipient']['id'];
            try {
                $this->db->run("CREATE TRIGGER n3_fail_watermark BEFORE UPDATE ON users FOR EACH ROW BEGIN IF NEW.id = $uid AND NEW.last_daily_digest_at IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'n3 injected watermark failure'; END IF; END");
                try {
                    $this->worker(new ArrayMailer())->run('2026-09-20 09:15:00');
                    self::fail('Expected scheduling failure');
                } catch (\PDOException $e) {
                    self::assertStringContainsString('n3 injected watermark failure', $e->getMessage());
                }
                self::assertNull($this->users()->find($uid)['last_daily_digest_at']);
                self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM email_deliveries WHERE user_id = ? AND kind = 'digest'", [$uid]));
            } finally {
                $this->db->run('DROP TRIGGER IF EXISTS n3_fail_watermark');
                $this->db->run('DELETE FROM posts WHERE thread_id = ?', [$f['thread']['thread_id']]);
                $this->db->run('DELETE FROM threads WHERE id = ?', [$f['thread']['thread_id']]);
                $this->db->run('DELETE FROM boards WHERE id = ?', [$f['board']['id']]);
                $this->db->run('DELETE FROM categories WHERE id = ?', [$f['board']['category_id']]);
                foreach ([$uid, (int) $f['author']['id']] as $id) { $this->db->run('DELETE FROM users WHERE id = ?', [$id]); }
            }
        });
    }

    public function testDuePausedSuppressedAndBannedWindowsAreConsumedWithoutCatchup(): void
    {
        foreach (['paused', 'suppressed', 'banned'] as $state) {
            $f = $this->digestFixture(); $uid = (int) $f['recipient']['id'];
            $prefs = new UserPreferenceRepository($this->db);
            $suppression = new EmailSuppressionRepository($this->db);
            if ($state === 'paused') { $prefs->merge($uid, ['pause_all_email' => true]); }
            if ($state === 'suppressed') { $suppression->suppress($f['recipient']['email'], 'manual'); }
            if ($state === 'banned') { $this->db->run("UPDATE users SET status = 'banned' WHERE id = ?", [$uid]); }
            $mailer = new ArrayMailer();
            self::assertSame(1, $this->worker($mailer)->run('2026-09-20 09:15:00')['suppressed']);
            self::assertSame('2026-09-20 09:15:00', $this->users()->find($uid)['last_daily_digest_at']);
            self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM email_deliveries WHERE user_id = ? AND kind = 'digest'", [$uid]));
            $prefs->merge($uid, ['pause_all_email' => false]);
            $suppression->unsuppress($f['recipient']['email']);
            $this->db->run("UPDATE users SET status = 'active' WHERE id = ?", [$uid]);
            self::assertSame(0, $this->worker($mailer)->run('2026-09-20 10:00:00')['sent']);
            self::assertSame(0, $mailer->count());
            $this->db->run('UPDATE users SET digest_hour = NULL WHERE id = ?', [$uid]);
        }
    }

    public function testQueuedDigestFailureThenHttpOptOutInEveryRestrictedState(): void
    {
        $this->makeAdmin();
        foreach (['suspended', 'banned', 'deactivated', 'pending_deletion'] as $state) {
            $f = $this->digestFixture(); $uid = (int) $f['recipient']['id'];
            $mailer = new ArrayMailer(); $mailer->failNext = true;
            self::assertSame(1, $this->worker($mailer)->run('2026-09-20 09:15:00')['retrying']);
            $job = $this->db->fetch("SELECT * FROM email_deliveries WHERE user_id = ? AND kind = 'digest'", [$uid]);
            $this->db->run("UPDATE users SET status = ?, suspended_until = '2099-01-01' WHERE id = ?", [$state, $uid]);
            $this->actingAs($f['recipient']);
            $this->assertRedirect($this->post('/settings/notifications', ['timezone' => '', 'digest_hour' => '']), '/settings/notifications');
            self::assertNull($this->users()->find($uid)['digest_hour']);
            $this->db->run('UPDATE email_deliveries SET next_attempt_at = NULL WHERE id = ?', [$job['id']]);
            self::assertSame(1, $this->worker($mailer)->run('2026-09-20 09:20:00')['suppressed']);
            self::assertSame(0, $mailer->count());
            $row = (new EmailDeliveryRepository($this->db))->find((int) $job['id']);
            self::assertSame($state === 'banned' ? 'recipient_banned' : 'delivery_disabled', $row['error']);
            self::assertNull($row['sent_at']); self::assertNull($row['message_id']);
        }
    }

    public function testBothCommandsShareDrainLockAndEmailWorkerDrainsDigestRetries(): void
    {
        $f = $this->digestFixture();
        $other = new \App\Core\Database($GLOBALS['__RB_TEST_DBCONFIG']);
        self::assertTrue($other->tryLock('rb_email_outbox'));
        $mailer = new ArrayMailer();
        try {
            $stats = $this->worker($mailer)->run('2026-09-20 09:15:00');
            self::assertSame(1, $stats['queued']); self::assertSame(0, $stats['sent']);
        } finally { $other->unlock('rb_email_outbox'); }
        $worker = new \App\Worker\NotificationEmailWorker(new EmailDeliveryRepository($this->db), new EmailSuppressionRepository($this->db),
            new \App\Repository\PostRepository($this->db), $this->users(), $mailer, $this->config);
        self::assertSame(1, $worker->run()['sent']);
        self::assertSame(0, $this->worker($mailer)->run('2026-09-20 09:20:00')['sent']);
        self::assertSame(1, $mailer->count());
    }

    public function test_saved_feed_only_delivers_once_and_explicit_off_overrides(): void
    {
        $f = $this->digestFixture(); $uid = (int) $f['recipient']['id'];
        $subs = new SubscriptionRepository($this->db); $subs->delete($uid, 'thread', $f['thread']['thread_id']);
        $this->db->insert('INSERT INTO saved_feed_filters (user_id,name,filter_json,digest_enabled,created_at) VALUES (?,?,?,1,UTC_TIMESTAMP())', [$uid, 'Daily selected', json_encode(['board_ids' => [(int) $f['board']['id']], 'sort' => 'latest'])]);
        $off = $this->makeThread($f['board'], $f['author'], 'EXCLUDED explicit off');
        $subs->set($uid, 'thread', $off['thread_id'], false, false, 'off');
        $this->db->run("UPDATE posts SET created_at = '2026-09-20 08:00:00'");
        $mailer = new ArrayMailer();
        self::assertSame(1, $this->worker($mailer)->run('2026-09-20 09:15:00')['sent']);
        $text = $mailer->to($f['recipient']['email'])[0]['text'];
        self::assertStringContainsString('Original eligible topic (1 new)', $text);
        self::assertStringNotContainsString('EXCLUDED', $text);
        self::assertSame(0, $this->worker($mailer)->run('2026-09-20 09:20:00')['sent']);
    }

    private function savedSource(array $fixture, array $boards, string $name = 'Saved daily'): int
    {
        return $this->db->insert('INSERT INTO saved_feed_filters (user_id,name,filter_json,digest_enabled,created_at) VALUES (?,?,?,1,UTC_TIMESTAMP())',
            [$fixture['recipient']['id'], $name, json_encode(['board_ids' => $boards, 'sort' => 'latest'])]);
    }

    public function test_overlapping_saved_sources_and_subscriptions_count_each_post_once_with_effective_frequency(): void
    {
        $f = $this->digestFixture(); $uid = (int) $f['recipient']['id'];
        $this->savedSource($f, [], 'All'); $this->savedSource($f, [(int) $f['board']['id']], 'Selected');
        $subs = new SubscriptionRepository($this->db);
        $subs->set($uid, 'board', (int) $f['board']['id'], true, true, 'daily');
        foreach ([['off', true], ['instant', true], ['daily', false]] as [$frequency, $email]) {
            $thread = $this->makeThread($f['board'], $f['author'], 'EXCLUDED ' . $frequency . (int) $email);
            $subs->set($uid, 'thread', $thread['thread_id'], true, $email, $frequency);
        }
        $this->db->run("UPDATE posts SET created_at = '2026-09-20 08:00:00'");
        $mailer = new ArrayMailer(); $this->worker($mailer)->run('2026-09-20 09:15:00');
        $text = $mailer->to($f['recipient']['email'])[0]['text'];
        self::assertSame(1, substr_count($text, 'Original eligible topic (1 new)'));
        self::assertStringNotContainsString('EXCLUDED', $text);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM email_deliveries WHERE user_id=? AND kind='digest'", [$uid]));
    }

    public function test_saved_feed_retry_intersects_original_and_current_filters_and_retains_other_sources(): void
    {
        foreach (['disable', 'delete', 'replace', 'corrupt', 'private', 'feature_off'] as $change) {
            $f = $this->digestFixture(); $uid = (int) $f['recipient']['id'];
            $subs = new SubscriptionRepository($this->db); $subs->delete($uid, 'thread', $f['thread']['thread_id']);
            $id = $this->savedSource($f, [(int) $f['board']['id']]);
            $otherBoard = $this->makeBoard($this->makeCategory());
            $other = $this->makeThread($otherBoard, $f['author'], 'Remaining subscribed source ' . $change);
            $subs->set($uid, 'thread', $other['thread_id'], true, true, 'daily');
            $new = $this->makeThread($otherBoard, $f['author'], 'EXCLUDED newly selected ' . $change);
            $this->db->run("UPDATE posts SET created_at='2026-09-20 08:00:00'");
            $mailer = new ArrayMailer(); $mailer->failNext = true;
            self::assertSame(1, $this->worker($mailer)->run('2026-09-20 09:15:00')['retrying']);
            $job = $this->db->fetch("SELECT * FROM email_deliveries WHERE user_id=? AND kind='digest'", [$uid]);
            $payload = json_decode($job['payload'], true);
            self::assertSame([(int) $f['board']['id']], $payload['sources']['saved_feeds'][0]['filter']['board_ids']);
            match ($change) {
                'disable' => $this->db->run('UPDATE saved_feed_filters SET digest_enabled=0 WHERE id=?', [$id]),
                'delete' => $this->db->run('DELETE FROM saved_feed_filters WHERE id=?', [$id]),
                'replace' => $this->db->run('UPDATE saved_feed_filters SET filter_json=? WHERE id=?', [json_encode(['board_ids'=>[(int) $otherBoard['id']], 'sort'=>'latest']), $id]),
                'corrupt' => $this->db->run('UPDATE saved_feed_filters SET filter_json=? WHERE id=?', ['{"board_ids":false,"sort":"latest"}', $id]),
                'private' => $this->db->run("UPDATE boards SET visibility='private' WHERE id=?", [$f['board']['id']]),
                'feature_off' => (new SettingRepository($this->db))->set('features', ['saved_feeds' => false]),
            };
            $this->db->run('UPDATE email_deliveries SET next_attempt_at=NULL WHERE id=?', [$job['id']]);
            self::assertSame(1, $this->worker($mailer)->run('2026-09-20 09:20:00')['sent']);
            $text = $mailer->to($f['recipient']['email'])[0]['text'];
            self::assertStringContainsString('Remaining subscribed source ' . $change, $text);
            self::assertStringNotContainsString('Original eligible topic', $text); self::assertStringNotContainsString('EXCLUDED', $text);
            $this->db->run('UPDATE users SET digest_hour=NULL WHERE id=?', [$uid]);
            (new SettingRepository($this->db))->set('features', ['saved_feeds' => true]);
        }
    }

    public function test_saved_source_disable_is_terminal_and_all_boards_remains_distinct_from_revoked_selection(): void
    {
        $f = $this->digestFixture(); $uid = (int) $f['recipient']['id'];
        (new SubscriptionRepository($this->db))->delete($uid, 'thread', $f['thread']['thread_id']);
        $id = $this->savedSource($f, [(int) $f['board']['id']]);
        $mailer = new ArrayMailer(); $mailer->failNext = true;
        $this->worker($mailer)->run('2026-09-20 09:15:00');
        $job = $this->db->fetch("SELECT * FROM email_deliveries WHERE user_id=? AND kind='digest'", [$uid]);
        $this->db->run('UPDATE saved_feed_filters SET digest_enabled=0 WHERE id=?', [$id]);
        $this->db->run('UPDATE email_deliveries SET next_attempt_at=NULL WHERE id=?', [$job['id']]);
        $this->worker($mailer)->run('2026-09-20 09:20:00');
        self::assertSame('suppressed', $this->db->fetchValue('SELECT status FROM email_deliveries WHERE id=?', [$job['id']]));
        $this->db->run('UPDATE saved_feed_filters SET digest_enabled=1 WHERE id=?', [$id]);
        self::assertSame(0, $this->worker($mailer)->run('2026-09-20 09:25:00')['sent']);
        $activity = new \App\Repository\DigestActivityRepository($this->db);
        $visibility = new \App\Service\NotificationVisibilityService($this->db);
        $service = new \App\Service\DigestService($activity, $visibility, $this->config);
        $this->db->run("UPDATE boards SET visibility='private' WHERE id=?", [$f['board']['id']]);
        $other = $this->makeThread($this->makeBoard($this->makeCategory()), $f['author'], 'Public all-board activity');
        $this->db->run("UPDATE posts SET created_at='2026-09-20 08:00:00' WHERE thread_id=?", [$other['thread_id']]);
        $viewer = $this->userEntity($f['recipient']);
        $max = (int) $this->db->fetchValue('SELECT MAX(id) FROM posts');
        $selected = $service->snapshot($viewer, '2026-09-19 09:15:00', '2026-09-20 09:15:00', $max);
        self::assertNull($service->render($viewer, $selected));
        $this->db->run('UPDATE saved_feed_filters SET filter_json=? WHERE id=?', ['{"board_ids":[],"sort":"latest"}', $id]);
        self::assertNull($service->render($viewer, $selected), 'Broadening a current filter cannot expand the queued original selection');
        $all = $service->snapshot($viewer, '2026-09-19 09:15:00', '2026-09-20 09:15:00', $max);
        self::assertStringContainsString('Public all-board activity', $service->render($viewer, $all)['text']);
        self::assertStringNotContainsString('Original eligible topic', $service->render($viewer, $all)['text']);
    }

    public function test_saved_source_validation_is_total_and_legacy_multiple_ids_stay_selected(): void
    {
        $f = $this->digestFixture(); $uid = (int) $f['recipient']['id'];
        (new SubscriptionRepository($this->db))->delete($uid, 'thread', $f['thread']['thread_id']);
        $otherBoard = $this->makeBoard($this->makeCategory()); $other = $this->makeThread($otherBoard, $f['author'], 'Second selected board');
        $excluded = $this->makeThread($this->makeBoard($this->makeCategory()), $f['author'], 'EXCLUDED unselected');
        $this->savedSource($f, [(int) $f['board']['id'], (int) $otherBoard['id']]);
        $this->db->run("UPDATE posts SET created_at='2026-09-20 08:00:00'");
        $mailer = new ArrayMailer(); $this->worker($mailer)->run('2026-09-20 09:15:00');
        $text = $mailer->to($f['recipient']['email'])[0]['text'];
        self::assertStringContainsString('Original eligible topic', $text); self::assertStringContainsString('Second selected board', $text); self::assertStringNotContainsString('EXCLUDED', $text);
        $payload = json_decode($this->db->fetchValue("SELECT payload FROM email_deliveries WHERE user_id=? AND kind='digest'", [$uid]), true);
        foreach ([null, [], ['id' => '1', 'filter' => ['board_ids' => [], 'sort' => 'latest']], ['id' => 1, 'filter' => ['board_ids' => [0], 'sort' => 'latest']], ['id' => 1, 'filter' => ['board_ids' => '1', 'sort' => 'latest']]] as $source) {
            $bad = $payload; $bad['sources']['saved_feeds'] = [$source];
            self::assertFalse(\App\Service\DigestService::validPayload($bad));
        }
        $bad = $payload; $bad['window_start_utc'] = "2026-09-19 09:15:00\0";
        self::assertFalse(\App\Service\DigestService::validPayload($bad));
    }

    public function test_queued_saved_digest_opt_out_remains_available_in_all_restricted_states(): void
    {
        $this->makeAdmin();
        foreach (['suspended', 'deactivated', 'pending_deletion', 'banned'] as $state) {
            $f = $this->digestFixture(); $uid = (int) $f['recipient']['id'];
            (new SubscriptionRepository($this->db))->delete($uid, 'thread', $f['thread']['thread_id']);
            $id = $this->savedSource($f, [(int) $f['board']['id']]);
            $mailer = new ArrayMailer(); $mailer->failNext = true;
            self::assertSame(1, $this->worker($mailer)->run('2026-09-20 09:15:00')['retrying']);
            $job = $this->db->fetch("SELECT * FROM email_deliveries WHERE user_id=? AND kind='digest'", [$uid]);
            $this->db->run("UPDATE users SET status=?, suspended_until='2099-01-01' WHERE id=?", [$state, $uid]);
            $this->db->run("UPDATE boards SET visibility='private' WHERE id=?", [$f['board']['id']]);
            $this->actingAs($f['recipient']);
            $this->assertRedirect($this->post('/settings/saved-feeds/' . $id, ['digest_enabled'=>'0']));
            $this->db->run('UPDATE email_deliveries SET next_attempt_at=NULL WHERE id=?', [$job['id']]);
            $this->worker($mailer)->run('2026-09-20 09:20:00');
            self::assertSame('suppressed', $this->db->fetchValue('SELECT status FROM email_deliveries WHERE id=?', [$job['id']]));
            self::assertSame(0, $mailer->count());
            $this->db->run('UPDATE users SET digest_hour=NULL WHERE id=?', [$uid]);
        }
    }

    public function test_saved_digest_narrowing_and_actor_privacy_exclude_unavailable_activity(): void
    {
        $f = $this->digestFixture(); $uid = (int) $f['recipient']['id'];
        (new SubscriptionRepository($this->db))->delete($uid, 'thread', $f['thread']['thread_id']);
        $id = $this->savedSource($f, []);
        $otherBoard = $this->makeBoard($this->makeCategory());
        $this->makeThread($otherBoard, $f['author'], 'EXCLUDED after narrowing');
        foreach (['is_anonymous', 'is_pending', 'is_deleted'] as $field) {
            $t = $this->makeThread($f['board'], $f['author'], 'EXCLUDED ' . $field);
            $this->db->run("UPDATE posts SET $field=1 WHERE thread_id=?", [$t['thread_id']]);
        }
        $blocked = $this->makeUser();
        $this->makeThread($f['board'], $blocked, 'EXCLUDED blocked actor');
        (new \App\Repository\BlockRepository($this->db))->block((int) $blocked['id'], $uid);
        $this->db->run("UPDATE posts SET created_at='2026-09-20 08:00:00'");
        $mailer = new ArrayMailer(); $mailer->failNext = true;
        $this->worker($mailer)->run('2026-09-20 09:15:00');
        $job = $this->db->fetch("SELECT * FROM email_deliveries WHERE user_id=? AND kind='digest'", [$uid]);
        $this->db->run('UPDATE saved_feed_filters SET filter_json=? WHERE id=?', [json_encode(['board_ids'=>[(int) $f['board']['id']], 'sort'=>'latest']), $id]);
        $this->db->run('UPDATE email_deliveries SET next_attempt_at=NULL WHERE id=?', [$job['id']]);
        self::assertSame(1, $this->worker($mailer)->run('2026-09-20 09:20:00')['sent']);
        $text = $mailer->to($f['recipient']['email'])[0]['text'];
        self::assertStringContainsString('Original eligible topic (1 new)', $text);
        self::assertStringNotContainsString('EXCLUDED', $text);
    }

    public function test_saved_digest_explicit_read_access_and_all_discovery_intersect_at_retry(): void
    {
        $f = $this->digestFixture(); $uid = (int) $f['recipient']['id'];
        (new SubscriptionRepository($this->db))->delete($uid, 'thread', $f['thread']['thread_id']);
        $id = $this->savedSource($f, [(int) $f['board']['id']]);
        $this->db->run("UPDATE boards SET visibility='hidden' WHERE id=?", [$f['board']['id']]);
        $service = new \App\Service\DigestService(new \App\Repository\DigestActivityRepository($this->db), new \App\Service\NotificationVisibilityService($this->db), $this->config);
        $viewer = $this->userEntity($f['recipient']);
        $payload = $service->snapshot($viewer, '2026-09-19 09:15:00', '2026-09-20 09:15:00', (int) $this->db->fetchValue('SELECT MAX(id) FROM posts'));
        $rendered = $service->render($viewer, $payload);
        self::assertNotNull($rendered);
        self::assertStringContainsString('Original eligible topic', $rendered['text']);
        $this->db->run('UPDATE saved_feed_filters SET filter_json=? WHERE id=?', ['{"board_ids":[],"sort":"latest"}', $id]);
        self::assertNull($service->render($viewer, $payload), 'Current all-board discovery excludes hidden boards');
        $all = $service->snapshot($viewer, '2026-09-19 09:15:00', '2026-09-20 09:15:00', (int) $this->db->fetchValue('SELECT MAX(id) FROM posts'));
        $this->db->run('UPDATE saved_feed_filters SET filter_json=? WHERE id=?', [json_encode(['board_ids'=>[(int) $f['board']['id']], 'sort'=>'latest']), $id]);
        self::assertNull($service->render($viewer, $all), 'Original all-board discovery cannot expand to a hidden direct scope');
        $this->db->run("UPDATE boards SET visibility='private' WHERE id=?", [$f['board']['id']]);
        $this->db->run("UPDATE users SET role='admin' WHERE id=?", [$uid]);
        self::assertNotNull($service->render($this->userEntity($this->users()->find($uid)), $payload));
        $this->db->run("UPDATE users SET role='user' WHERE id=?", [$uid]);
        $mods = new \App\Repository\BoardModeratorRepository($this->db); $mods->assign((int) $f['board']['id'], $uid);
        self::assertNotNull($service->render($viewer, $payload));
        $mods->unassign((int) $f['board']['id'], $uid);
        self::assertNull($service->render($viewer, $payload));
    }

    public function test_object_shaped_saved_filters_are_excluded_from_snapshots_and_current_retry_sources(): void
    {
        $f = $this->digestFixture(); $uid = (int) $f['recipient']['id'];
        (new SubscriptionRepository($this->db))->delete($uid, 'thread', $f['thread']['thread_id']);
        $id = $this->savedSource($f, []);
        $service = new \App\Service\DigestService(new \App\Repository\DigestActivityRepository($this->db), new \App\Service\NotificationVisibilityService($this->db), $this->config);
        $viewer = $this->userEntity($f['recipient']); $max = (int) $this->db->fetchValue('SELECT MAX(id) FROM posts');
        $valid = $service->snapshot($viewer, '2026-09-19 09:15:00', '2026-09-20 09:15:00', $max);
        self::assertNotNull($service->render($viewer, $valid));
        foreach (['{}', '{"0":' . $f['board']['id'] . '}'] as $ids) {
            $this->db->run('UPDATE saved_feed_filters SET filter_json=? WHERE id=?', ['{"board_ids":' . $ids . ',"sort":"latest"}', $id]);
            $snapshot = $service->snapshot($viewer, '2026-09-19 09:15:00', '2026-09-20 09:15:00', $max);
            self::assertSame([], $snapshot['sources']['saved_feeds']);
            self::assertNull($service->render($viewer, $snapshot));
            self::assertNull($service->render($viewer, $valid), 'A valid original source cannot use a malformed current scope');
        }
    }

}
