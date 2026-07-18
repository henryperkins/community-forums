<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Mail\Mailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\ModerationLogRepository;
use App\Service\ThreadIntelligence\ThreadIntelligenceAdminService;

final class AdminDashboardService
{
    public function __construct(
        private Database $db,
        private EmailDeliveryRepository $emailDeliveries,
        private ModerationLogRepository $moderationLog,
        private FeatureFlags $features,
        private Mailer $mailer,
        private EmailDomainVerifier $emailDomainVerifier,
        private ?ThreadIntelligenceAdminService $threadIntelligence = null,
        private ?AuditQueryService $auditQuery = null,
    ) {
    }

    /**
     * @return array{
     *   queue_cards:list<array{title:string,count:int,detail:string,href:?string,status:string}>,
     *   activity_cards:list<array{title:string,count:int,detail:string,href:?string}>,
     *   attention:list<array{label:string,href:?string}>,
     *   audit:list<array<string,mixed>>
     * }
     */
    public function summary(): array
    {
        $counts = [
            'reports' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM reports WHERE status IN ('open', 'triaged')"),
            'pending_threads' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM threads WHERE is_pending = 1'),
            'pending_replies' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM posts WHERE is_pending = 1 AND is_op = 0'),
            'new_users_today' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM users WHERE created_at >= UTC_DATE()'),
            'active_users' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM users WHERE last_seen_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE'),
            'failed_emails' => (int) (($this->emailDeliveries->statusCounts())['failed'] ?? 0),
        ];

        $audit = $this->moderationLog->recent(10);
        if ($this->auditQuery !== null) {
            $audit = $this->auditQuery->enrich($audit);
        }

        $reportsEnabled = $this->features->enabled('moderation_queue');
        $emailEnabled = $this->features->enabled('email');
        $pendingApprovals = $counts['pending_threads'] + $counts['pending_replies'];
        $mailerConfigured = $this->mailer->isConfigured();
        $sendBlocked = $this->emailDomainVerifier->blockedReason() !== null;

        $queueCards = [
            [
                'title' => 'Reports',
                'count' => $counts['reports'],
                'detail' => $reportsEnabled ? 'Open or triaged moderation queue items' : 'Moderation queue disabled',
                'href' => $reportsEnabled ? '/mod/reports' : null,
                'status' => !$reportsEnabled ? 'unavailable' : ($counts['reports'] > 0 ? 'attention' : 'clear'),
            ],
            [
                'title' => 'Approval hold',
                'count' => $pendingApprovals,
                'detail' => $counts['pending_threads'] . ' threads · ' . $counts['pending_replies'] . ' replies',
                'href' => '/mod/approvals',
                'status' => $pendingApprovals > 0 ? 'attention' : 'clear',
            ],
            [
                'title' => 'Email failures',
                'count' => $counts['failed_emails'],
                'detail' => !$emailEnabled
                    ? 'Email tools disabled'
                    : (!$mailerConfigured
                        ? 'Sending is not configured'
                        : ($sendBlocked ? 'Sending blocked pending SPF/DKIM' : 'Delivery log and suppressions')),
                'href' => $emailEnabled ? '/admin/email?status=failed' : null,
                'status' => !$emailEnabled
                    ? 'unavailable'
                    : (($counts['failed_emails'] > 0 || !$mailerConfigured || $sendBlocked) ? 'attention' : 'clear'),
            ],
        ];

        $threadIntelligence = null;
        if ($this->features->enabled('community_memory') || $this->features->enabled('automated_context')) {
            $threadIntelligence = $this->threadIntelligence?->overview();
            if ($threadIntelligence === null) {
                $queueCards[] = [
                    'title' => 'Thread Intelligence',
                    'count' => 0,
                    'detail' => 'Operational status is unavailable',
                    'href' => '/admin/thread-intelligence',
                    'status' => 'unavailable',
                ];
            } else {
                $threadAttention = (int) $threadIntelligence['warning_count'] > 0
                    || (int) $threadIntelligence['queue_attention'] > 0
                    || (string) $threadIntelligence['heartbeat'] !== 'healthy';
                $queueCards[] = [
                    'title' => 'Thread Intelligence',
                    'count' => (int) $threadIntelligence['warning_count'],
                    'detail' => (int) $threadIntelligence['queue_attention'] . ' threads need operator attention · worker ' . (string) $threadIntelligence['heartbeat'],
                    'href' => '/admin/thread-intelligence',
                    'status' => $threadAttention ? 'attention' : 'clear',
                ];
            }
        }

        $attention = [];
        if ($reportsEnabled && $counts['reports'] > 0) {
            $attention[] = [
                'label' => $counts['reports'] === 1
                    ? '1 report is open or triaged in the moderation queue.'
                    : $counts['reports'] . ' reports are open or triaged in the moderation queue.',
                'href' => '/mod/reports',
            ];
        }
        if ($pendingApprovals > 0) {
            $attention[] = [
                'label' => $pendingApprovals . ' queued approvals need review (' . $counts['pending_threads'] . ' threads, ' . $counts['pending_replies'] . ' replies).',
                'href' => '/mod/approvals',
            ];
        }
        if ($emailEnabled && $counts['failed_emails'] > 0) {
            $attention[] = [
                'label' => $counts['failed_emails'] === 1
                    ? '1 failed email delivery is waiting for operator follow-up.'
                    : $counts['failed_emails'] . ' failed email deliveries are waiting for operator follow-up.',
                'href' => '/admin/email?status=failed',
            ];
        }
        if ($emailEnabled && !$mailerConfigured) {
            $attention[] = ['label' => 'Email sending is not configured yet.', 'href' => '/admin/email'];
        }
        if ($emailEnabled && $sendBlocked) {
            $attention[] = ['label' => 'Email sending is blocked until SPF and DKIM pass.', 'href' => '/admin/email'];
        }
        if ($threadIntelligence !== null && (int) $threadIntelligence['warning_count'] > 0) {
            $warnings = (int) $threadIntelligence['warning_count'];
            $attention[] = [
                'label' => $warnings . ' Thread Intelligence warning' . ($warnings === 1 ? '' : 's') . ' need operator review.',
                'href' => '/admin/thread-intelligence',
            ];
        }

        return [
            'queue_cards' => $queueCards,
            'activity_cards' => [
                [
                    'title' => 'New users today',
                    'count' => $counts['new_users_today'],
                    'detail' => 'Accounts created since 00:00 UTC',
                    'href' => '/admin/users',
                ],
                [
                    'title' => 'Active now',
                    'count' => $counts['active_users'],
                    'detail' => 'Seen in the last 15 minutes',
                    'href' => '/admin/users',
                ],
            ],
            'attention' => $attention,
            'audit' => $audit,
        ];
    }
}
