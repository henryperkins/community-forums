<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Repository\ModerationLogRepository;
use App\Repository\NotificationRepository;
use App\Repository\SettingRepository;
use App\Security\WriteGate;
use App\Service\AnnouncementService;
use Tests\Support\TestCase;

final class AnnouncementServiceTest extends TestCase
{
    private function service(): AnnouncementService
    {
        return new AnnouncementService(
            $this->db,
            new SettingRepository($this->db),
            new ModerationLogRepository($this->db),
            new NotificationRepository($this->db),
            new WriteGate(),
        );
    }

    public function test_set_banner_persists_active_announcement_and_audits(): void
    {
        $admin = $this->makeAdmin(['username' => 'annsvcadmin']);
        $this->service()->setBanner($this->userEntity($admin), 'Welcome to the new release', true, false);

        $stored = (new SettingRepository($this->db))->get('site_announcement', []);
        self::assertIsArray($stored);
        self::assertTrue((bool) ($stored['active'] ?? false));
        self::assertSame('Welcome to the new release', $stored['message'] ?? null);
        self::assertTrue((bool) ($stored['dismissible'] ?? false));
        self::assertSame(1, (int) ($stored['version'] ?? 0));

        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'set_announcement' AND target_type = 'setting'",
        ));
    }

    public function test_version_increments_on_each_publish(): void
    {
        $admin = $this->userEntity($this->makeAdmin(['username' => 'annveradmin']));
        $this->service()->setBanner($admin, 'First', false, false);
        $this->service()->setBanner($admin, 'Second', false, false);

        $stored = (new SettingRepository($this->db))->get('site_announcement', []);
        self::assertSame(2, (int) ($stored['version'] ?? 0));
    }

    public function test_broadcast_creates_announcement_rows_excluding_actor(): void
    {
        $admin = $this->makeAdmin(['username' => 'annbcadmin']);
        $reader = $this->makeUser(['username' => 'annreader']);

        $this->service()->setBanner($this->userEntity($admin), 'All hands at noon', false, true);

        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE type = 'announcement' AND user_id = ?",
            [(int) $reader['id']],
        ));
        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE type = 'announcement' AND user_id = ?",
            [(int) $admin['id']],
        ));
    }

    public function test_no_broadcast_when_flag_off(): void
    {
        $admin = $this->makeAdmin(['username' => 'annnobcadmin']);
        $this->makeUser(['username' => 'annnobcreader']);

        $this->service()->setBanner($this->userEntity($admin), 'Banner only', false, false);

        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE type = 'announcement'",
        ));
    }

    public function test_empty_message_is_rejected(): void
    {
        $admin = $this->userEntity($this->makeAdmin(['username' => 'annemptyadmin']));
        $this->expectException(ValidationException::class);
        $this->service()->setBanner($admin, '   ', false, false);
    }

    public function test_clear_deactivates_banner_and_audits(): void
    {
        $admin = $this->userEntity($this->makeAdmin(['username' => 'annclearsvc']));
        $this->service()->setBanner($admin, 'Temporary', true, false);
        $this->service()->clearBanner($admin);

        $stored = (new SettingRepository($this->db))->get('site_announcement', []);
        self::assertFalse((bool) ($stored['active'] ?? true));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'clear_announcement' AND target_type = 'setting'",
        ));
    }
}
