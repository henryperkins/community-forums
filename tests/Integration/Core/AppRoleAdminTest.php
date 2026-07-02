<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08): no-JS role editor over HTTP.
 */
final class AppRoleAdminTest extends TestCase
{
    private function enable(): void
    {
        (new SettingRepository($this->db))->set('features', ['capabilities' => true]);
    }

    public function test_routes_are_dark_without_the_flag(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/roles'));
        $this->assertStatus(404, $this->post('/admin/roles', ['name' => 'X']));
        $this->assertStatus(404, $this->get('/admin/roles/1'));
    }

    public function test_guests_and_members_cannot_reach_the_editor(): void
    {
        $this->enable();
        $this->makeAdmin();
        $this->assertRedirectContains($this->get('/admin/roles'), '/login');
        $this->actingAs($this->makeUser());
        $this->assertStatus(403, $this->get('/admin/roles'));
    }

    public function test_admin_lists_system_anchors_with_noindex(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin());
        $resp = $this->get('/admin/roles');
        $this->assertStatus(200, $resp);
        $this->assertSeeText($resp, 'system.admin');
        $this->assertSeeText($resp, 'Protected anchor');
        self::assertSame('noindex', $resp->getHeader('x-robots-tag'));
    }

    public function test_create_role_via_form_post_then_audit(): void
    {
        $this->enable();
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $resp = $this->post('/admin/roles', [
            'name' => 'Board Helper',
            'description' => 'Lock and pin',
            'capabilities' => ['core.thread.lock', 'core.thread.pin'],
            'current_password' => 'password123',
        ]);
        $this->assertRedirectContains($resp, '/admin/roles');

        $role = (new RoleRepository($this->db))->findByKey('custom.board_helper');
        self::assertNotNull($role);
        $list = $this->get('/admin/roles');
        $this->assertSeeText($list, 'Board Helper');
        self::assertCount(1, (new RoleAssignmentHistoryRepository($this->db))->forRole((int) $role['id']));
    }

    public function test_create_validation_preserves_typed_input(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin());
        $resp = $this->post('/admin/roles', [
            'name' => 'Draft Role Name',
            'capabilities' => ['core.thread.lock'],
            'current_password' => 'wrong-password',
        ]);
        $this->assertStatus(422, $resp);
        $this->assertSeeText($resp, 'Draft Role Name');
        $this->assertSeeText($resp, 'current password is incorrect');
    }

    public function test_protected_capabilities_are_not_offered_and_are_rejected(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin());
        $page = $this->get('/admin/roles');
        $this->assertDontSeeText($page, 'core.owner.transfer');

        $resp = $this->post('/admin/roles', [
            'name' => 'Sneaky',
            'capabilities' => ['core.owner.transfer'],
            'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $resp);
    }

    public function test_update_bumps_version_and_system_roles_are_immutable(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin());
        $this->post('/admin/roles', [
            'name' => 'Helper',
            'capabilities' => ['core.thread.lock'],
            'current_password' => 'password123',
        ]);
        $roles = new RoleRepository($this->db);
        $id = (int) $roles->findByKey('custom.helper')['id'];

        $resp = $this->post('/admin/roles/' . $id, [
            'name' => 'Helper',
            'capabilities' => ['core.thread.pin'],
            'current_password' => 'password123',
        ]);
        $this->assertRedirectContains($resp, '/admin/roles');
        self::assertSame(2, (int) $roles->find($id)['version']);
        self::assertSame(['core.thread.pin'], (new RoleCapabilityRepository($this->db))->keysForRole($id));

        $modId = (int) $roles->findByKey('system.moderator')['id'];
        $this->assertStatus(403, $this->post('/admin/roles/' . $modId, [
            'name' => 'Weakened',
            'capabilities' => ['core.board.read'],
            'current_password' => 'password123',
        ]));
    }

    public function test_clone_creates_an_editable_copy(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin());
        $roles = new RoleRepository($this->db);
        $modId = (int) $roles->findByKey('system.moderator')['id'];

        $resp = $this->post('/admin/roles/' . $modId . '/clone', [
            'name' => 'Mod Copy',
            'current_password' => 'password123',
        ]);
        $this->assertRedirectContains($resp, '/admin/roles');
        $clone = $roles->findByKey('custom.mod_copy');
        self::assertNotNull($clone);
        self::assertSame('custom', $clone['kind']);
    }
}
