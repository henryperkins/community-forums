<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\ModerationLogRepository;
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

    public function test_invalid_date_filters_are_a_422_with_no_rows(): void
    {
        // PR #44 spec §4: `from=banana` used to filter as the SQL string
        // 'banana 00:00:00' at 200 — dates must round-trip Y-m-d exactly or
        // the screen refuses with the typed value preserved and zero rows.
        $admin = $this->makeAdmin(['password' => 'password123']);
        $target = $this->makeUser();
        $this->actingAs($admin);
        $this->post('/admin/users/' . (int) $target['id'] . '/warn', ['reason' => 'datecheck marker']);

        $res = $this->get('/admin/audit', ['from' => 'banana']);
        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Use YYYY-MM-DD.');
        $this->assertSeeText($res, 'banana');
        $this->assertDontSeeText($res, 'datecheck marker');

        $to = $this->get('/admin/audit', ['to' => '2026-13-45']);
        $this->assertStatus(422, $to);
        $this->assertSeeText($to, 'Use YYYY-MM-DD.');

        // Valid inclusive bounds still admit today's row.
        $today = gmdate('Y-m-d');
        $ok = $this->get('/admin/audit', ['from' => $today, 'to' => $today]);
        $this->assertStatus(200, $ok);
        $this->assertSeeText($ok, 'datecheck marker');
    }

    public function test_exact_multiple_of_page_size_has_no_next_link(): void
    {
        // Unique action prefix so ambient fixture rows cannot skew the count
        // (the old `has_next = count(rows) === PER_PAGE` showed a dead Next
        // link on any exact multiple).
        $this->actingAs($this->makeAdmin());
        $log = new ModerationLogRepository($this->db);
        for ($i = 0; $i < 50; $i++) {
            $log->log(['actor_id' => null, 'action' => 'plancheck.' . $i, 'target_type' => 'setting', 'target_id' => $i]);
        }

        $first = $this->get('/admin/audit', ['action' => 'plancheck.']);
        $this->assertStatus(200, $first);
        $this->assertSeeText($first, '50 matching entries');
        $this->assertDontSeeText($first, 'Next');

        // Past-the-end page renders empty without error.
        $second = $this->get('/admin/audit', ['action' => 'plancheck.', 'page' => '1']);
        $this->assertStatus(200, $second);
        $this->assertSeeText($second, 'No audit entries match');
    }

    public function test_actor_filter_matches_names_and_misses_return_no_rows(): void
    {
        $admin = $this->makeAdmin(['username' => 'auditactor', 'password' => 'password123']);
        $target = $this->makeUser();
        $this->actingAs($admin);
        $this->post('/admin/users/' . (int) $target['id'] . '/warn', ['reason' => 'actor marker']);

        $hit = $this->get('/admin/audit', ['actor' => 'uditacto']);
        $this->assertStatus(200, $hit);
        $this->assertSeeText($hit, 'actor marker');

        $miss = $this->get('/admin/audit', ['actor' => 'zz-no-such-actor']);
        $this->assertStatus(200, $miss);
        $this->assertDontSeeText($miss, 'actor marker');
        $this->assertSeeText($miss, 'No audit entries match');
    }

    public function test_dashboard_card_and_staff_panel_still_name_actors(): void
    {
        // Guards the single-tabling refactor: actor handles now come from a
        // batched enrichment, and both consumers must keep naming actors.
        $admin = $this->makeAdmin(['username' => 'auditadmin', 'password' => 'password123']);
        $target = $this->makeUser(['username' => 'auditsubject']);
        $this->actingAs($admin);
        $this->post('/admin/users/' . (int) $target['id'] . '/warn', ['reason' => 'named marker']);

        $this->assertSeeText($this->get('/admin'), 'auditadmin');
        $this->assertSeeText($this->get('/mod/u/' . (int) $target['id']), 'by @auditadmin');
    }
}
