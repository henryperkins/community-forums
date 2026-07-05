<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppChangeRoleTest extends TestCase
{
    public function test_admin_promotes_member_to_moderator_with_reauth_and_audit(): void
    {
        $admin = $this->makeAdmin();
        $member = $this->makeUser();
        $this->actingAs($admin);

        $r = $this->post('/admin/users/' . $member['id'] . '/role', [
            'role' => 'moderator', 'current_password' => 'password123',
        ]);
        $this->assertRedirect($r);
        self::assertSame('moderator', $this->users()->find((int) $member['id'])['role']);
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'change_role' AND target_id = ?",
            [(int) $member['id']],
        ));
    }

    public function test_wrong_password_refuses_and_role_is_unchanged(): void
    {
        $admin = $this->makeAdmin();
        $member = $this->makeUser();
        $this->actingAs($admin);

        $r = $this->post('/admin/users/' . $member['id'] . '/role', [
            'role' => 'moderator', 'current_password' => 'nope',
        ]);
        self::assertContains($r->status(), [302, 303, 422]); // controller decides the shape; role must not change
        self::assertSame('user', $this->users()->find((int) $member['id'])['role']);
    }

    public function test_demoting_the_last_owner_is_blocked_tm_pe_07(): void
    {
        $admin = $this->makeAdmin();
        // Seed the owner set (0066 semantics): the sole active admin is the owner.
        (new \App\Repository\ProtectedOwnerRepository($this->db))->designate((int) $admin['id'], null);
        $this->actingAs($admin);

        $r = $this->post('/admin/users/' . $admin['id'] . '/role', [
            'role' => 'user', 'current_password' => 'password123',
        ]);
        self::assertContains($r->status(), [302, 303, 422]); // refused (flash or 422)
        self::assertSame('admin', $this->users()->find((int) $admin['id'])['role']);
    }

    public function test_demoting_a_non_last_owner_deactivates_their_owner_row_and_revokes_sessions(): void
    {
        $owner1 = $this->makeAdmin();
        $owner2 = $this->makeAdmin();
        $owners = new \App\Repository\ProtectedOwnerRepository($this->db);
        $owners->designate((int) $owner1['id'], null);
        $owners->designate((int) $owner2['id'], null);
        (new \App\Repository\SessionRepository($this->db))->create([
            'id' => hash('sha256', 'target-session-' . $owner2['id']),
            'user_id' => (int) $owner2['id'],
            'csrf_secret' => bin2hex(random_bytes(32)),
            'user_agent' => 'phpunit-target',
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
        ]);
        $this->actingAs($owner1);

        $this->assertRedirect($this->post('/admin/users/' . $owner2['id'] . '/role', [
            'role' => 'user', 'current_password' => 'password123',
        ]));
        self::assertSame('user', $this->users()->find((int) $owner2['id'])['role']);
        self::assertFalse($owners->isActiveOwner((int) $owner2['id']));
        self::assertSame(0, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM sessions WHERE user_id = ? AND revoked_at IS NULL',
            [(int) $owner2['id']],
        ));
    }

    public function test_promote_to_admin_designates_protected_owner(): void
    {
        $admin = $this->makeAdmin();
        (new \App\Repository\ProtectedOwnerRepository($this->db))->designate((int) $admin['id'], null);
        $member = $this->makeUser();
        $this->actingAs($admin);

        $this->assertRedirect($this->post('/admin/users/' . $member['id'] . '/role', [
            'role' => 'admin', 'current_password' => 'password123',
        ]));
        self::assertTrue((new \App\Repository\ProtectedOwnerRepository($this->db))->isActiveOwner((int) $member['id']));
    }
}
