<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Mail\ArrayMailer;
use App\Mail\Mailer;
use App\Mail\SendmailMailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailDomainStatusRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;
use App\Service\EmailDomainVerifier;
use App\Service\EmailOpsService;
use Tests\Support\TestCase;

final class EmailOpsServiceTest extends TestCase
{
    private function service(?Mailer $mailer = null): EmailOpsService
    {
        return new EmailOpsService(
            $this->db,
            new EmailDeliveryRepository($this->db),
            new EmailSuppressionRepository($this->db),
            new SubscriptionRepository($this->db),
            new UserRepository($this->db),
            new ModerationLogRepository($this->db),
            new WriteGate(),
            $mailer ?? new ArrayMailer(),
            new EmailDomainVerifier($this->config, new SettingRepository($this->db), new EmailDomainStatusRepository($this->db)),
        );
    }

    public function test_send_test_enqueues_a_test_row_marks_sent_and_audits(): void
    {
        $admin = $this->userEntity($this->makeAdmin(['email' => 'ops@example.test']));
        $this->service()->sendTest($admin);

        self::assertSame(
            'sent',
            (string) $this->db->fetchValue("SELECT status FROM email_deliveries WHERE kind = 'test' AND email = ?", ['ops@example.test']),
        );
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'email_test_sent'"));
    }

    public function test_send_test_fails_closed_when_transport_unconfigured(): void
    {
        $admin = $this->userEntity($this->makeAdmin(['email' => 'noconf@example.test']));
        $this->expectException(ValidationException::class);
        $this->service(new SendmailMailer(''))->sendTest($admin);
    }

    public function test_send_test_surfaces_failure_when_configured_transport_throws(): void
    {
        $admin = $this->userEntity($this->makeAdmin(['email' => 'fail@example.test']));
        $mailer = new class implements Mailer {
            public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): string
            {
                throw new \RuntimeException('smtp 550 mailbox unavailable');
            }

            public function isConfigured(): bool
            {
                return true;
            }
        };

        $thrown = null;
        try {
            $this->service($mailer)->sendTest($admin);
        } catch (ValidationException $e) {
            $thrown = $e;
        }

        // A genuinely failed send must surface to the operator, not flash success.
        self::assertInstanceOf(ValidationException::class, $thrown);
        self::assertStringContainsString('smtp 550 mailbox unavailable', $thrown->first());
        // The delivery row is still recorded failed (markFailed runs before the re-throw).
        self::assertSame(
            'failed',
            (string) $this->db->fetchValue("SELECT status FROM email_deliveries WHERE kind = 'test' AND email = ?", ['fail@example.test']),
        );
    }

    public function test_manual_suppress_cascades_email_off_then_unsuppress_restores(): void
    {
        $u = $this->makeUser(['email' => 'sub@example.test']);
        $board = $this->makeBoard($this->makeCategory());
        (new SubscriptionRepository($this->db))->set((int) $u['id'], 'board', (int) $board['id'], true, true, 'instant');
        $admin = $this->userEntity($this->makeAdmin());

        $this->service()->manualSuppress($admin, 'sub@example.test');
        self::assertTrue((new EmailSuppressionRepository($this->db))->isSuppressed('sub@example.test'));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT email_enabled FROM subscriptions WHERE user_id = ?', [(int) $u['id']]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'email_suppressed'"));

        $this->service()->unsuppress($admin, 'sub@example.test');
        self::assertFalse((new EmailSuppressionRepository($this->db))->isSuppressed('sub@example.test'));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT email_enabled FROM subscriptions WHERE user_id = ?', [(int) $u['id']]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'email_unsuppressed'"));
    }

    public function test_requeue_failed_resets_to_queued_with_audit(): void
    {
        $deliv = new EmailDeliveryRepository($this->db);
        $id = $deliv->enqueue(null, 'x@example.test', 'instant', null, 'p1:u1');
        $deliv->markFailed($id, 'smtp 550');
        $admin = $this->userEntity($this->makeAdmin());

        $this->service()->requeueFailed($admin, $id);
        self::assertSame('queued', (string) $this->db->fetchValue('SELECT status FROM email_deliveries WHERE id = ?', [$id]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'email_requeued'"));
    }
}
