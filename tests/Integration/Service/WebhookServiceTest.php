<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Core\WebhooksDisabledException;
use App\Repository\ModerationLogRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Security\EgressGuard;
use App\Security\PasswordHasher;
use App\Security\SecretBox;
use App\Security\WriteGate;
use App\Service\SecretVault;
use App\Service\WebhookService;
use Tests\Support\TestCase;

final class WebhookServiceTest extends TestCase
{
    /** @param array<string,bool> $flags */
    private function service(array $flags = ['webhooks' => true, 'service_secrets' => true]): WebhookService
    {
        (new SettingRepository($this->db))->set('features', $flags);
        return new WebhookService(
            $this->db,
            new WebhookRepository($this->db),
            new WebhookDeliveryRepository($this->db),
            $this->vault(),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
            new PasswordHasher(),
            new WriteGate(),
            new EgressGuard(false, []),
        );
    }

    private function admin(): \App\Domain\User
    {
        return $this->userEntity($this->makeAdmin(['password' => 'password123']));
    }

    public function test_register_returns_secret_once_and_stores_only_a_vault_ref(): void
    {
        $res = $this->service()->register($this->admin(), 'password123', 'ci', 'https://x.test/h', ['ping']);
        self::assertNotSame('', $res['secret']);
        $ref = (string) $this->db->fetchValue('SELECT secret_ref FROM webhooks WHERE id = ?', [$res['id']]);
        self::assertStringStartsWith('svcsec_', $ref);
        self::assertStringNotContainsString($res['secret'], $ref);
        self::assertContains($res['secret'], $this->vault()->usableSecrets($ref));
    }

    public function test_register_requires_service_secrets_flag(): void
    {
        $this->expectException(ValidationException::class);
        $this->service(['webhooks' => true, 'service_secrets' => false])
            ->register($this->admin(), 'password123', 'ci', 'https://x.test/h', ['ping']);
    }

    public function test_register_blocked_when_webhooks_dark(): void
    {
        $this->expectException(WebhooksDisabledException::class);
        $this->service(['webhooks' => false, 'service_secrets' => true])
            ->register($this->admin(), 'password123', 'ci', 'https://x.test/h', ['ping']);
    }

    public function test_register_rejects_wrong_password_and_bad_input(): void
    {
        $svc = $this->service();
        try {
            $svc->register($this->admin(), 'WRONG', 'ci', 'https://x.test/h', ['ping']);
            self::fail('expected ValidationException');
        } catch (ValidationException) {
            self::assertTrue(true);
        }
        $this->expectException(ValidationException::class);
        $svc->register($this->admin(), 'password123', 'ci', 'not-a-url', ['ping']);
    }

    public function test_suspended_admin_cannot_register(): void
    {
        $admin = $this->userEntity($this->makeUser(['role' => 'admin', 'status' => 'suspended', 'password' => 'password123']));
        $this->expectException(ForbiddenException::class);
        $this->service()->register($admin, 'password123', 'ci', 'https://x.test/h', ['ping']);
    }

    public function test_dispatch_fans_out_to_subscribed_active_endpoints_and_dedupes(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $a = $svc->register($admin, 'password123', 'subA', 'https://a.test/h', ['topic.created']);
        $svc->register($admin, 'password123', 'subB', 'https://b.test/h', ['reply.created']);

        self::assertSame(1, $svc->dispatch('topic.created', ['x' => 1], 'occ1'));
        self::assertSame(0, $svc->dispatch('topic.created', ['x' => 1], 'occ1'));
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM webhook_deliveries WHERE webhook_id = ?',
            [$a['id']],
        ));
    }

    public function test_dispatch_is_noop_when_dark(): void
    {
        $this->service()->register($this->admin(), 'password123', 'ci', 'https://x.test/h', ['ping']);
        (new SettingRepository($this->db))->set('features', ['webhooks' => false]);
        $dark = new WebhookService(
            $this->db,
            new WebhookRepository($this->db),
            new WebhookDeliveryRepository($this->db),
            $this->vault(),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
            new PasswordHasher(),
            new WriteGate(),
            new EgressGuard(false, []),
        );
        self::assertSame(0, $dark->dispatch('ping', ['x' => 1], 'occ'));
    }

    public function test_audit_rows_carry_no_secret(): void
    {
        $res = $this->service()->register($this->admin(), 'password123', 'ci', 'https://x.test/h', ['ping']);
        $row = $this->db->fetch(
            "SELECT after_json FROM moderation_log WHERE action = 'webhook_registered' AND target_id = ?",
            [$res['id']],
        );
        self::assertNotNull($row);
        self::assertStringNotContainsString($res['secret'], (string) $row['after_json']);
        self::assertStringNotContainsString('password123', (string) $row['after_json']);
    }

    public function test_register_rejects_a_literal_private_ip_at_registration(): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->register($this->admin(), 'password123', 'ci', 'https://10.0.0.1/hook', ['ping']);
    }

    private function vault(): SecretVault
    {
        return new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            new SecretBox('0000000000000000000000000000000000000000000000000000000000000000'),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
        );
    }
}
