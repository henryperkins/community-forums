<?php

declare(strict_types=1);

namespace App\Worker;

use App\Core\Config;
use App\Core\Database;
use App\Mail\MailException;
use App\Mail\Mailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\SettingRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Timezone-aware daily digest (P2-04). For each user whose local hour matches
 * their digest_hour and who has not yet received a digest today, gather new,
 * accessible activity since their watermark (last_daily_digest_at) in the
 * threads/boards they daily-subscribe to. A digest is:
 *  - never sent empty (no eligible activity ⇒ no mail);
 *  - never duplicated (the watermark advances once per run that fires);
 *  - suppression-aware and fail-closed when the transport is unconfigured.
 */
final class DailyDigestWorker
{
    public function __construct(
        private Database $db,
        private EmailDeliveryRepository $deliveries,
        private EmailSuppressionRepository $suppress,
        private Mailer $mailer,
        private Config $config,
        private ?SettingRepository $settings = null,
    ) {
    }

    /**
     * @param string $nowUtc 'Y-m-d H:i:s' UTC (injectable for deterministic tests)
     * @return array{sent:int,skipped_empty:int,suppressed:int}
     */
    public function run(string $nowUtc): array
    {
        $stats = ['sent' => 0, 'skipped_empty' => 0, 'suppressed' => 0];
        if (!$this->mailer->isConfigured()) {
            return $stats;
        }

        $now = new DateTimeImmutable($nowUtc, new DateTimeZone('UTC'));

        $users = $this->db->fetchAll(
            "SELECT id, email, status, timezone, digest_hour, last_daily_digest_at
             FROM users
             WHERE digest_hour IS NOT NULL AND timezone IS NOT NULL AND status <> 'banned'",
        );

        foreach ($users as $u) {
            if (!$this->isDue($u, $now)) {
                continue;
            }
            $email = (string) $u['email'];
            if ($this->suppress->isSuppressed($email)) {
                $this->advanceWatermark((int) $u['id'], $nowUtc);
                $stats['suppressed']++;
                continue;
            }

            $since = (string) ($u['last_daily_digest_at'] ?? '') ?: $now->modify('-1 day')->format('Y-m-d H:i:s');
            $threads = $this->digestActivity((int) $u['id'], $since);

            // Advance the watermark whether or not we send, so a fired hour runs
            // exactly once per day; only send when there is real activity.
            $this->advanceWatermark((int) $u['id'], $nowUtc);

            if ($threads === []) {
                $stats['skipped_empty']++;
                continue;
            }

            $rendered = $this->render($threads);
            $deliveryId = $this->deliveries->enqueue((int) $u['id'], $email, 'digest', $rendered['subject'], null);
            try {
                $messageId = $this->mailer->send($email, $rendered['subject'], $rendered['text'], $rendered['html']);
                $this->deliveries->markSent($deliveryId, $messageId);
                $stats['sent']++;
            } catch (MailException | Throwable $e) {
                $this->deliveries->markFailed($deliveryId, $e->getMessage());
            }
        }

        return $stats;
    }

    /** @param array<string,mixed> $u */
    private function isDue(array $u, DateTimeImmutable $now): bool
    {
        try {
            $tz = new DateTimeZone((string) $u['timezone']);
        } catch (Throwable) {
            return false;
        }
        $local = $now->setTimezone($tz);
        if ((int) $local->format('G') !== (int) $u['digest_hour']) {
            return false;
        }
        // Already sent today (in local time)?
        $last = $u['last_daily_digest_at'];
        if ($last === null) {
            return true;
        }
        $lastLocal = (new DateTimeImmutable((string) $last, new DateTimeZone('UTC')))->setTimezone($tz);
        return $lastLocal->format('Y-m-d') !== $local->format('Y-m-d');
    }

    /**
     * New, non-deleted posts since $since (excluding the user's own) in threads
     * the user daily-subscribes to (thread-level, or board-level where no thread
     * subscription overrides), grouped by thread.
     *
     * @return array<int,array{thread_id:int,title:string,slug:string,n:int}>
     */
    private function digestActivity(int $userId, string $since): array
    {
        $rows = $this->db->fetchAll(
            "SELECT t.id AS thread_id, t.title, t.slug, COUNT(*) AS n
             FROM posts p
             JOIN threads t ON t.id = p.thread_id
             JOIN boards b ON b.id = t.board_id
             WHERE p.is_deleted = 0 AND p.is_pending = 0 AND p.user_id <> :uid AND p.created_at > :since
               AND t.is_deleted = 0 AND t.is_pending = 0
               AND (
                 EXISTS (SELECT 1 FROM subscriptions s
                         WHERE s.user_id = :uid2 AND s.target_type = 'thread' AND s.target_id = t.id
                           AND s.frequency = 'daily' AND s.email_enabled = 1)
                 OR (
                   EXISTS (SELECT 1 FROM subscriptions sb
                           WHERE sb.user_id = :uid3 AND sb.target_type = 'board' AND sb.target_id = t.board_id
                             AND sb.frequency = 'daily' AND sb.email_enabled = 1)
                   AND NOT EXISTS (SELECT 1 FROM subscriptions so
                           WHERE so.user_id = :uid4 AND so.target_type = 'thread' AND so.target_id = t.id)
                 )
               )
             GROUP BY t.id, t.title, t.slug
             ORDER BY MAX(p.created_at) DESC",
            ['uid' => $userId, 'since' => $since, 'uid2' => $userId, 'uid3' => $userId, 'uid4' => $userId],
        );
        return array_map(static fn (array $r): array => [
            'thread_id' => (int) $r['thread_id'],
            'title' => (string) $r['title'],
            'slug' => (string) $r['slug'],
            'n' => (int) $r['n'],
        ], $rows);
    }

    private function siteName(): string
    {
        $fallback = (string) $this->config->get('app.name', 'RetroBoards');
        if ($this->settings === null) {
            return $fallback;
        }
        try {
            return $this->settings->getString('site_name', $fallback);
        } catch (Throwable) {
            return $fallback;
        }
    }

    /**
     * @param array<int,array{thread_id:int,title:string,slug:string,n:int}> $threads
     * @return array{subject:string,text:string,html:string}
     */
    private function render(array $threads): array
    {
        $base = rtrim((string) $this->config->get('app.url', ''), '/');
        $app = $this->siteName();
        $subject = 'Your daily digest — ' . count($threads) . ' active ' . (count($threads) === 1 ? 'thread' : 'threads');
        $lines = [];
        $html = ['<p>New activity since your last digest:</p><ul>'];
        foreach ($threads as $t) {
            $url = $base . '/t/' . $t['thread_id'] . '-' . $t['slug'];
            $lines[] = sprintf('- %s (%d new) %s', $t['title'], $t['n'], $url);
            $html[] = '<li><a href="' . htmlspecialchars($url, ENT_QUOTES) . '">' . htmlspecialchars($t['title'], ENT_QUOTES) . '</a> (' . $t['n'] . ' new)</li>';
        }
        $html[] = '</ul>';
        return [
            'subject' => $subject,
            'text' => "$app daily digest\n\n" . implode("\n", $lines),
            'html' => implode('', $html),
        ];
    }

    private function advanceWatermark(int $userId, string $nowUtc): void
    {
        $this->db->run('UPDATE users SET last_daily_digest_at = ? WHERE id = ?', [$nowUtc, $userId]);
    }
}
