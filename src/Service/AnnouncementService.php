<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\EmailDeliveryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\NotificationRepository;
use App\Repository\SettingRepository;
use App\Security\WriteGate;

/**
 * Admin announcements (ADMIN §7.4, PHASE_2_PLAN §7; SCHEMA §7 #13). Owns the
 * site-wide banner stored in settings.site_announcement — a JSON key carrying an
 * active flag, message, dismissible flag and an incrementing version — plus an
 * opt-in in-app broadcast. NO email channel and NO `announcements` table: the
 * broadcast reuses notifications.type='announcement'. The version increments on
 * every publish so a member's per-version dismissal never hides a newer banner.
 * Publish history is derived from the audit trail (set_announcement rows), not
 * a table of its own.
 */
final class AnnouncementService
{
    private const MAX_MESSAGE = 500;

    public function __construct(
        private Database $db,
        private SettingRepository $settings,
        private ModerationLogRepository $log,
        private NotificationRepository $notifications,
        private EmailDeliveryRepository $deliveries,
        private WriteGate $writeGate,
    ) {
    }

    public function setBanner(User $admin, string $message, bool $dismissible, bool $inAppBroadcast, bool $emailBroadcast = false): void
    {
        $this->assertAdmin($admin);

        $message = trim($message);
        if ($message === '' || mb_strlen($message) > self::MAX_MESSAGE) {
            throw new ValidationException(
                ['message' => 'Announcement message must be 1–' . self::MAX_MESSAGE . ' characters.'],
                ['message' => $message, 'dismissible' => $dismissible, 'broadcast' => $inAppBroadcast, 'broadcast_email' => $emailBroadcast],
            );
        }

        $version = $this->currentVersion() + 1;

        $this->db->transaction(function () use ($admin, $message, $dismissible, $inAppBroadcast, $emailBroadcast, $version): void {
            $this->settings->set('site_announcement', [
                'active' => true,
                'message' => $message,
                'dismissible' => $dismissible,
                'version' => $version,
            ]);
            if ($inAppBroadcast) {
                $this->notifications->broadcastAnnouncement($admin->id());
            }
            $emailCount = 0;
            if ($emailBroadcast) {
                $emailCount = $this->deliveries->enqueueSystemForActiveUsers(
                    $admin->id(),
                    'Announcement from ' . $this->siteName(),
                    ['type' => 'announcement', 'version' => $version, 'message' => $message],
                    'announcement:' . $version . ':',
                );
            }
            // The message is part of the audit payload so the console can show
            // a publish history (ADMIN §7.4 "audited") without a new table.
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'set_announcement',
                'target_type' => 'setting',
                'target_id' => 0,
                'reason' => 'site_announcement',
                'after' => ['active' => true, 'version' => $version, 'message' => $message, 'broadcast' => $inAppBroadcast, 'email_broadcast' => $emailBroadcast, 'email_count' => $emailCount],
            ]);
        });
    }

    public function clearBanner(User $admin): void
    {
        $this->assertAdmin($admin);
        $version = $this->currentVersion();

        $this->db->transaction(function () use ($admin, $version): void {
            // Preserve the version so it never decreases across publish/clear cycles.
            $this->settings->set('site_announcement', ['active' => false, 'version' => $version]);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'clear_announcement',
                'target_type' => 'setting',
                'target_id' => 0,
                'reason' => 'site_announcement',
                'after' => ['active' => false],
            ]);
        });
    }

    /**
     * Recent publish/clear history for the console, straight from the audit
     * trail. Older set_announcement rows predate the message-in-payload change
     * and render without a message.
     *
     * @return array<int,array{when:string,actor:?string,action:string,version:?int,message:?string,broadcast:bool,email_broadcast:bool}>
     */
    public function recentHistory(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $rows = $this->db->fetchAll(
            "SELECT m.action, m.after_json, m.created_at, u.username AS actor_username
             FROM moderation_log m
             LEFT JOIN users u ON u.id = m.actor_id
             WHERE m.action IN ('set_announcement', 'clear_announcement')
             ORDER BY m.id DESC
             LIMIT " . $limit,
        );

        $history = [];
        foreach ($rows as $row) {
            $after = json_decode((string) ($row['after_json'] ?? ''), true);
            $after = is_array($after) ? $after : [];
            $history[] = [
                'when' => (string) $row['created_at'],
                'actor' => isset($row['actor_username']) ? (string) $row['actor_username'] : null,
                'action' => (string) $row['action'],
                'version' => isset($after['version']) && is_numeric($after['version']) ? (int) $after['version'] : null,
                'message' => isset($after['message']) && is_string($after['message']) ? $after['message'] : null,
                'broadcast' => !empty($after['broadcast']),
                'email_broadcast' => !empty($after['email_broadcast']),
            ];
        }
        return $history;
    }

    private function currentVersion(): int
    {
        $current = $this->settings->get('site_announcement', []);
        if (is_array($current) && isset($current['version']) && is_numeric($current['version'])) {
            return (int) $current['version'];
        }
        return 0;
    }

    private function siteName(): string
    {
        return $this->settings->getString('site_name', 'RetroBoards');
    }

    private function assertAdmin(User $admin): void
    {
        if (!$admin->isAdmin()) {
            throw new ForbiddenException('Administrator access required.');
        }
        // State beats role: a suspended/banned admin cannot publish.
        $this->writeGate->assertCanWrite($admin);
    }
}
