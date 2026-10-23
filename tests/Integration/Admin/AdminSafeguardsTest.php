<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AdminSafeguardsTest extends TestCase
{
    public function test_block_review_and_edit_preserve_the_exact_draft_without_writes(): void
    {
        $settings = new SettingRepository($this->db);
        $settings->set('antiabuse_mode', 'hold');
        $settings->set('antiabuse_blocked_words', ['original']);
        $this->actingAs($this->makeAdmin());
        $draft = "  First phrase\n<script>alert('draft')</script>\nFinal phrase  ";
        $input = ['antiabuse_mode' => 'block', 'antiabuse_blocked_words' => $draft];
        $review = $this->post('/admin/moderation', $input);
        $this->assertStatus(200, $review);
        $this->assertSeeText($review, 'Confirm Block mode');
        self::assertStringContainsString(htmlspecialchars($draft, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $review->body());
        self::assertStringNotContainsString('<script>alert', $review->body());
        self::assertSame('hold', $settings->getString('antiabuse_mode'));
        self::assertSame(['original'], $settings->get('antiabuse_blocked_words'));
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE reason = 'anti_abuse_settings'"));

        $edit = $this->post('/admin/moderation', $input + ['review_action' => 'edit']);
        $this->assertStatus(200, $edit);
        $this->assertSeeText($edit, 'Save anti-abuse settings');
        self::assertStringContainsString(htmlspecialchars($draft, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $edit->body());
        self::assertSame('hold', $settings->getString('antiabuse_mode'));

        $saved = $this->post('/admin/moderation', $input + ['review_action' => 'confirm']);
        $this->assertRedirect($saved, '/admin/moderation');
        self::assertSame('block', $settings->getString('antiabuse_mode'));
        self::assertSame(['First phrase', "<script>alert('draft')</script>", 'Final phrase'], $settings->get('antiabuse_blocked_words'));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE reason = 'anti_abuse_settings'"));
        // New phrases under an already blocking mode also need a review.
        $this->assertStatus(200, $this->post('/admin/moderation', ['antiabuse_mode' => 'block', 'antiabuse_blocked_words' => 'different']));
        self::assertSame('First phrase', $settings->get('antiabuse_blocked_words')[0]);
    }

    public function test_closing_registration_needs_confirmation_and_cancel_does_not_mutate(): void
    {
        $settings = new SettingRepository($this->db);
        $settings->set('registration_mode', 'open');
        $this->actingAs($this->makeAdmin());
        $review = $this->post('/admin/settings/registration', ['registration_mode' => 'closed']);
        $this->assertStatus(200, $review);
        $this->assertSeeText($review, 'Confirm closing registration');
        $this->assertSeeText($review, 'including by invitation or a new social sign-in');
        self::assertSame('open', $settings->getString('registration_mode'));
        $this->get('/admin/settings'); // Cancel is a GET back to the persisted form.
        self::assertSame('open', $settings->getString('registration_mode'));
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE reason = 'registration_mode'"));
        $saved = $this->post('/admin/settings/registration', ['registration_mode' => 'closed', 'review_action' => 'confirm']);
        $this->assertRedirect($saved, '/admin/settings');
        self::assertSame('closed', $settings->getString('registration_mode'));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE reason = 'registration_mode'"));
        $this->assertRedirect($this->post('/admin/settings/registration', ['registration_mode' => 'open']), '/admin/settings');
    }

    public function test_confirmation_keeps_csrf_role_state_feature_and_validation_guards(): void
    {
        $settings = new SettingRepository($this->db);
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $input = ['registration_mode' => 'closed', 'review_action' => 'confirm'];
        $this->assertStatus(403, $this->post('/admin/settings/registration', $input, false));
        $this->assertStatus(422, $this->post('/admin/settings/registration', ['registration_mode' => 'invalid', 'review_action' => 'confirm']));
        $settings->set('features', ['anti_abuse' => false]);
        $this->assertStatus(404, $this->post('/admin/moderation', ['antiabuse_mode' => 'block', 'review_action' => 'confirm']));
        $this->actingAs($this->makeUser());
        $this->assertStatus(403, $this->post('/admin/settings/registration', $input));
        $this->actingAs($admin);
        $this->db->run("UPDATE users SET status = 'suspended', suspended_until = NULL WHERE id = ?", [(int) $admin['id']]);
        $this->assertStatus(403, $this->post('/admin/settings/registration', $input));
        self::assertSame('open', $settings->getString('registration_mode', 'open'));
    }

    public function test_native_and_legacy_expiry_values_are_stored_as_utc_without_rollover(): void
    {
        $this->actingAs($this->makeAdmin());
        foreach (['2031-06-04T12:34' => '2031-06-04 12:34:00', '2031-06-04T12:34:56' => '2031-06-04 12:34:56', '2031-06-04 12:34:56' => '2031-06-04 12:34:56'] as $input => $expected) {
            $member = $this->makeUser();
            $response = $this->post('/admin/users/' . $member['id'] . '/suspend', ['reason' => 'Time bound', 'until' => $input]);
            $this->assertRedirect($response, '/admin/users/' . $member['id']);
            self::assertSame($expected, $this->users()->find((int) $member['id'])['suspended_until']);
        }
        $member = $this->makeUser();
        foreach (['2031-02-29T10:30', '2032-02-30T10:30', '2031-06-04T25:00', '2031-06-04T10:30Z', '0000-01-01T00:00', "  malformed <date>  "] as $bad) {
            $response = $this->post('/admin/users/' . $member['id'] . '/suspend', ['reason' => '  Keep this reason  ', 'until' => $bad]);
            $this->assertStatus(422, $response);
            self::assertStringContainsString('type="text" name="until"', $response->body());
            self::assertStringContainsString('value="' . htmlspecialchars($bad, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"', $response->body());
            $this->assertSeeText($response, 'value="  Keep this reason  "');
            self::assertSame('active', $this->users()->find((int) $member['id'])['status']);
        }
        $this->assertStatus(422, $this->post('/admin/users/' . $member['id'] . '/suspend', ['reason' => 'Array date', 'until' => ['bad']]));
    }

    public function test_duration_presets_and_bulk_validation_share_one_utc_expiry(): void
    {
        $this->actingAs($this->makeAdmin());
        $one = $this->makeUser();
        $two = $this->makeUser();
        $input = ['bulk_action' => 'suspend', 'selected' => [$one['id'], $two['id']], 'reason' => 'Shared reason', 'duration' => 'week', 'until' => '2035-12-31T23:59'];
        $start = time();
        $this->assertRedirect($this->post('/admin/users/bulk/apply', $input), '/admin/users');
        $first = (string) $this->users()->find((int) $one['id'])['suspended_until'];
        self::assertSame($first, $this->users()->find((int) $two['id'])['suspended_until']);
        self::assertGreaterThanOrEqual($start + 7 * 86400, strtotime($first . ' UTC'));
        self::assertLessThanOrEqual(time() + 7 * 86400, strtotime($first . ' UTC'));
        $three = $this->makeUser();
        $bad = $this->post('/admin/users/bulk/apply', ['bulk_action' => 'suspend', 'selected' => [$three['id']], 'reason' => 'Keep shared draft', 'until' => '2031-02-30T12:00']);
        $this->assertStatus(422, $bad);
        $this->assertSeeText($bad, 'Keep shared draft');
        $this->assertSeeText($bad, '2031-02-30T12:00');
        self::assertSame('active', $this->users()->find((int) $three['id'])['status']);
        $this->assertStatus(422, $this->post('/admin/users/' . $three['id'] . '/suspend', ['reason' => 'Unknown duration', 'duration' => ['week']]));
    }

    public function test_audit_names_changes_and_raw_unknown_actions_are_escaped_and_admin_only(): void
    {
        $actor = $this->makeAdmin(['username' => 'safeguardstaff', 'display_name' => '<Staff>']);
        $member = $this->makeUser(['username' => 'safeguardmember', 'display_name' => 'Member <one>']);
        $log = new ModerationLogRepository($this->db);
        $log->log(['actor_id' => (int) $actor['id'], 'action' => 'change_role', 'target_type' => 'user', 'target_id' => (int) $member['id'], 'before' => ['role' => 'user'], 'after' => ['role' => 'moderator']]);
        $log->log(['actor_id' => null, 'action' => 'future.action', 'target_type' => 'user', 'target_id' => 987654321, 'after' => ['unknown_field' => '<script>bad</script>']]);
        $this->actingAs($actor);
        $response = $this->get('/admin/audit');
        $this->assertStatus(200, $response);
        $this->assertSeeText($response, '&lt;Staff&gt; (@safeguardstaff)');
        $this->assertSeeText($response, 'Member &lt;one&gt; (@safeguardmember)');
        $this->assertSeeText($response, 'Changed role');
        $this->assertSeeText($response, 'Member → Moderator');
        $this->assertSeeText($response, 'System or deleted actor');
        $this->assertSeeText($response, 'Unavailable member #987654321');
        $this->assertSeeText($response, 'future.action');
        $this->assertSeeText($response, 'Raw details');
        self::assertStringNotContainsString('<script>bad', $response->body());
        self::assertStringNotContainsString('href="/admin/users/987654321"', $response->body());
        self::assertStringNotContainsString('safeguardstaff@example', $response->body());
        $this->actingAs($member);
        $this->assertStatus(403, $this->get('/admin/audit'));
    }
}
