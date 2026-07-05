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

    public function test_revoke_stamps_and_bumps_version(): void
    {
        $user = $this->makeUser();
        $admin = $this->makeAdmin();
        $roles = new RoleRepository($this->db);
        $roleId = (int) $roles->findByKey('system.moderator')['id'];
        $repo = new RoleAssignmentRepository($this->db);
        $id = $repo->create(['subject_id' => (int) $user['id'], 'role_id' => $roleId]);

        $repo->revoke($id, (int) $admin['id']);

        $row = $repo->findForUpdate($id);
        self::assertNotNull($row);
        self::assertNotNull($row['revoked_at']);
        self::assertSame((int) $admin['id'], (int) $row['revoked_by']);
        self::assertSame(2, (int) $row['assignment_version']);
    }

    public function test_update_ends_at_bumps_version_and_list_for_role_includes_all_states(): void
    {
        $user = $this->makeUser();
        $admin = $this->makeAdmin();
        $roles = new RoleRepository($this->db);
        $roleId = (int) $roles->findByKey('system.moderator')['id'];
        $repo = new RoleAssignmentRepository($this->db);

        // One renewed (still-active) assignment...
        $renewedId = $repo->create(['subject_id' => (int) $user['id'], 'role_id' => $roleId]);
        $repo->updateEndsAt($renewedId, gmdate('Y-m-d H:i:s', time() + 3600));
        // ...and a second, revoked assignment for the SAME role.
        $revokedId = $repo->create(['subject_id' => (int) $user['id'], 'role_id' => $roleId]);
        $repo->revoke($revokedId, (int) $admin['id']);

        $rows = $repo->listForRole($roleId);

        // listForRole must surface EVERY state (no revoked_at IS NULL filter), newest id first.
        self::assertCount(2, $rows);
        self::assertSame($revokedId, (int) $rows[0]['id'], 'newest (revoked) row first');

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }
        // Renewed row: updateEndsAt bumped the version, username joined, not revoked.
        self::assertSame($user['username'], $byId[$renewedId]['username']);
        self::assertSame(2, (int) $byId[$renewedId]['assignment_version']);
        self::assertNull($byId[$renewedId]['revoked_at']);
        // Revoked row is present with a non-null revoked_at — proves revoked rows are included.
        self::assertNotNull($byId[$revokedId]['revoked_at'], 'revoked row must appear in listForRole');
    }

    public function test_find_returns_the_row_or_null(): void
    {
        $user = $this->makeUser();
        $roles = new RoleRepository($this->db);
        $roleId = (int) $roles->findByKey('system.moderator')['id'];
        $repo = new RoleAssignmentRepository($this->db);
        $id = $repo->create(['subject_id' => (int) $user['id'], 'role_id' => $roleId]);

        $row = $repo->find($id);
        self::assertNotNull($row);
        self::assertSame($id, (int) $row['id']);
        self::assertSame((int) $user['id'], (int) $row['subject_id']);

        self::assertNull($repo->find(999999));
    }

    public function test_double_revoke_is_idempotent(): void
    {
        $user = $this->makeUser();
        $admin = $this->makeAdmin();
        $other = $this->makeAdmin();
        $roles = new RoleRepository($this->db);
        $roleId = (int) $roles->findByKey('system.moderator')['id'];
        $repo = new RoleAssignmentRepository($this->db);
        $id = $repo->create(['subject_id' => (int) $user['id'], 'role_id' => $roleId]);

        $repo->revoke($id, (int) $admin['id']);
        $afterFirst = $repo->findForUpdate($id);
        self::assertNotNull($afterFirst);
        self::assertSame(2, (int) $afterFirst['assignment_version']);
        self::assertSame((int) $admin['id'], (int) $afterFirst['revoked_by']);
        $firstRevokedAt = $afterFirst['revoked_at'];

        // Second revoke by a DIFFERENT actor must be a no-op (WHERE revoked_at IS NULL).
        $repo->revoke($id, (int) $other['id']);

        $afterSecond = $repo->findForUpdate($id);
        self::assertNotNull($afterSecond);
        self::assertSame(2, (int) $afterSecond['assignment_version'], 'version not re-bumped');
        self::assertSame((int) $admin['id'], (int) $afterSecond['revoked_by'], 'original revoker preserved');
        self::assertSame($firstRevokedAt, $afterSecond['revoked_at'], 'revoked_at unchanged');
    }
}
