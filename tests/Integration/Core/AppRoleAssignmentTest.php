<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppRoleAssignmentTest extends TestCase
{
    private function enableCapabilities(): void
    {
        (new SettingRepository($this->db))->set('features', ['capabilities' => true]);
    }

    /** @return array{admin:array<string,mixed>,roleId:int} */
    private function seedRole(): array
    {
        $admin = $this->makeAdmin();
        $this->enableCapabilities();
        $this->actingAs($admin);
        $this->post('/admin/roles', [
            'name' => 'Deputy', 'description' => '', 'capabilities' => ['core.thread.lock'],
            'current_password' => 'password123',
        ]);
        $roleId = (int) $this->db->fetchValue("SELECT id FROM roles WHERE role_key LIKE 'custom.%' ORDER BY id DESC LIMIT 1");
        return ['admin' => $admin, 'roleId' => $roleId];
    }

    public function test_routes_are_dark_without_the_flag(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $this->assertStatus(404, $this->post('/admin/roles/1/assignments', ['username' => 'x', 'current_password' => 'password123']));
        $this->assertStatus(404, $this->post('/admin/role-assignments/1/revoke'));
        $this->assertStatus(404, $this->post('/admin/role-assignments/1/renew', ['ends_at' => '2030-01-01 00:00', 'current_password' => 'password123']));
    }

    public function test_grant_revoke_renew_journey_no_js(): void
    {
        ['roleId' => $roleId] = $this->seedRole();
        $subject = $this->makeUser();

        $r = $this->post('/admin/roles/' . $roleId . '/assignments', [
            'username' => $subject['username'], 'scope_type' => 'site', 'scope_id' => '',
            'starts_at' => '', 'ends_at' => gmdate('Y-m-d H:i', time() + 3600),
            'reason' => 'pilot', 'current_password' => 'password123',
        ]);
        $this->assertRedirect($r);

        $page = $this->get('/admin/roles/' . $roleId);
        $this->assertSeeText($page, $subject['username']);
        $assignmentId = (int) $this->db->fetchValue('SELECT id FROM role_assignments ORDER BY id DESC LIMIT 1');

        $this->assertRedirect($this->post('/admin/role-assignments/' . $assignmentId . '/renew', [
            'ends_at' => gmdate('Y-m-d H:i', time() + 7200), 'current_password' => 'password123',
        ]));
        $this->assertRedirect($this->post('/admin/role-assignments/' . $assignmentId . '/revoke', []));
        $this->assertSeeText($this->get('/admin/roles/' . $roleId), 'revoked');
    }

    public function test_validation_error_rerenders_422_preserving_input(): void
    {
        ['roleId' => $roleId] = $this->seedRole();
        $r = $this->post('/admin/roles/' . $roleId . '/assignments', [
            'username' => 'nobody-here', 'scope_type' => 'site', 'scope_id' => '',
            'starts_at' => '', 'ends_at' => '', 'reason' => 'keep me',
            'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $r);
        $this->assertSeeText($r, 'keep me'); // anti-draft-loss: typed input survives
    }

    public function test_renew_validation_error_rerenders_422_preserving_input(): void
    {
        ['roleId' => $roleId] = $this->seedRole();
        $subject = $this->makeUser();
        $this->post('/admin/roles/' . $roleId . '/assignments', [
            'username' => $subject['username'], 'scope_type' => 'site', 'scope_id' => '',
            'starts_at' => '', 'ends_at' => gmdate('Y-m-d H:i', time() + 3600),
            'reason' => 'pilot', 'current_password' => 'password123',
        ]);
        $assignmentId = (int) $this->db->fetchValue('SELECT id FROM role_assignments ORDER BY id DESC LIMIT 1');

        $r = $this->post('/admin/role-assignments/' . $assignmentId . '/renew', [
            'ends_at' => 'not-a-date', 'current_password' => 'password123',
        ]);

        $this->assertStatus(422, $r);
        $this->assertSeeText($r, 'not-a-date');
    }

    public function test_renew_of_a_concurrently_revoked_assignment_shows_the_error_v5(): void
    {
        // If the row is revoked between page load and submit (another admin, or
        // this admin in a second tab), renew() refuses with an 'assignment'
        // error. That message must stay VISIBLE — the row's error paragraphs used
        // to sit inside the status!=='revoked' guard, so a now-revoked row
        // swallowed the very error that only fires when the row IS revoked,
        // leaving a silent 422 with the typed expiry dropped (review V5).
        ['roleId' => $roleId] = $this->seedRole();
        $subject = $this->makeUser();
        $this->post('/admin/roles/' . $roleId . '/assignments', [
            'username' => $subject['username'], 'scope_type' => 'site', 'scope_id' => '',
            'starts_at' => '', 'ends_at' => gmdate('Y-m-d H:i', time() + 3600),
            'reason' => 'pilot', 'current_password' => 'password123',
        ]);
        $assignmentId = (int) $this->db->fetchValue('SELECT id FROM role_assignments ORDER BY id DESC LIMIT 1');

        // Concurrent revoke lands first.
        $this->assertRedirect($this->post('/admin/role-assignments/' . $assignmentId . '/revoke', []));

        // Now renew the already-revoked row.
        $r = $this->post('/admin/role-assignments/' . $assignmentId . '/renew', [
            'ends_at' => gmdate('Y-m-d H:i', time() + 7200), 'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $r);
        $this->assertSeeText($r, 'Revoked assignments cannot be renewed'); // visible, not swallowed
    }

    public function test_category_scoped_assignment_shows_the_category_name_not_a_board_v4(): void
    {
        // The assignments table used to resolve every scope_id through the board
        // name map regardless of scope_type, so a category-scoped grant showed
        // the name of whatever unrelated board shared the numeric id (boards and
        // categories auto-increment independently), or a bare '#id'. It must show
        // the category's own name (review V4).
        ['roleId' => $roleId] = $this->seedRole();
        $subject = $this->makeUser();
        $catId = $this->makeCategory('Zeta Category Scope'); // sentinel name, rendered nowhere else

        $r = $this->post('/admin/roles/' . $roleId . '/assignments', [
            'username' => $subject['username'], 'scope_type' => 'category', 'scope_id' => (string) $catId,
            'starts_at' => '', 'ends_at' => '', 'reason' => '', 'current_password' => 'password123',
        ]);
        $this->assertRedirect($r);

        $page = $this->get('/admin/roles/' . $roleId);
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Zeta Category Scope');
    }

    public function test_renew_wrong_password_rerenders_422_with_error_on_the_renewed_row(): void
    {
        // renew() reauths BEFORE touching the window, so a wrong password is the
        // single most likely renew failure. The reauth error must surface on the
        // row being renewed (anti-draft-loss), with the typed expiry preserved.
        ['roleId' => $roleId] = $this->seedRole();
        $subject = $this->makeUser();
        $this->post('/admin/roles/' . $roleId . '/assignments', [
            'username' => $subject['username'], 'scope_type' => 'site', 'scope_id' => '',
            'starts_at' => '', 'ends_at' => gmdate('Y-m-d H:i', time() + 3600),
            'reason' => 'pilot', 'current_password' => 'password123',
        ]);
        $assignmentId = (int) $this->db->fetchValue('SELECT id FROM role_assignments ORDER BY id DESC LIMIT 1');

        $typedExpiry = gmdate('Y-m-d H:i', time() + 7200);
        $r = $this->post('/admin/role-assignments/' . $assignmentId . '/renew', [
            'ends_at' => $typedExpiry, 'current_password' => 'wrong-password',
        ]);

        $this->assertStatus(422, $r);
        $this->assertSeeText($r, 'current password is incorrect'); // reauth error is shown, not swallowed
        $this->assertSeeText($r, $typedExpiry); // anti-draft-loss: typed expiry survives on the row
        // On the correct row ONLY: the shared errors bag must not bleed the reauth
        // message onto the edit-definition or assign forms on the same page.
        self::assertSame(1, substr_count($r->body(), 'Your current password is incorrect.'));
    }
}
