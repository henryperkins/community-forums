<?php

declare(strict_types=1);

namespace App\Worker;

use App\Core\Database;
use App\Repository\ModerationLogRepository;

/**
 * 90-day IP-retention purge (P3-05, ADMIN §5.5). Anonymises the captured IPs
 * (`sessions.ip`, `posts.ip` from Phase 2; `invitation_redemptions.ip` from
 * P5-13) once they are older than the configured retention window. The action
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
     * @return array{sessions:int,posts:int,invitation_redemptions:int,cutoff:string,days:int}
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

        $redemptions = $this->db->run(
            'UPDATE invitation_redemptions SET ip = NULL WHERE ip IS NOT NULL AND redeemed_at < ?',
            [$cutoff],
        )->rowCount();

        if ($sessions > 0 || $posts > 0 || $redemptions > 0) {
            $this->log->log([
                'actor_id' => null, // system actor
                'action' => 'ip_retention_purge',
                'target_type' => 'setting', // a retention/config-level operation (no per-row target)
                'target_id' => 0,
                'reason' => "Anonymised IPs older than {$days} day(s).",
                'before' => null,
                'after' => ['sessions' => $sessions, 'posts' => $posts, 'invitation_redemptions' => $redemptions, 'cutoff' => $cutoff, 'days' => $days],
            ]);
        }

        return ['sessions' => $sessions, 'posts' => $posts, 'invitation_redemptions' => $redemptions, 'cutoff' => $cutoff, 'days' => $days];
    }
}
