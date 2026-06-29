<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Repository\BoardModeratorRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;
use App\Service\UserModerationService;
use Tests\Support\TestCase;

final class UserModerationSetTitleTest extends TestCase
{
    private function service(): UserModerationService
    {
        return new UserModerationService(
            $this->db,
            new UserRepository($this->db),
            new ModerationLogRepository($this->db),
            new WriteGate(),
            new BoardModeratorRepository($this->db),
        );
    }

    public function test_set_title_persists_trimmed_override_and_audits_set_title(): void
    {
        $admin = $this->makeAdmin(['username' => 'titler']);
        $user = $this->makeUser(['username' => 'titlee']);
        $uid = (int) $user['id'];

        $this->service()->setTitle($this->userEntity($admin), $uid, '  Champion  ');

        self::assertSame('Champion', (string) $this->db->fetchValue('SELECT title FROM users WHERE id = ?', [$uid]));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'set_title' AND target_type = 'user' AND target_id = ?",
            [$uid],
        ));
    }

    public function test_empty_title_clears_to_null_and_audits_clear_title(): void
    {
        $admin = $this->makeAdmin(['username' => 'titler2']);
        $user = $this->makeUser(['username' => 'titlee2']);
        $uid = (int) $user['id'];
        $this->users()->setTitle($uid, 'Champion');

        $this->service()->setTitle($this->userEntity($admin), $uid, '');

        self::assertNull($this->db->fetchValue('SELECT title FROM users WHERE id = ?', [$uid]));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'clear_title' AND target_type = 'user' AND target_id = ?",
            [$uid],
        ));
    }

    public function test_title_over_64_chars_throws_validation(): void
    {
        $admin = $this->makeAdmin(['username' => 'titler3']);
        $user = $this->makeUser(['username' => 'titlee3']);
        $this->expectException(ValidationException::class);
        $this->service()->setTitle($this->userEntity($admin), (int) $user['id'], str_repeat('x', 65));
    }

    public function test_suspended_admin_cannot_set_title(): void
    {
        $admin = $this->makeUser(['username' => 'titler4', 'role' => 'admin', 'status' => 'suspended']);
        $user = $this->makeUser(['username' => 'titlee4']);
        $this->expectException(ForbiddenException::class);
        $this->service()->setTitle($this->userEntity($admin), (int) $user['id'], 'Nope');
    }
}
