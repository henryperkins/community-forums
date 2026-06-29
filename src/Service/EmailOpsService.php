<?php

declare(strict_types=1);

namespace App\Service;

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
    ) {
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

    /** Re-queue a failed delivery for the worker. No-op (no audit) when not failed. */
    public function requeueFailed(User $admin, int $id): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->db->transaction(function () use ($admin, $id): void {
            if ($this->deliveries->requeue($id) !== 1) {
                return;
            }
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'email_requeued',
                'target_type' => 'setting',
                'target_id' => 0,
                'after' => ['delivery_id' => $id],
            ]);
        });
    }
}
