<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\BoardModeratorRepository;
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
        $this->assertDontSeeText($res, 'Review before applying');
        self::assertMatchesRegularExpression('/<a[^>]+href="\/admin\/users"[^>]*>[\s\S]*?All members[\s\S]*?<\/a>/', $res->body());
        self::assertSame(2, substr_count($res->body(), 'member-bulk-subject-monogram'));
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
        $this->assertSeeText($res, 'Choose an action to apply to the selected members.');
        $this->assertSeeText($res, '2 members selected');
        self::assertMatchesRegularExpression('/value="' . $a . '"[^>]*checked/', $res->body());
        self::assertMatchesRegularExpression('/value="' . $b . '"[^>]*checked/', $res->body());
    }

    public function test_bulk_form_carries_filtered_page_state_into_422_rerender(): void
    {
        $admin = $this->makeAdmin(['username' => 'bulkfilteradmin']);
        $ids = [];
        for ($i = 0; $i < 51; $i++) {
            $ids[] = (int) $this->makeUser([
                'username' => 'bulkfilter' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ])['id'];
        }
        $this->actingAs($admin);

        $page = $this->get('/admin/users', ['q' => 'bulkfilter', 'role' => 'user', 'page' => '1']);
        $this->assertStatus(200, $page);
        self::assertMatchesRegularExpression('/type="hidden" name="q" value="bulkfilter"/', $page->body());
        self::assertMatchesRegularExpression('/type="hidden" name="role" value="user"/', $page->body());
        self::assertMatchesRegularExpression('/type="hidden" name="page" value="1"/', $page->body());

        $res = $this->post('/admin/users/bulk', [
            'q' => 'bulkfilter',
            'role' => 'user',
            'page' => '1',
            'selected' => [(string) $ids[0]],
            'bulk_action' => '',
        ]);

        $this->assertStatus(422, $res);
        self::assertMatchesRegularExpression('/value="' . $ids[0] . '"[^>]*checked/', $res->body());
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

    public function test_suspend_confirmation_marks_only_ungovernable_subjects_and_counts_actionable_members(): void
    {
        $admin = $this->makeAdmin(['username' => 's11actor']);
        $peer = $this->makeAdmin(['username' => 's11peer']);
        $regular = $this->makeUser(['username' => 's11regular']);
        $this->actingAs($admin);

        $res = $this->post('/admin/users/bulk', [
            'bulk_action' => 'suspend',
            'selected' => [(string) $admin['id'], (string) $peer['id'], (string) $regular['id']],
        ]);

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'Suspend 3 members');
        self::assertSame(2, substr_count($res->body(), 'skipped — administrator'));
        self::assertMatchesRegularExpression('/<button[^>]*type="submit"[^>]*>Suspend 1 member<\/button>/', $res->body());
        $this->assertSeeText(
            $res,
            'Every selected member becomes read-only until the expiry (blank is indefinite). Your own account and other administrators are skipped automatically. Each suspension is audited individually and reversible with Lift.',
        );
        $this->assertSeeText($res, 'Until (UTC, optional — blank is indefinite)');
    }

    public function test_warn_confirmation_does_not_claim_admin_targets_are_skipped(): void
    {
        $admin = $this->makeAdmin(['username' => 's11warnactor']);
        $peer = $this->makeAdmin(['username' => 's11warnpeer']);
        $regular = $this->makeUser(['username' => 's11warnregular']);
        $this->actingAs($admin);

        $confirm = $this->post('/admin/users/bulk', [
            'bulk_action' => 'warn',
            'selected' => [(string) $peer['id'], (string) $regular['id']],
        ]);

        $this->assertStatus(200, $confirm);
        $this->assertDontSeeText($confirm, 'skipped — administrator');
        self::assertMatchesRegularExpression('/<button[^>]*type="submit"[^>]*>Warn 2 members<\/button>/', $confirm->body());

        $applied = $this->post('/admin/users/bulk/apply', [
            'bulk_action' => 'warn',
            'selected' => [(string) $peer['id'], (string) $regular['id']],
            'reason' => 'Same formal warning',
        ]);
        $this->assertRedirectContains($applied, '/admin/users');
        self::assertSame(2, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM warnings WHERE user_id IN (?, ?)',
            [(int) $peer['id'], (int) $regular['id']],
        ));
        $flash = urldecode(implode(' ', $applied->cookieHeaders()));
        self::assertStringContainsString('Warned 2 members — each action was audited individually.', $flash);
        self::assertStringNotContainsString('Skipped', $flash);
    }

    public function test_bulk_empty_reason_uses_shared_copy_and_preserves_fields_and_selection(): void
    {
        $admin = $this->makeAdmin(['username' => 's11reasonactor']);
        $a = $this->makeUser(['username' => 's11reasonone']);
        $b = $this->makeUser(['username' => 's11reasontwo']);
        $this->actingAs($admin);

        $res = $this->post('/admin/users/bulk/apply', [
            'bulk_action' => 'suspend',
            'selected' => [(string) $a['id'], (string) $b['id']],
            'reason' => '',
            'until' => '2026-08-31 10:30:00',
        ]);

        $this->assertStatus(422, $res);
        $this->assertSeeText(
            $res,
            'A shared reason is required — it is shown to every member and written to each audit entry.',
        );
        self::assertMatchesRegularExpression('/name="reason"[^>]*aria-invalid="true"[^>]*aria-describedby="err-reason"/', $res->body());
        self::assertMatchesRegularExpression('/name="until"[^>]*value="2026-08-31 10:30:00"/', $res->body());
        self::assertSame(2, substr_count($res->body(), 'name="selected[]"'));
        self::assertStringContainsString('name="_token"', $res->body());
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
        $res = $this->post('/admin/users/bulk', ['bulk_action' => '']);
        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Select at least one member before choosing a bulk action.');
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
        self::assertMatchesRegularExpression('/<span class="pager-control member-directory-pager-control is-disabled" aria-disabled="true">Next<\/span>/', $first->body());

        // Past-the-end page renders empty without error.
        $second = $this->get('/admin/users', ['q' => 'pageruser', 'page' => '1']);
        $this->assertStatus(200, $second);
        $this->assertDontSeeText($second, 'pageruser00');
    }

    public function test_directory_copies_member_table_anatomy_and_keeps_production_extensions(): void
    {
        $admin = $this->makeAdmin(['username' => 's11actoroutside']);
        $moderator = $this->makeUser([
            'username' => 's11directorymod',
            'display_name' => 'Directory Moderator',
            'role' => 'moderator',
        ]);
        $suspended = $this->makeUser([
            'username' => 's11directorymember',
            'display_name' => 'Directory Member',
            'status' => 'suspended',
        ]);
        $this->db->run('UPDATE users SET reputation = ?, post_count = ? WHERE id = ?', [8740, 1204, (int) $moderator['id']]);
        $categoryId = $this->makeCategory('Slice 11 directory');
        $board = $this->makeBoard($categoryId, ['name' => 'Slice 11 board']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $moderator['id']);
        $this->actingAs($admin);

        $res = $this->get('/admin/users', ['q' => 's11directory']);

        $this->assertStatus(200, $res);
        self::assertStringContainsString('Members &amp; invitations', $res->body());
        self::assertSame(1, substr_count($res->body(), 'aria-label="Member sections"'));
        self::assertMatchesRegularExpression('/class="admin-tab is-active" aria-current="page">Directory<\/span>/', $res->body());
        $this->assertSeeText($res, 'Joined from');
        $this->assertSeeText($res, 'Joined to');
        $this->assertSeeText($res, '2 members of 2');
        self::assertMatchesRegularExpression('/<th[^>]*aria-sort="[^"]+"[^>]*>[\s\S]*?>Member(?:<|\s)/', $res->body());
        self::assertMatchesRegularExpression('/aria-sort="[^"]+"[\s\S]*?last_seen/', $res->body());
        self::assertStringContainsString('aria-label="Select all members on this page"', $res->body());
        self::assertStringContainsString('role="region" aria-label="User directory"', $res->body());
        self::assertSame(2, substr_count($res->body(), 'member-directory-member-monogram'));
        $this->assertSeeText($res, 'Directory Moderator');
        self::assertStringNotContainsString('(Directory Moderator)', $res->body());
        self::assertStringContainsString('member-directory-role is-moderator', $res->body());
        self::assertStringContainsString('title="Moderates 1 board"', $res->body());
        self::assertStringContainsString('member-directory-board-mod', $res->body());
        self::assertMatchesRegularExpression('/s11directorymember[\s\S]*?member-directory-state is-suspended/', $res->body());
        $this->assertSeeText($res, '8,740');
        $this->assertSeeText($res, '1,204');
        $this->assertSeeText($res, 'None selected');
        self::assertMatchesRegularExpression('/<span class="pager-control member-directory-pager-control is-disabled" aria-disabled="true">Previous<\/span>/', $res->body());
        self::assertMatchesRegularExpression('/<span class="pager-control member-directory-pager-control is-disabled" aria-disabled="true">Next<\/span>/', $res->body());
        $this->assertSeeText($res, (string) $suspended['username']);
    }

    public function test_directory_empty_state_and_result_count_match_the_design(): void
    {
        $this->actingAs($this->makeAdmin());

        $res = $this->get('/admin/users', ['q' => 's11-no-such-member']);

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, '0 members of 0');
        $this->assertSeeText($res, 'No members match these filters');
        $this->assertSeeText($res, 'Reset the filters to see the whole directory.');
        $this->assertDontSeeText($res, 'No users match these filters.');
    }

    public function test_directory_keeps_advanced_filters_disclosed_after_a_get_round_trip(): void
    {
        $this->actingAs($this->makeAdmin());

        $initial = $this->get('/admin/users');

        $this->assertStatus(200, $initial);
        $body = $initial->body();
        self::assertMatchesRegularExpression(
            '/<div class="filter-grid member-directory-filter-grid member-directory-common-filter-grid">[\s\S]*?name="q"[\s\S]*?name="role"[\s\S]*?name="status"[\s\S]*?<\/div>\s*<details class="member-directory-advanced-filters">/',
            $body,
        );
        self::assertStringContainsString('<summary>More filters</summary>', $body);
        self::assertStringNotContainsString('<details class="member-directory-advanced-filters" open>', $body);
        self::assertStringContainsString('class="member-directory-table-shell" data-overflow-cue', $body);
        self::assertStringContainsString('role="region" aria-label="User directory" data-overflow-region', $body);

        $filtered = $this->get('/admin/users', ['last_seen' => '7']);

        $this->assertStatus(200, $filtered);
        self::assertStringContainsString('<details class="member-directory-advanced-filters" open>', $filtered->body());
        self::assertStringContainsString('<option value="7" selected>Past 7 days</option>', $filtered->body());

        $zeroPostRange = $this->get('/admin/users', ['min_posts' => '0', 'max_posts' => '0']);

        $this->assertStatus(200, $zeroPostRange);
        self::assertStringContainsString('<details class="member-directory-advanced-filters" open>', $zeroPostRange->body());
        self::assertStringContainsString('name="min_posts" class="input member-directory-number-input" min="0" value="0"', $zeroPostRange->body());
        self::assertStringContainsString('name="max_posts" class="input member-directory-number-input" min="0" value="0"', $zeroPostRange->body());
    }

    public function test_member_tabs_disable_the_invitations_destination_when_the_flag_is_dark(): void
    {
        $admin = $this->makeAdmin();
        $this->db->run(
            "INSERT INTO settings (`key`, value, updated_at) VALUES ('features', ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)",
            [json_encode(['invitations' => false], JSON_THROW_ON_ERROR)],
        );
        $this->actingAs($admin);

        $res = $this->get('/admin/users');

        $this->assertStatus(200, $res);
        self::assertMatchesRegularExpression('/<span class="admin-tab is-disabled" aria-disabled="true" data-destination="\/admin\/invitations">/', $res->body());
        $this->assertSeeText($res, 'Disabled until the feature flag is enabled');
        self::assertDoesNotMatchRegularExpression('/<a[^>]+href="\/admin\/invitations"/', $res->body());
    }
}
