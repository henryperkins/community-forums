<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Mail\Mailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;
use Throwable;

/**
 * Admin email-operations service (ADMIN §7.5/§7.6/§10.1). Owns the test-send,
 * manual suppression add/remove (with the §7.6 per-user subscription cascade) and
 * failed-delivery requeue. Every mutation runs through WriteGate (state beats
 * role) and writes one moderation_log audit row, mirroring WebhookService.
 */
final class EmailOpsService
{
    public function __construct(
        private Database $db,
        private EmailDeliveryRepository $deliveries,
        private EmailSuppressionRepository $suppress,
        private SubscriptionRepository $subs,
        private UserRepository $users,
        private ModerationLogRepository $log,
        private WriteGate $writeGate,
        private Mailer $mailer,
        private EmailDomainVerifier $domainVerifier,
        private ?Config $config = null,
    ) {
    }

    /**
     * The delivery-ops dashboard read model (PR #44 spec §4): the whole
     * /admin/email assembly, with has_next computed from the real filtered
     * total — 1-based pages, so page*per_page < total.
     *
     * @return array<string,mixed>
     */
    public function dashboardModel(?string $status, ?string $kind, ?string $email, int $page, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $email = $email !== null && trim($email) !== '' ? trim($email) : null;
        $domain = $this->domainVerifier->current();
        $total = $this->deliveries->count($status, $kind, $email);

        return [
            'deliveries' => $this->deliveries->recent($perPage, ($page - 1) * $perPage, $status, $kind, $email),
            'total' => $total,
            'status_counts' => $this->deliveries->statusCounts(),
            'suppressions' => $this->suppress->list(100, 0, null),
            'suppression_count' => $this->suppress->count(null),
            'mailer_configured' => $this->mailer->isConfigured(),
            'mail_from' => (string) ($this->config?->get('mail.from', '') ?? ''),
            'domain_status' => $domain,
            'send_blocked' => !empty($domain['required']) && empty($domain['allowed']),
            'f_status' => $status ?? '',
            'f_kind' => $kind ?? '',
            'f_email' => $email ?? '',
            'page' => $page,
            'per_page' => $perPage,
            'has_next' => $page * $perPage < $total,
        ];
    }

    /**
     * Queue + synchronously send a one-off test message to the admin's own
     * address. Fails closed when the transport is not configured (no From).
     */
    public function sendTest(User $admin): void
    {
        $this->writeGate->assertCanWrite($admin);
        if (!$this->mailer->isConfigured()) {
            throw new ValidationException(['email' => 'Configure your sending domain first.']);
        }
        if (($blocked = $this->domainVerifier->blockedReason()) !== null) {
            throw new ValidationException(['email' => $blocked]);
        }

        $email = $admin->email();
        $subject = 'RetroBoards email delivery test';
        $id = $this->db->transaction(function () use ($admin, $email, $subject): int {
            $newId = $this->deliveries->enqueue($admin->id(), $email, 'test', $subject, null);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'email_test_sent',
                'target_type' => 'setting',
                'target_id' => 0,
                'after' => ['email' => $email],
            ]);
            return $newId;
        });

        try {
            $messageId = $this->mailer->send(
                $email,
                $subject,
                "This is a test email from RetroBoards. If you received it, your outbound email is working.",
            );
            $this->deliveries->markSent($id, $messageId);
        } catch (Throwable $e) {
            $this->deliveries->markFailed($id, $e->getMessage());
            // Surface the real failure to the operator instead of flashing
            // success: test-send exists to verify deliverability (ADMIN §7.6).
            // The controller's existing ValidationException catch renders this.
            throw new ValidationException(['email' => 'Test send failed: ' . $e->getMessage()]);
        }
    }

    /** Manually suppress an address + cascade its subscriptions' email channel off. */
    public function manualSuppress(User $admin, string $email): void
    {
        $this->writeGate->assertCanWrite($admin);
        $email = trim($email);
        if ($email === '' || !str_contains($email, '@')) {
            throw new ValidationException(['email' => 'Enter a valid email address.']);
        }
        $this->db->transaction(function () use ($admin, $email): void {
            $this->suppress->suppress($email, 'manual');
            $user = $this->users->findByEmail($email);
            if ($user !== null) {
                $this->subs->disableEmailForUser((int) $user['id']);
            }
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'email_suppressed',
                'target_type' => 'setting',
                'target_id' => 0,
                'after' => ['email' => strtolower($email)],
            ]);
        });
    }

    /** Remove an address from the suppression list + re-enable its subscriptions' email channel. */
    public function unsuppress(User $admin, string $email): void
    {
        $this->writeGate->assertCanWrite($admin);
        $email = trim($email);
        if ($email === '') {
            throw new ValidationException(['email' => 'Enter an email address.']);
        }
        $this->db->transaction(function () use ($admin, $email): void {
            $this->suppress->unsuppress($email);
            $user = $this->users->findByEmail($email);
            if ($user !== null) {
                $this->subs->enableEmailForUser((int) $user['id']);
            }
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'email_unsuppressed',
                'target_type' => 'setting',
                'target_id' => 0,
                'after' => ['email' => strtolower($email)],
            ]);
        });
    }

    /**
     * Re-queue a failed delivery for the worker. Returns whether a row was
     * actually requeued so the caller can report the idempotent no-op honestly
     * (a delivery that is not in the failed state is left untouched, no audit).
     */
    public function requeueFailed(User $admin, int $id): bool
    {
        $this->writeGate->assertCanWrite($admin);
        return (bool) $this->db->transaction(function () use ($admin, $id): bool {
            if ($this->deliveries->requeue($id) !== 1) {
                return false;
            }
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'email_requeued',
                'target_type' => 'setting',
                'target_id' => 0,
                'after' => ['delivery_id' => $id],
            ]);
            return true;
        });
    }
}
