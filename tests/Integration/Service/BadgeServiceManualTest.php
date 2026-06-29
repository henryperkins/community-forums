<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Repository\BadgeRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\UserRepository;
use App\Service\BadgeService;
use Tests\Support\TestCase;

final class BadgeServiceManualTest extends TestCase
{
    private function service(): BadgeService
    {
        return new BadgeService(
            $this->db,
            new BadgeRepository($this->db),
            new UserRepository($this->db),
            null, // notifications
            10,
            10,
            100,
            1000,
            new ModerationLogRepository($this->db),
            new \App\Security\WriteGate(),
        );
    }

    public function test_grant_manual_awards_and_audits_with_reason(): void
    {
        $admin = $this->makeAdmin(['username' => 'grant_admin']);
        $user = $this->makeUser(['username' => 'grantee']);
        $uid = (int) $user['id'];

        $this->service()->grantManual($this->userEntity($admin), $uid, 'staff', 'core team');

        self::assertTrue((new BadgeRepository($this->db))->hasBadgeSlug($uid, 'staff'));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log
             WHERE action = 'badge.grant' AND target_type = 'user' AND target_id = ? AND reason = ?",
            [$uid, 'core team'],
        ));
    }

    public function test_grant_manual_rejects_auto_slug(): void
    {
        $admin = $this->makeAdmin(['username' => 'grant_admin2']);
        $user = $this->makeUser(['username' => 'grantee2']);
        $this->expectException(ValidationException::class);
        $this->service()->grantManual($this->userEntity($admin), (int) $user['id'], 'welcome');
    }

    public function test_revoke_manual_removes_and_audits(): void
    {
        $admin = $this->makeAdmin(['username' => 'revoke_admin']);
        $user = $this->makeUser(['username' => 'revokee']);
        $uid = (int) $user['id'];
        $badges = new BadgeRepository($this->db);
        $badges->awardBySlug($uid, 'staff');

        $removed = $this->service()->revokeManual($this->userEntity($admin), $uid, 'staff');

        self::assertTrue($removed);
        self::assertFalse($badges->hasBadgeSlug($uid, 'staff'));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'badge.revoke' AND target_type = 'user' AND target_id = ?",
            [$uid],
        ));
    }

    public function test_revoke_manual_rejects_auto_slug(): void
    {
        $admin = $this->makeAdmin(['username' => 'revoke_admin2']);
        $user = $this->makeUser(['username' => 'revokee2']);
        $this->expectException(ValidationException::class);
        $this->service()->revokeManual($this->userEntity($admin), (int) $user['id'], 'welcome');
    }

    /** State beats role: a suspended admin can read but not write (CLAUDE.md Security §2). */
    public function test_suspended_admin_cannot_grant_badge(): void
    {
        $admin = $this->makeUser([
            'username' => 'suspended_grant_admin',
            'role' => 'admin',
            'status' => 'suspended',
        ]);
        $user = $this->makeUser(['username' => 'grantee_blocked']);

        $this->expectException(ForbiddenException::class);
        $this->service()->grantManual($this->userEntity($admin), (int) $user['id'], 'staff');
    }
}
