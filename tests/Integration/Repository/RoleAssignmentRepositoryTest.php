<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleRepository;
use Tests\Support\TestCase;

final class RoleAssignmentRepositoryTest extends TestCase
{
    public function test_rows_for_user_joins_role_and_excludes_revoked(): void
    {
        $repo = new RoleAssignmentRepository($this->db);
        $roles = new RoleRepository($this->db);
        $user = $this->makeUser();
        $modRoleId = (int) $roles->findByKey('system.moderator')['id'];

        $a1 = $repo->create(['subject_id' => (int) $user['id'], 'role_id' => $modRoleId, 'scope_type' => 'board', 'scope_id' => 42]);
        $repo->create(['subject_id' => (int) $user['id'], 'role_id' => $modRoleId, 'ends_at' => '2026-01-01 00:00:00']);

        $rows = $repo->rowsForUser((int) $user['id']);
        self::assertCount(2, $rows, 'expired rows are returned; rules filter windows, revoked rows are not');
        self::assertSame('system.moderator', $rows[0]['role_key']);
        self::assertSame(20, (int) $rows[0]['role_rank']);

        $this->db->run('UPDATE role_assignments SET revoked_at = UTC_TIMESTAMP() WHERE id = ?', [$a1]);
        self::assertCount(1, $repo->rowsForUser((int) $user['id']));
    }

    public function test_count_active_for_roles_applies_window_and_revocation(): void
    {
        $repo = new RoleAssignmentRepository($this->db);
        $roles = new RoleRepository($this->db);
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();
        $modRoleId = (int) $roles->findByKey('system.moderator')['id'];
        $adminRoleId = (int) $roles->findByKey('system.admin')['id'];

        $repo->create(['subject_id' => (int) $u1['id'], 'role_id' => $modRoleId]);
        $repo->create(['subject_id' => (int) $u2['id'], 'role_id' => $modRoleId, 'ends_at' => '2026-01-01 00:00:00']);
        $repo->create(['subject_id' => (int) $u2['id'], 'role_id' => $modRoleId, 'starts_at' => '2030-01-01 00:00:00']);
        $revoked = $repo->create(['subject_id' => (int) $u1['id'], 'role_id' => $adminRoleId]);
        $this->db->run('UPDATE role_assignments SET revoked_at = UTC_TIMESTAMP() WHERE id = ?', [$revoked]);

        $counts = $repo->countActiveForRoles([$modRoleId, $adminRoleId]);
        self::assertSame(1, $counts[$modRoleId] ?? 0);
        self::assertArrayNotHasKey($adminRoleId, $counts);
        self::assertSame([], $repo->countActiveForRoles([]));
    }

    public function test_history_log_round_trips_before_after_json(): void
    {
        $hist = new RoleAssignmentHistoryRepository($this->db);
        $roles = new RoleRepository($this->db);
        $admin = $this->makeAdmin();
        $roleId = (int) $roles->findByKey('system.user')['id'];

        $hist->log([
            'event' => 'role_edit',
            'actor_id' => (int) $admin['id'],
            'role_id' => $roleId,
            'before' => null,
            'after' => ['name' => 'Board Helper', 'capabilities' => ['core.thread.lock']],
            'reason' => 'created',
        ]);
        $rows = $hist->forRole($roleId);
        self::assertCount(1, $rows);
        self::assertSame('role_edit', $rows[0]['event']);
        self::assertNull($rows[0]['before_json']);
        $after = json_decode((string) $rows[0]['after_json'], true);
        self::assertSame(['core.thread.lock'], $after['capabilities']);
    }
}
