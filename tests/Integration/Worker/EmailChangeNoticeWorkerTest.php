<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Core\Config;
use App\Mail\ArrayMailer;
use App\Mail\Mailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailDomainStatusRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
use App\Repository\UserPreferenceRepository;
use App\Service\EmailDomainVerifier;
use App\Service\EmailPreferenceService;
use App\Worker\NotificationEmailWorker;
use Tests\Support\TestCase;

final class EmailChangeNoticeWorkerTest extends TestCase
{
    private function worker(Mailer $mailer, ?EmailDomainVerifier $domain = null): NotificationEmailWorker
    {
        return new NotificationEmailWorker(new EmailDeliveryRepository($this->db), new EmailSuppressionRepository($this->db),
            new PostRepository($this->db), $this->users(), $mailer, $this->config, new SettingRepository($this->db), $domain);
    }

    private function notice(array $user): int
    {
        return (new EmailDeliveryRepository($this->db))->enqueue((int) $user['id'], 'old@example.test', 'system', null,
            'email-change:' . $user['id'], ['type' => 'email_changed']);
    }

    public function testSecurityNoticeBypassesPauseAndGlobalEmailSwitchAndHasNoUnsubscribe(): void
    {
        $user = $this->makeUser(['email' => 'new@example.test']);
        $id = $this->notice($user);
        (new EmailPreferenceService(new UserPreferenceRepository($this->db)))->setPauseAllEmail((int) $user['id'], true);
        (new SettingRepository($this->db))->set('features', ['email' => false]);
        $mailer = new ArrayMailer();
        self::assertSame(1, $this->worker($mailer)->run()['sent']);
        self::assertSame('sent', (new EmailDeliveryRepository($this->db))->find($id)['status']);
        self::assertSame('old@example.test', $mailer->sent[0]['to']);
        self::assertStringContainsString('email address', strtolower($mailer->sent[0]['text']));
        self::assertStringContainsString('password', strtolower($mailer->sent[0]['text']));
        foreach (['text', 'html'] as $part) {
            self::assertStringNotContainsString('unsubscribe', strtolower((string) $mailer->sent[0][$part]));
            self::assertStringNotContainsString('new@example.test', (string) $mailer->sent[0][$part]);
        }
    }

    public function testSecurityNoticeSurvivesTransportRetryAndRechecksSuppression(): void
    {
        $user = $this->makeUser(['email' => 'new@example.test']);
        $id = $this->notice($user);
        $repo = new EmailDeliveryRepository($this->db);
        $mailer = new ArrayMailer();
        $mailer->failNext = true;
        $worker = $this->worker($mailer);
        self::assertSame(1, $worker->run()['retrying']);
        self::assertSame(1, (int) $repo->find($id)['attempt_count']);
        self::assertSame(0, $worker->run()['sent']);
        $this->db->run('UPDATE email_deliveries SET next_attempt_at = NULL WHERE id = ?', [$id]);
        self::assertSame(1, $worker->run()['sent']);
        self::assertSame(2, (int) $repo->find($id)['attempt_count']);
        self::assertSame(0, $worker->run()['sent']);
    }

    public function testSuppressedOldAddressNeverReceivesSecurityNotice(): void
    {
        $user = $this->makeUser(['email' => 'new@example.test']);
        $id = $this->notice($user);
        $mailer = new ArrayMailer();
        $mailer->failNext = true;
        $worker = $this->worker($mailer);
        self::assertSame(1, $worker->run()['retrying']);
        $suppressions = new EmailSuppressionRepository($this->db);
        $suppressions->suppress('old@example.test', 'unsubscribe');
        $this->db->run('UPDATE email_deliveries SET next_attempt_at = NULL WHERE id = ?', [$id]);
        self::assertSame(1, $worker->run()['suppressed']);
        self::assertSame('address_suppressed', (new EmailDeliveryRepository($this->db))->find($id)['error']);
        self::assertTrue($suppressions->isSuppressed('old@example.test'));
        self::assertSame(0, $mailer->count());
    }

    public function testSecurityNoticesRespectRecipientDeletion(): void
    {
        $user = $this->makeUser(['email' => 'new@example.test']);
        $id = $this->notice($user);
        $this->users()->setStatus((int) $user['id'], 'deleted');
        $mailer = new ArrayMailer();
        self::assertSame(1, $this->worker($mailer)->run()['suppressed']);
        self::assertSame('recipient_deleted', (new EmailDeliveryRepository($this->db))->find($id)['error']);
        self::assertSame(0, $mailer->count());
    }

    public function testSecurityNoticesRespectTransportAndDomainBlockingWithoutLosingQueue(): void
    {
        $user = $this->makeUser(['email' => 'new@example.test']);
        $id = $this->notice($user);
        $mailer = new class implements Mailer {
            public function isConfigured(): bool { return false; }
            public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): string { throw new \LogicException('must not send'); }
        };
        self::assertSame('sender_unconfigured', $this->worker($mailer)->run()['blocked_reason']);
        $settings = new SettingRepository($this->db);
        $settings->set('email_require_verified_domain', true);
        $config = new Config(array_replace_recursive($this->config->all(), ['mail' => ['from' => 'sender@example.test']]));
        $domain = new EmailDomainVerifier($config, $settings, new EmailDomainStatusRepository($this->db));
        self::assertSame('domain_unverified', $this->worker(new ArrayMailer(), $domain)->run()['blocked_reason']);
        self::assertSame('queued', (new EmailDeliveryRepository($this->db))->find($id)['status']);
        self::assertSame(0, (int) (new EmailDeliveryRepository($this->db))->find($id)['attempt_count']);
    }

    public function testOrdinaryQueuedMailToPreviousContactIsSuppressedButTestsAndCurrentAnnouncementsKeepBehavior(): void
    {
        $user = $this->makeUser(['email' => 'new@example.test']);
        $repo = new EmailDeliveryRepository($this->db);
        $old = $repo->enqueue((int) $user['id'], 'old@example.test', 'system', null, null, ['type' => 'announcement', 'message' => 'Private content']);
        $current = $repo->enqueue((int) $user['id'], 'new@example.test', 'system', null, null, ['type' => 'announcement', 'message' => 'Current announcement']);
        $test = $repo->enqueue((int) $user['id'], 'operator@example.test', 'test', null);
        $mailer = new ArrayMailer();
        $stats = $this->worker($mailer)->run();
        self::assertSame(1, $stats['suppressed']);
        self::assertSame('recipient_email_changed', $repo->find($old)['error']);
        self::assertSame(2, $stats['sent']);
        self::assertSame('sent', $repo->find($current)['status']);
        self::assertSame('sent', $repo->find($test)['status']);
        self::assertStringContainsString('/unsubscribe?', $mailer->to('new@example.test')[0]['text']);
        self::assertCount(0, $mailer->to('old@example.test'));
    }

    public function testOrdinaryRetryRechecksContactAfterAddressSwitch(): void
    {
        $user = $this->makeUser(['email' => 'old@example.test']);
        $repo = new EmailDeliveryRepository($this->db);
        $id = $repo->enqueue((int) $user['id'], 'old@example.test', 'system', null, null, ['type' => 'announcement', 'message' => 'Private content']);
        $mailer = new ArrayMailer();
        $mailer->failNext = true;
        $worker = $this->worker($mailer);
        self::assertSame(1, $worker->run()['retrying']);
        $this->db->run('UPDATE users SET email = ? WHERE id = ?', ['new@example.test', $user['id']]);
        $this->db->run('UPDATE email_deliveries SET next_attempt_at = NULL WHERE id = ?', [$id]);
        self::assertSame(1, $worker->run()['suppressed']);
        self::assertSame('recipient_email_changed', $repo->find($id)['error']);
        self::assertSame(0, $mailer->count());
    }
}
