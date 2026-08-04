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
        $this->assertSeeText($filtered, '1 entry');

        $byTarget = $this->get('/admin/audit', ['target_type' => 'user', 'target_id' => (string) $target['id']]);
        $this->assertStatus(200, $byTarget);
        $this->assertSeeText($byTarget, 'warn');

        $none = $this->get('/admin/audit', ['action' => 'no_such_action_prefix']);
        $this->assertStatus(200, $none);
        $this->assertSeeText($none, 'Nothing matches these filters');
        self::assertStringContainsString('<a href="/admin/audit">Reset filters</a>', $none->body());
    }

    public function test_audit_screen_paginates(): void
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $target = $this->makeUser();
        $this->actingAs($admin);
        // 51 warn rows → external page 1 is full (50), page 2 has the rest.
        for ($i = 0; $i < 51; $i++) {
            $this->post('/admin/users/' . (int) $target['id'] . '/warn', ['reason' => 'r' . $i]);
        }

        $first = $this->get('/admin/audit', ['action' => 'warn']);
        $this->assertStatus(200, $first);
        $this->assertSeeText($first, 'Next');
        $this->assertSeeText($first, 'Page 1 of 2');
        self::assertStringContainsString('<span class="pager-control is-disabled" aria-disabled="true">Previous</span>', $first->body());
        self::assertStringContainsString('href="/admin/audit?action=warn&amp;page=2">Next</a>', $first->body());
        self::assertStringNotContainsString('page=0', $first->body());

        $second = $this->get('/admin/audit', ['action' => 'warn', 'page' => '2']);
        $this->assertStatus(200, $second);
        $this->assertSeeText($second, 'Previous');
        $this->assertSeeText($second, 'Page 2 of 2');
        $this->assertSeeText($second, 'r0');
        self::assertStringContainsString('href="/admin/audit?action=warn&amp;page=1">Previous</a>', $second->body());
        self::assertStringNotContainsString('page=0', $second->body());
        self::assertStringContainsString('<span class="pager-control is-disabled" aria-disabled="true">Next</span>', $second->body());
    }

    /** Raw rows (no bcrypt) so a 500-account fixture stays fast. */
    private function bulkInsertUsers(string $prefix, int $count): void
    {
        $values = [];
        $params = [];
        for ($i = 0; $i < $count; $i++) {
            $values[] = "(?, ?, NULL, 'user', 'active', UTC_TIMESTAMP())";
            $params[] = $prefix . $i;
            $params[] = $prefix . $i . '@example.test';
        }
        $this->db->run(
            'INSERT INTO users (username, email, password_hash, role, status, created_at) VALUES ' . implode(',', $values),
            $params,
        );
    }

    public function test_actor_filter_matching_over_500_accounts_is_refused_not_truncated(): void
    {
        // Past the resolution cap the old code silently filtered by the first
        // 500 ids — incomplete rows AND a false total. Refuse loudly instead.
        $admin = $this->makeAdmin(['password' => 'password123']);
        $this->bulkInsertUsers('swarm', 501);
        $this->actingAs($admin);

        $res = $this->get('/admin/audit', ['actor' => 'swarm']);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'matches too many accounts');
    }

    public function test_actor_filter_matching_exactly_500_accounts_still_answers(): void
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $this->bulkInsertUsers('flock', 500);
        $this->actingAs($admin);

        $this->assertStatus(200, $this->get('/admin/audit', ['actor' => 'flock']));
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

    public function test_non_numeric_target_id_is_a_422_instead_of_dropping_the_filter(): void
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $target = $this->makeUser();
        $this->actingAs($admin);
        $this->post('/admin/users/' . (int) $target['id'] . '/warn', ['reason' => 'target-id marker']);

        $res = $this->get('/admin/audit', ['target_id' => 'not-a-number']);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Use a numeric target ID.');
        $this->assertSeeText($res, 'not-a-number');
        $this->assertDontSeeText($res, 'target-id marker');
    }

    public function test_exact_page_size_has_no_pager_and_out_of_range_page_clamps_to_final_page(): void
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
        $this->assertSeeText($first, '50 entries');
        $this->assertDontSeeText($first, 'Next');
        self::assertStringNotContainsString('aria-label="Pagination"', $first->body());

        // A stale or hand-authored page number clamps to the last real page.
        $clamped = $this->get('/admin/audit', ['action' => 'plancheck.', 'page' => '999']);
        $this->assertStatus(200, $clamped);
        $this->assertSeeText($clamped, 'plancheck.49');
        $this->assertSeeText($clamped, '50 entries');
        self::assertStringNotContainsString('aria-label="Pagination"', $clamped->body());
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
        $this->assertSeeText($miss, 'Nothing matches these filters');
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

    public function test_audit_body_adopts_the_design_register_without_weakening_raw_change_or_targets(): void
    {
        $admin = $this->makeAdmin(['username' => 'overviewaudit']);
        $target = $this->makeUser(['username' => 'overviewtarget']);
        $this->actingAs($admin);
        $this->db->run(
            "INSERT INTO moderation_log
                (actor_id, action, target_type, target_id, reason, before_json, after_json, created_at)
             VALUES (?, 'overview_user_change', 'user', ?, 'User reason', '{\"status\":\"active\"}', '{\"status\":\"suspended\"}', '2026-08-04 09:14:00')",
            [(int) $admin['id'], (int) $target['id']],
        );
        $this->db->run(
            "INSERT INTO moderation_log
                (actor_id, action, target_type, target_id, reason, before_json, after_json, created_at)
             VALUES (?, 'overview_board_change', 'board', 42, 'Board reason', NULL, NULL, '2026-08-04 08:52:00')",
            [(int) $admin['id']],
        );

        $response = $this->get('/admin/audit', ['action' => 'overview_']);
        $body = $response->body();

        $this->assertStatus(200, $response);
        self::assertStringContainsString(
            "Every moderation and admin action, append-only. Filter it, page through it, and follow a target's whole trail from its own record.",
            $body,
        );
        self::assertStringNotContainsString('ADMIN §3.6', $body);
        self::assertStringContainsString('class="card audit-filter-card"', $body);
        self::assertStringContainsString('class="card audit-table-card"', $body);
        self::assertMatchesRegularExpression('~<div class="form-actions filter-actions">.*?2 entries.*?</div>~s', $body);
        self::assertStringContainsString(
            '<td class="audit-time nowrap"><time datetime="2026-08-04T09:14:00Z">Aug 4, 2026 at 09:14 UTC</time></td>',
            $body,
        );
        self::assertMatchesRegularExpression(
            '~<a class="audit-target-link" href="/admin/users/' . (int) $target['id'] . '">user #' . (int) $target['id'] . '</a>~',
            $body,
        );
        self::assertStringContainsString('<span class="audit-target">board #42</span>', $body);
        self::assertStringContainsString('<details class="audit-change">', $body);
        self::assertStringContainsString('Before: <code>{&quot;status&quot;:&quot;active&quot;}</code>', $body);
        self::assertStringNotContainsString('aria-label="Pagination"', $body);
    }
}
