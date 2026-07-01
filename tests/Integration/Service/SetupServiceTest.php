<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\SettingRepository;
use App\Security\Session;
use App\Service\SetupService;
use Tests\Support\TestCase;

final class SetupServiceTest extends TestCase
{
    private function service(): SetupService
    {
        return new SetupService(
            $this->db,
            new \App\Service\AuthService($this->users(), new \App\Security\PasswordHasher(), $this->config),
            $this->users(),
            new SettingRepository($this->db),
            new \App\Repository\CategoryRepository($this->db),
            new \App\Repository\BoardRepository($this->db),
            new \App\Repository\ModerationLogRepository($this->db),
            new Session(new \App\Repository\SessionRepository($this->db), $this->users(), $this->config->get('session')),
            new ProtectedOwnerRepository($this->db),
        );
    }

    public function test_fresh_install_is_not_initialised(): void
    {
        self::assertFalse($this->service()->isInitialized());
    }

    public function test_run_creates_admin_settings_and_starter_content(): void
    {
        $admin = $this->service()->run([
            'site_name' => 'My Community',
            'username' => 'founder',
            'email' => 'founder@example.test',
            'password' => 'sup3rsecret',
            'password_confirm' => 'sup3rsecret',
        ]);

        self::assertSame('admin', $admin->role());
        // The operator account is auto-verified (owns the install, no email round-trip).
        self::assertNotNull($this->db->fetchValue('SELECT email_verified_at FROM users WHERE id = ?', [$admin->id()]));
        self::assertTrue((new ProtectedOwnerRepository($this->db))->isActiveOwner($admin->id()));
        self::assertTrue($this->service()->isInitialized());
        self::assertSame('My Community', (new SettingRepository($this->db))->getString('site_name'));

        // Starter categories and boards exist.
        self::assertGreaterThan(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM categories'));
        self::assertGreaterThan(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM boards'));

        // The install is audited.
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'install'"));
    }

    public function test_run_rejects_invalid_input(): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->run([
            'site_name' => '',
            'username' => 'x',
            'email' => 'not-an-email',
            'password' => 'short',
        ]);
    }

    public function test_run_cannot_be_repeated(): void
    {
        $this->makeAdmin();
        $this->expectException(ForbiddenException::class);
        $this->service()->run([
            'site_name' => 'Second',
            'username' => 'rogue',
            'email' => 'rogue@example.test',
            'password' => 'sup3rsecret',
            'password_confirm' => 'sup3rsecret',
        ]);
    }
}
