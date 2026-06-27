<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Repository\ModerationLogRepository;
use App\Worker\IpRetentionPurger;
use Tests\Support\TestCase;

/**
 * P3-05 / ADMIN §5.5: the 90-day IP-retention purge anonymises sessions.ip and
 * posts.ip past the cutoff, leaves recent rows intact, is idempotent, and audits
 * the privacy operation with a system actor.
 */
final class IpRetentionPurgeTest extends TestCase
{
    public function test_purges_old_ips_keeps_recent_and_audits(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'ipboard']);
        $author = $this->makeUser(['username' => 'ipuser']);

        // An old post (100 days ago) with an IP, and a fresh one.
        $oldThread = $this->makeThread($board, $author, 'Old', 'old body');
        $oldPostId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ?', [$oldThread['thread_id']]);
        $this->db->run('UPDATE posts SET ip = ?, created_at = ? WHERE id = ?', [inet_pton('203.0.113.5'), gmdate('Y-m-d H:i:s', time() - 100 * 86400), $oldPostId]);

        $freshThread = $this->makeThread($board, $author, 'Fresh', 'fresh body');
        $freshPostId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ?', [$freshThread['thread_id']]);
        $this->db->run('UPDATE posts SET ip = ? WHERE id = ?', [inet_pton('203.0.113.6'), $freshPostId]);

        // An old session with an IP.
        $sid = bin2hex(random_bytes(16));
        $this->db->run(
            'INSERT INTO sessions (id, user_id, csrf_secret, ip, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?)',
            [hash('sha256', $sid), (int) $author['id'], bin2hex(random_bytes(16)), inet_pton('203.0.113.9'), gmdate('Y-m-d H:i:s', time() - 100 * 86400), gmdate('Y-m-d H:i:s', time() + 86400)],
        );

        $purger = new IpRetentionPurger($this->db, new ModerationLogRepository($this->db), 90);
        $stats = $purger->run();

        self::assertSame(1, $stats['posts']);
        self::assertSame(1, $stats['sessions']);
        self::assertNull($this->db->fetchValue('SELECT ip FROM posts WHERE id = ?', [$oldPostId]));
        self::assertNotNull($this->db->fetchValue('SELECT ip FROM posts WHERE id = ?', [$freshPostId]));

        // Audited with a system actor.
        self::assertNotFalse($this->db->fetchValue(
            "SELECT 1 FROM moderation_log WHERE action = 'ip_retention_purge' AND actor_id IS NULL",
        ));

        // Idempotent: a second run anonymises nothing new.
        $again = $purger->run();
        self::assertSame(0, $again['posts']);
        self::assertSame(0, $again['sessions']);
    }
}
