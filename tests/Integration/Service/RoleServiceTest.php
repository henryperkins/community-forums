<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\CapabilityRepository;
use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\RoleService;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08): custom role definitions are additive capability bundles,
 * system anchors are immutable, and every mutation reauths, versions, and audits.
 */
final class RoleServiceTest extends TestCase
{
    private function service(): RoleService
    {
        return new RoleService(
            $this->db,
            new RoleRepository($this->db),
            new RoleCapabilityRepository($this->db),
            new CapabilityRepository($this->db),
            new RoleAssignmentRepository($this->db),
            new RoleAssignmentHistoryRepository($this->db),
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
        );
    }

    private function admin(): User
    {
        return User::fromRow($this->makeAdmin());
    }

    public function test_create_role_maps_capabilities_and_audits(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $id = $svc->create($admin, 'password123', 'Board Helper', 'Lock + pin', ['core.thread.lock', 'core.thread.pin']);

        $role = (new RoleRepository($this->db))->find($id);
        self::assertSame('custom', $role['kind']);
        self::assertSame('custom.board_helper', $role['role_key']);
        self::assertSame(1, (int) $role['version']);

        $keys = (new RoleCapabilityRepository($this->db))->keysForRole($id);
        sort($keys);
        self::assertSame(['core.thread.lock', 'core.thread.pin'], $keys);

        $hist = (new RoleAssignmentHistoryRepository($this->db))->forRole($id);
        self::assertCount(1, $hist);
        self::assertSame('role_edit', $hist[0]['event']);
        self::assertNull($hist[0]['before_json']);
    }

    public function test_create_rejects_bad_input(): void
    {
        $svc = $this->service();
        $admin = $this->admin();

        try {
            $svc->create($admin, 'wrong-password', 'X', null, ['core.thread.lock']);
            self::fail('expected ValidationException for wrong password');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }

        try {
            $svc->create($admin, 'password123', '', null, ['core.thread.lock']);
            self::fail('expected ValidationException for empty name');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('name', $e->errors);
        }

        try {
            $svc->create($admin, 'password123', 'No Caps', null, []);
            self::fail('expected ValidationException for empty capability list');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('capabilities', $e->errors);
        }

        try {
            $svc->create($admin, 'password123', 'Unknown', null, ['core.not.a.key']);
            self::fail('expected ValidationException for unknown key');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('capabilities', $e->errors);
        }

        try {
            $svc->create($admin, 'password123', 'Sneaky Owner', null, ['core.owner.transfer']);
            self::fail('expected ValidationException for protected key');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('capabilities', $e->errors);
            self::assertStringContainsString('protected', strtolower((string) $e->errors['capabilities']));
        }

        $svc->create($admin, 'password123', 'Dup Role', null, ['core.thread.lock']);
        try {
            $svc->create($admin, 'password123', 'Dup Role', null, ['core.thread.pin']);
            self::fail('expected ValidationException for duplicate name');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('name', $e->errors);
        }
    }

    public function test_update_bumps_version_replaces_mapping_and_audits_before_after(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $id = $svc->create($admin, 'password123', 'Helper', null, ['core.thread.lock']);

        $svc->update($admin, 'password123', $id, 'Helper v2', 'now with pin', ['core.thread.pin']);

        $role = (new RoleRepository($this->db))->find($id);
        self::assertSame('Helper v2', $role['name']);
        self::assertSame(2, (int) $role['version']);
        self::assertSame(['core.thread.pin'], (new RoleCapabilityRepository($this->db))->keysForRole($id));

        $hist = (new RoleAssignmentHistoryRepository($this->db))->forRole($id);
        self::assertCount(2, $hist);
        $before = json_decode((string) $hist[0]['before_json'], true);
        $after = json_decode((string) $hist[0]['after_json'], true);
        self::assertSame(['core.thread.lock'], $before['capabilities']);
        self::assertSame(['core.thread.pin'], $after['capabilities']);
    }

    public function test_system_roles_are_protected_from_edit(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $modId = (int) (new RoleRepository($this->db))->findByKey('system.moderator')['id'];

        $this->expectException(ForbiddenException::class);
        $svc->update($admin, 'password123', $modId, 'Weakened Mod', null, ['core.board.read']);
    }

    public function test_clone_copies_capabilities_into_a_new_custom_role(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $modId = (int) (new RoleRepository($this->db))->findByKey('system.moderator')['id'];

        $cloneId = $svc->clone($admin, 'password123', $modId, 'Mod Clone');
        $role = (new RoleRepository($this->db))->find($cloneId);
        self::assertSame('custom', $role['kind']);

        // clone() filters the source to the enforceable set, so the clone holds
        // a strict subset of the source's cumulative keys (baseline keys like
        // core.board.read are dropped) — every cloned key still traces back to
        // the source, none invented.
        $sourceKeys = (new RoleCapabilityRepository($this->db))->keysForRole($modId);
        $cloneKeys = (new RoleCapabilityRepository($this->db))->keysForRole($cloneId);
        self::assertNotEmpty($cloneKeys);
        self::assertSame([], array_values(array_diff($cloneKeys, $sourceKeys)), 'no key invented by clone');
        self::assertLessThan(count($sourceKeys), count($cloneKeys), 'non-enforceable baseline keys were filtered out');
    }

    public function test_clone_of_system_role_copies_only_enforceable_keys(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $modId = (int) (new RoleRepository($this->db))->findByKey('system.moderator')['id'];

        // Cloning a system anchor now SUCCEEDS: clone() filters the source's
        // cumulative keys down to the enforceable set instead of 422-ing on the
        // baseline keys every system role carries (core.board.read, etc.). This
        // is the documented "clone one to adapt it" path.
        $cloneId = $svc->clone($admin, 'password123', $modId, 'Mod Deputy');
        $role = (new RoleRepository($this->db))->find($cloneId);
        self::assertSame('custom', $role['kind']);

        $cloneKeys = (new RoleCapabilityRepository($this->db))->keysForRole($cloneId);
        self::assertContains('core.thread.lock', $cloneKeys);   // enforced moderation key kept
        self::assertNotContains('core.board.read', $cloneKeys); // non-enforceable baseline key dropped
    }

    public function test_create_rejects_a_key_without_live_enforcement(): void
    {
        $admin = $this->admin();
        $this->expectException(ValidationException::class);
        $this->service()->create($admin, 'password123', 'Suspender', null, ['core.user.suspend']);
    }

    public function test_list_with_meta_reports_counts_and_impact(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $id = $svc->create($admin, 'password123', 'Impacted', null, ['core.thread.lock']);
        $user = $this->makeUser();
        (new RoleAssignmentRepository($this->db))->create(['subject_id' => (int) $user['id'], 'role_id' => $id]);

        $rows = $svc->listWithMeta();
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row['role']['role_key']] = $row;
        }

        self::assertSame(1, $byKey['custom.impacted']['capability_count']);
        self::assertSame(1, $byKey['custom.impacted']['impact']);
        self::assertSame(0, $byKey['system.guest']['impact'] ?? 0);
        self::assertGreaterThanOrEqual(49, $byKey['system.admin']['capability_count']);
    }
}
