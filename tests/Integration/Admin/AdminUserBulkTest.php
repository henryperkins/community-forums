<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use Tests\Support\TestCase;

/**
 * Bulk user moderation (ADMIN §5.1 bulk-selectable, §3.2 "each still audited
 * individually"), the audited PII reveal, and the ban typed-confirmation —
 * 2026-07-18 remediation coverage.
 */
final class AdminUserBulkTest extends TestCase
{
    public function test_bulk_confirm_page_lists_the_selection(): void
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $a = $this->makeUser(['username' => 'bulka']);
        $b = $this->makeUser(['username' => 'bulkb']);
        $this->actingAs($admin);

        $res = $this->post('/admin/users/bulk', [
            'bulk_action' => 'warn',
            'selected' => [(string) $a['id'], (string) $b['id']],
        ]);

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, '@bulka');
        $this->assertSeeText($res, '@bulkb');
        $this->assertSeeText($res, 'Warn 2 members');
    }

    public function test_bulk_422_preserves_the_row_selection(): void
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $a = (int) $this->makeUser(['username' => 'bulksel1'])['id'];
        $b = (int) $this->makeUser(['username' => 'bulksel2'])['id'];
        $this->actingAs($admin);

        // Missing action with rows ticked: the 422 re-render must keep the ticks
        // (round-2 audit finding 4 — the error used to clear the selection).
        $res = $this->post('/admin/users/bulk', ['selected' => [(string) $a, (string) $b], 'bulk_action' => '']);

        $this->assertStatus(422, $res);
        self::assertMatchesRegularExpression('/value="' . $a . '"[^>]*checked/', $res->body());
        self::assertMatchesRegularExpression('/value="' . $b . '"[^>]*checked/', $res->body());
    }

    public function test_bulk_warn_applies_and_audits_per_member(): void
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->actingAs($admin);

        $res = $this->post('/admin/users/bulk/apply', [
            'bulk_action' => 'warn',
            'selected' => [(string) $a['id'], (string) $b['id']],
            'reason' => 'Coordinated spam wave',
        ]);

        $this->assertRedirectContains($res, '/admin/users');
        self::assertSame(2, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings'));
        self::assertSame(
            2,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'warn' AND target_type = 'user'"),
        );
    }

    public function test_bulk_suspend_skips_admin_targets_and_reports_it(): void
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $regular = $this->makeUser(['username' => 'suspendme']);
        $otherAdmin = $this->makeAdmin(['username' => 'peer']);
        $this->actingAs($admin);

        $res = $this->post('/admin/users/bulk/apply', [
            'bulk_action' => 'suspend',
            'selected' => [(string) $regular['id'], (string) $otherAdmin['id']],
            'reason' => 'Cooling-off period',
            'until' => '',
        ]);

        $this->assertRedirectContains($res, '/admin/users');
        self::assertSame('suspended', (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [(int) $regular['id']]));
        self::assertSame('active', (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [(int) $otherAdmin['id']]));
        // The skip is reported in the flash cookie, not silently swallowed.
        $flash = implode(' ', $res->cookieHeaders());
        self::assertStringContainsString('Skipped', urldecode($flash));
    }

    public function test_bulk_suspend_with_malformed_until_aborts_before_any_write(): void
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $a = $this->makeUser();
        $this->actingAs($admin);

        $res = $this->post('/admin/users/bulk/apply', [
            'bulk_action' => 'suspend',
            'selected' => [(string) $a['id']],
            'reason' => 'Typed a bad date',
            'until' => '2026-13-99 00:00:00',
        ]);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Typed a bad date');
        self::assertSame('active', (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [(int) $a['id']]));
    }

    public function test_bulk_with_empty_selection_is_422(): void
    {
        $this->actingAs($this->makeAdmin());
        $res = $this->post('/admin/users/bulk', ['bulk_action' => 'warn']);
        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Select at least one member');
    }

    public function test_bulk_routes_require_admin(): void
    {
        $this->makeAdmin(); // past the first-run setup gate (needs ≥1 admin)
        $this->actingAs($this->makeUser());
        $this->assertStatus(403, $this->post('/admin/users/bulk', ['bulk_action' => 'warn', 'selected' => ['1']]));
        $this->assertStatus(403, $this->post('/admin/users/bulk/apply', ['bulk_action' => 'warn', 'selected' => ['1'], 'reason' => 'x']));
    }

    public function test_ban_requires_the_typed_username(): void
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $target = $this->makeUser(['username' => 'bannable']);
        $this->actingAs($admin);

        $miss = $this->post('/admin/users/' . (int) $target['id'] . '/ban', [
            'reason' => 'Ban rationale that must survive',
            'confirm_username' => 'wrong-name',
        ]);
        $this->assertStatus(422, $miss);
        $this->assertSeeText($miss, 'Ban rationale that must survive');
        self::assertSame('active', (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [(int) $target['id']]));

        $hit = $this->post('/admin/users/' . (int) $target['id'] . '/ban', [
            'reason' => 'Ban rationale that must survive',
            'confirm_username' => 'bannable',
        ]);
        $this->assertRedirectContains($hit, '/admin/users/' . (int) $target['id']);
        self::assertSame('banned', (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [(int) $target['id']]));
    }

    public function test_suspension_history_row_is_recorded_as_read_only_type(): void
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $target = $this->makeUser();
        $this->actingAs($admin);

        $this->post('/admin/users/' . (int) $target['id'] . '/suspend', ['reason' => 'Timeout', 'until' => '']);

        self::assertSame(
            'post',
            (string) $this->db->fetchValue('SELECT type FROM bans WHERE user_id = ?', [(int) $target['id']]),
        );
    }

    public function test_pii_reveal_shows_email_once_and_writes_a_view_pii_audit_row(): void
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $target = $this->makeUser(['email' => 'private-address@example.test']);
        $this->actingAs($admin);

        // The plain record never shows the email.
        $record = $this->get('/admin/users/' . (int) $target['id']);
        $this->assertStatus(200, $record);
        $this->assertDontSeeText($record, 'private-address@example.test');

        $revealed = $this->post('/admin/users/' . (int) $target['id'] . '/pii');
        $this->assertStatus(200, $revealed);
        $this->assertSeeText($revealed, 'private-address@example.test');
        self::assertSame(
            1,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'view_pii' AND target_id = ?", [(int) $target['id']]),
        );
    }

    public function test_directory_exact_multiple_of_page_size_has_no_next_link(): void
    {
        // PR #44 spec §4: has_next came from count(rows) === PER_PAGE — a dead
        // Next link on any exact multiple. Unique username prefix so ambient
        // fixture users cannot skew the filtered count.
        $this->actingAs($this->makeAdmin());
        for ($i = 0; $i < 50; $i++) {
            $this->makeUser(['username' => 'pageruser' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $first = $this->get('/admin/users', ['q' => 'pageruser']);
        $this->assertStatus(200, $first);
        $this->assertSeeText($first, 'pageruser00');
        $this->assertDontSeeText($first, 'Next');

        // Past-the-end page renders empty without error.
        $second = $this->get('/admin/users', ['q' => 'pageruser', 'page' => '1']);
        $this->assertStatus(200, $second);
        $this->assertDontSeeText($second, 'pageruser00');
    }
}
