<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use Tests\Support\TestCase;

/** The /admin/audit screen (ADMIN §3.6/§9.2) — 2026-07-18 remediation coverage. */
final class AdminAuditPageTest extends TestCase
{
    public function test_audit_screen_is_admin_only(): void
    {
        $this->makeAdmin(); // past the first-run setup gate (needs ≥1 admin)
        $this->assertRedirectContains($this->get('/admin/audit'), '/login');
        $this->actingAs($this->makeUser());
        $this->assertStatus(403, $this->get('/admin/audit'));
    }

    public function test_audit_screen_lists_and_filters_actions(): void
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $target = $this->makeUser(['username' => 'audited']);
        $this->actingAs($admin);
        // Two distinct audited actions against the same target.
        $this->post('/admin/users/' . (int) $target['id'] . '/warn', ['reason' => 'First strike']);
        $this->post('/admin/users/' . (int) $target['id'] . '/note', ['body' => 'Watch this account']);

        $all = $this->get('/admin/audit');
        $this->assertStatus(200, $all);
        $this->assertSeeText($all, 'warn');
        $this->assertSeeText($all, 'First strike');

        $filtered = $this->get('/admin/audit', ['action' => 'warn']);
        $this->assertStatus(200, $filtered);
        $this->assertSeeText($filtered, 'First strike');
        $this->assertSeeText($filtered, '1 matching entry');

        $byTarget = $this->get('/admin/audit', ['target_type' => 'user', 'target_id' => (string) $target['id']]);
        $this->assertStatus(200, $byTarget);
        $this->assertSeeText($byTarget, 'warn');

        $none = $this->get('/admin/audit', ['action' => 'no_such_action_prefix']);
        $this->assertStatus(200, $none);
        $this->assertSeeText($none, 'No audit entries match');
    }

    public function test_audit_screen_paginates(): void
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $target = $this->makeUser();
        $this->actingAs($admin);
        // 51 warn rows → page 0 full (50) with a Next link, page 1 has the rest.
        for ($i = 0; $i < 51; $i++) {
            $this->post('/admin/users/' . (int) $target['id'] . '/warn', ['reason' => 'r' . $i]);
        }

        $first = $this->get('/admin/audit', ['action' => 'warn']);
        $this->assertStatus(200, $first);
        $this->assertSeeText($first, 'Next');

        $second = $this->get('/admin/audit', ['action' => 'warn', 'page' => '1']);
        $this->assertStatus(200, $second);
        $this->assertSeeText($second, 'Previous');
    }

    public function test_dashboard_audit_card_links_to_the_audit_screen(): void
    {
        $this->actingAs($this->makeAdmin());
        $res = $this->get('/admin');
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, '/admin/audit');
    }
}
