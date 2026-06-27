<?php

declare(strict_types=1);

namespace App\Worker;

use App\Core\Database;
use App\Repository\ModerationLogRepository;

/**
 * 90-day IP-retention purge (P3-05, ADMIN §5.5). Anonymises the login/post IPs
 * captured in Phase 2 (`sessions.ip`, `posts.ip`) once they are older than the
 * configured retention window, closing the Phase-2 IP-capture seam. The action
 * is idempotent (a second run touches nothing new) and audited with a system
 * actor so the privacy operation is itself reviewable.
 */
final class IpRetentionPurger
{
    public function __construct(
        private Database $db,
        private ModerationLogRepository $log,
        private int $retentionDays = 90,
    ) {
    }

    /**
     * @param string|null $now UTC "Y-m-d H:i:s"; defaults to current time.
     * @return array{sessions:int,posts:int,cutoff:string,days:int}
     */
    public function run(?string $now = null): array
    {
        $days = max(0, $this->retentionDays);
        $base = $now !== null ? strtotime($now . ' UTC') : time();
        $cutoff = gmdate('Y-m-d H:i:s', ($base !== false ? $base : time()) - $days * 86400);

        $sessions = $this->db->run(
            'UPDATE sessions SET ip = NULL WHERE ip IS NOT NULL AND created_at < ?',
            [$cutoff],
        )->rowCount();

        $posts = $this->db->run(
            'UPDATE posts SET ip = NULL WHERE ip IS NOT NULL AND created_at < ?',
            [$cutoff],
        )->rowCount();

        if ($sessions > 0 || $posts > 0) {
            $this->log->log([
                'actor_id' => null, // system actor
                'action' => 'ip_retention_purge',
                'target_type' => 'setting', // a retention/config-level operation (no per-row target)
                'target_id' => 0,
                'reason' => "Anonymised IPs older than {$days} day(s).",
                'before' => null,
                'after' => ['sessions' => $sessions, 'posts' => $posts, 'cutoff' => $cutoff, 'days' => $days],
            ]);
        }

        return ['sessions' => $sessions, 'posts' => $posts, 'cutoff' => $cutoff, 'days' => $days];
    }
}
