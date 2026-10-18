<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\RoleRepository;
use Tests\Support\TestCase;

final class AppPeopleRemediationTest extends TestCase
{
    private function role(): int
    {
        $this->actingAs($this->makeAdmin());
        $this->assertRedirect($this->post('/admin/roles', [
            'name' => 'Scope review deputy', 'capabilities' => ['core.thread.lock'],
            'current_password' => 'password123',
        ]));
        return (int) (new RoleRepository($this->db))->findByKey('custom.scope_review_deputy')['id'];
    }

    public function test_named_category_target_is_not_interpreted_as_a_board_or_site_grant(): void
    {
        $roleId = $this->role();
        $categoryId = $this->makeCategory('Review category');
        $this->makeBoard($categoryId, ['name' => 'Review board']);
        $subject = $this->makeUser();
        $response = $this->post('/admin/roles/' . $roleId . '/assignments', [
            'username' => $subject['username'], 'scope_target' => 'category:' . $categoryId,
            'current_password' => 'password123',
        ]);
        $this->assertRedirect($response);
        $assignment = $this->db->fetch('SELECT scope_type, scope_id FROM role_assignments WHERE role_id = :role AND subject_id = :subject', [
            'role' => $roleId, 'subject' => (int) $subject['id'],
        ]);
        self::assertSame('category', $assignment['scope_type']);
        self::assertSame($categoryId, (int) $assignment['scope_id']);
        $this->assertSeeText($this->get('/admin/roles/' . $roleId), 'Review category');
    }

    public function test_malformed_named_target_does_not_fall_back_to_site_and_preserves_draft(): void
    {
        $roleId = $this->role();
        $subject = $this->makeUser();
        $response = $this->post('/admin/roles/' . $roleId . '/assignments', [
            'username' => $subject['username'], 'scope_target' => 'board:2oops',
            'scope_type' => 'site', 'reason' => 'Keep target review rationale',
            'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'Choose a site, category, or board from the scope list.');
        $this->assertSeeText($response, 'No one has been assigned this role yet.');
        self::assertStringContainsString('value="Keep target review rationale"', $response->body());
        self::assertMatchesRegularExpression('/<option[^>]*value="board:2oops"[^>]*selected/', $response->body());
    }

    public function test_missing_capabilities_error_is_announced_and_focuses_an_available_choice(): void
    {
        $this->actingAs($this->makeAdmin());
        $response = $this->post('/admin/roles', [
            'name' => 'Preserved role draft', 'description' => 'Keep this description',
            'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $response);
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->body());
        $xpath = new \DOMXPath($dom);
        self::assertSame(1, $xpath->query('//*[@role="group" and @aria-invalid="true" and @aria-describedby="err-role-create-capabilities"]')->length);
        self::assertSame(1, $xpath->query('//*[@id="err-role-create-capabilities" and @role="alert"]')->length);
        self::assertSame(1, $xpath->query('//input[@name="capabilities[]" and @autofocus and not(@disabled) and @aria-describedby="err-role-create-capabilities"]')->length);
        self::assertStringContainsString('value="Preserved role draft"', $response->body());
    }

    public function test_role_record_explains_shadow_effect_at_the_decision_controls(): void
    {
        $roleId = $this->role();
        $response = $this->get('/admin/roles/' . $roleId);
        $this->assertStatus(200, $response);
        $this->assertSeeText($response, 'Saving roles and assignments does not change live access.');
        self::assertStringContainsString('data-role-live-effect', $response->body());
    }
}
