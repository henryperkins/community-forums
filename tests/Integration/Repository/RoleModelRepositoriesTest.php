<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\CapabilityRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Security\CapabilityCatalog;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08): thin wrappers over the 0050 role-model tables,
 * exercised against the real 0066 seed.
 */
final class RoleModelRepositoriesTest extends TestCase
{
    public function test_capability_repository_reads_the_seeded_catalogue(): void
    {
        $caps = new CapabilityRepository($this->db);
        $all = $caps->all();
        self::assertCount(count(CapabilityCatalog::keys()), $all);

        $ids = $caps->idsByKeys(['core.board.read', 'core.thread.lock']);
        self::assertCount(2, $ids);
        self::assertArrayHasKey('core.thread.lock', $ids);
        self::assertSame([], $caps->idsByKeys([]));
    }

    public function test_role_keys_holding_reflects_the_cumulative_seed(): void
    {
        $rc = new RoleCapabilityRepository($this->db);

        $read = $rc->roleKeysHolding('core.board.read');
        sort($read);
        self::assertSame(['system.admin', 'system.guest', 'system.moderator', 'system.user'], $read);

        $lock = $rc->roleKeysHolding('core.thread.lock');
        sort($lock);
        self::assertSame(['system.admin', 'system.moderator'], $lock);

        self::assertSame(['system.admin'], $rc->roleKeysHolding('core.user.ban'));
        self::assertSame([], $rc->roleKeysHolding('core.owner.transfer'));
        self::assertSame([], $rc->roleKeysHolding('core.not.a.key'));
    }

    public function test_custom_role_create_map_and_version_bump(): void
    {
        $roles = new RoleRepository($this->db);
        $rc = new RoleCapabilityRepository($this->db);
        $caps = new CapabilityRepository($this->db);
        $admin = $this->makeAdmin();

        $id = $roles->create([
            'role_key' => 'custom.board_helper',
            'name' => 'Board Helper',
            'description' => 'Lock + pin only',
            'created_by' => (int) $admin['id'],
        ]);
        $row = $roles->find($id);
        self::assertNotNull($row);
        self::assertSame('custom', $row['kind']);
        self::assertSame(0, (int) $row['is_protected']);
        self::assertSame(1, (int) $row['version']);
        self::assertSame('custom.board_helper', $roles->findByKey('custom.board_helper')['role_key']);

        $ids = $caps->idsByKeys(['core.thread.lock', 'core.thread.pin']);
        $rc->replaceForRole($id, array_values($ids));
        $keys = $rc->keysForRole($id);
        sort($keys);
        self::assertSame(['core.thread.lock', 'core.thread.pin'], $keys);

        $rc->replaceForRole($id, [$ids['core.thread.lock']]);
        self::assertSame(['core.thread.lock'], $rc->keysForRole($id));

        self::assertSame(1, $roles->updateDefinition($id, 'Board Helper v2', null));
        $roles->bumpVersion($id);
        $row = $roles->find($id);
        self::assertSame('Board Helper v2', $row['name']);
        self::assertNull($row['description']);
        self::assertSame(2, (int) $row['version']);

        self::assertContains('custom.board_helper', array_column($roles->all(), 'role_key'));
        self::assertContains('system.admin', array_column($roles->all(), 'role_key'));
    }
}
