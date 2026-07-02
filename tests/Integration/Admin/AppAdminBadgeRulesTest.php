<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\BadgeRepository;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppAdminBadgeRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_badge_rule_admin_routes_are_available_by_default_and_can_be_disabled(): void
    {
        // badge_rules graduated to default-on (GA 2026-07-02): with no features
        // override the admin surface is live; an operator can still take the whole
        // surface offline via the features setting (the rollback path).
        $admin = $this->makeAdmin(['username' => 'badge_rule_default_admin']);
        $this->actingAs($admin);

        $this->assertStatus(200, $this->get('/admin/badge-rules'));

        $this->setFlags(['badge_rules' => false]);
        $this->assertStatus(404, $this->get('/admin/badge-rules'));
        $this->assertStatus(404, $this->post('/admin/badge-rules', ['badge_id' => 1, 'rule_type' => 'post_count', 'threshold' => 1]));
    }

    public function test_badge_rules_flag_rollback_preserves_award_history(): void
    {
        // Operator rollback rehearsal: disabling the flag re-gates every route
        // (404) but touches no data — the award/revoke ledger in
        // badge_award_history survives, and re-enabling restores the surface with
        // the rule and its history intact.
        $admin = $this->makeAdmin(['username' => 'badge_rule_rollback_admin']);
        $eligible = $this->makeUser(['username' => 'badge_rule_rollback_user']);
        $board = $this->makeBoard($this->makeCategory('Badge Rollback'));
        $this->makeThread($board, $eligible, 'One', 'opening one');

        $badge = (new BadgeRepository($this->db))->findBySlug('conversation-starter');
        self::assertNotNull($badge);
        $this->actingAs($admin);

        // Create + enable + backfill through the default-on surface.
        $this->assertRedirectContains($this->post('/admin/badge-rules', [
            'badge_id' => (int) $badge['id'],
            'rule_type' => 'post_count',
            'threshold' => 1,
            'board_id' => (int) $board['id'],
        ]), '/admin/badge-rules');
        $ruleId = (int) $this->db->fetchValue('SELECT id FROM badge_rules ORDER BY id DESC LIMIT 1');
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $ruleId . '/enable'), '/admin/badge-rules');
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $ruleId . '/backfill'), '/admin/badge-rules');
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM badge_award_history WHERE badge_rule_id = ? AND action = 'award'",
            [$ruleId],
        ));

        // Roll back: disable the flag — the whole surface 404s.
        $this->setFlags(['badge_rules' => false]);
        $this->assertStatus(404, $this->get('/admin/badge-rules'));
        $this->assertStatus(404, $this->get('/admin/badge-rules/' . $ruleId . '/preview'));
        $this->assertStatus(404, $this->post('/admin/badge-rules/' . $ruleId . '/revoke'));

        // History is untouched by the toggle.
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM badge_award_history WHERE badge_rule_id = ? AND action = 'award'",
            [$ruleId],
        ));

        // Re-enable: the surface returns with the rule + history intact.
        $this->setFlags(['badge_rules' => true]);
        $this->assertStatus(200, $this->get('/admin/badge-rules'));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT is_enabled FROM badge_rules WHERE id = ?', [$ruleId]));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM badge_award_history WHERE badge_rule_id = ? AND action = 'award'",
            [$ruleId],
        ));
    }

    public function test_admin_previews_backfills_disables_and_revokes_post_count_rule(): void
    {
        $this->setFlags(['badge_rules' => true]);
        $admin = $this->makeAdmin(['username' => 'badge_rule_admin']);
        $eligible = $this->makeUser(['username' => 'badge_rule_eligible']);
        $ineligible = $this->makeUser(['username' => 'badge_rule_ineligible']);
        $board = $this->makeBoard($this->makeCategory('Badge Rules'));
        $this->makeThread($board, $eligible, 'One', 'opening one');
        $this->makeThread($board, $eligible, 'Two', 'opening two');
        $this->makeThread($board, $ineligible, 'Only one', 'opening one');

        $badge = (new BadgeRepository($this->db))->findBySlug('conversation-starter');
        self::assertNotNull($badge);
        $this->actingAs($admin);

        $create = $this->post('/admin/badge-rules', [
            'badge_id' => (int) $badge['id'],
            'rule_type' => 'post_count',
            'threshold' => 2,
            'board_id' => (int) $board['id'],
        ]);
        $this->assertRedirectContains($create, '/admin/badge-rules');
        $ruleId = (int) $this->db->fetchValue('SELECT id FROM badge_rules ORDER BY id DESC LIMIT 1');

        $preview = $this->get('/admin/badge-rules/' . $ruleId . '/preview');
        $this->assertStatus(200, $preview);
        self::assertStringContainsString('badge_rule_eligible', $preview->body());
        self::assertStringNotContainsString('badge_rule_ineligible', $preview->body());

        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $ruleId . '/enable'), '/admin/badge-rules');
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $ruleId . '/backfill'), '/admin/badge-rules');

        $badges = new BadgeRepository($this->db);
        self::assertTrue($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));
        self::assertFalse($badges->hasBadgeSlug((int) $ineligible['id'], 'conversation-starter'));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM badge_award_history WHERE badge_rule_id = ? AND action = 'award'",
            [$ruleId],
        ));

        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $ruleId . '/disable'), '/admin/badge-rules');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_enabled FROM badge_rules WHERE id = ?', [$ruleId]));

        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $ruleId . '/revoke'), '/admin/badge-rules');
        self::assertFalse($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM badge_award_history WHERE badge_rule_id = ? AND action = 'revoke'",
            [$ruleId],
        ));
    }

    public function test_revoking_one_rule_preserves_badge_when_another_enabled_rule_still_qualifies_user(): void
    {
        $admin = $this->makeAdmin(['username' => 'badge_rule_overlap_admin']);
        $eligible = $this->makeUser(['username' => 'badge_rule_overlap_user']);
        $board = $this->makeBoard($this->makeCategory('Badge Rule Overlap'));
        $this->makeThread($board, $eligible, 'Overlap one', 'opening one');
        $this->makeThread($board, $eligible, 'Overlap two', 'opening two');

        $badge = (new BadgeRepository($this->db))->findBySlug('conversation-starter');
        self::assertNotNull($badge);
        $this->actingAs($admin);

        $this->assertRedirectContains($this->post('/admin/badge-rules', [
            'badge_id' => (int) $badge['id'],
            'rule_type' => 'post_count',
            'threshold' => 2,
            'board_id' => (int) $board['id'],
        ]), '/admin/badge-rules');
        $originalRuleId = (int) $this->db->fetchValue('SELECT id FROM badge_rules ORDER BY id DESC LIMIT 1');
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $originalRuleId . '/enable'), '/admin/badge-rules');
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $originalRuleId . '/backfill'), '/admin/badge-rules');

        $badges = new BadgeRepository($this->db);
        self::assertTrue($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));

        $this->assertRedirectContains($this->post('/admin/badge-rules', [
            'badge_id' => (int) $badge['id'],
            'rule_type' => 'thread_count',
            'threshold' => 1,
            'board_id' => (int) $board['id'],
        ]), '/admin/badge-rules');
        $backupRuleId = (int) $this->db->fetchValue('SELECT id FROM badge_rules ORDER BY id DESC LIMIT 1');
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $backupRuleId . '/enable'), '/admin/badge-rules');

        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $originalRuleId . '/revoke'), '/admin/badge-rules');

        self::assertTrue($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'), 'another enabled rule still qualifies this user for the badge');
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM badge_award_history WHERE badge_rule_id = ? AND action = 'revoke'",
            [$originalRuleId],
        ));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM badge_award_history WHERE badge_rule_id = ? AND action = 'award'",
            [$backupRuleId],
        ), 'preserving the visible badge must transfer active award ownership to the qualifying replacement rule');
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM user_badges WHERE user_id = ? AND badge_id = ?',
            [(int) $eligible['id'], (int) $badge['id']],
        ));

        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $backupRuleId . '/revoke'), '/admin/badge-rules');

        self::assertFalse($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM badge_award_history WHERE badge_rule_id = ? AND action = 'revoke'",
            [$backupRuleId],
        ));
    }

    public function test_revoking_rule_preserves_badge_when_replacement_rule_had_prior_revoked_history(): void
    {
        $admin = $this->makeAdmin(['username' => 'badge_rule_reaward_admin']);
        $eligible = $this->makeUser(['username' => 'badge_rule_reaward_user']);
        $board = $this->makeBoard($this->makeCategory('Badge Rule Reaward'));
        $this->makeThread($board, $eligible, 'Reaward one', 'opening one');
        $this->makeThread($board, $eligible, 'Reaward two', 'opening two');

        $badge = (new BadgeRepository($this->db))->findBySlug('conversation-starter');
        self::assertNotNull($badge);
        $badges = new BadgeRepository($this->db);
        $this->actingAs($admin);

        $this->assertRedirectContains($this->post('/admin/badge-rules', [
            'badge_id' => (int) $badge['id'],
            'rule_type' => 'thread_count',
            'threshold' => 1,
            'board_id' => (int) $board['id'],
        ]), '/admin/badge-rules');
        $replacementRuleId = (int) $this->db->fetchValue('SELECT id FROM badge_rules ORDER BY id DESC LIMIT 1');
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $replacementRuleId . '/enable'), '/admin/badge-rules');
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $replacementRuleId . '/backfill'), '/admin/badge-rules');
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $replacementRuleId . '/revoke'), '/admin/badge-rules');
        self::assertFalse($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));

        $this->assertRedirectContains($this->post('/admin/badge-rules', [
            'badge_id' => (int) $badge['id'],
            'rule_type' => 'post_count',
            'threshold' => 2,
            'board_id' => (int) $board['id'],
        ]), '/admin/badge-rules');
        $activeRuleId = (int) $this->db->fetchValue('SELECT id FROM badge_rules ORDER BY id DESC LIMIT 1');
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $activeRuleId . '/enable'), '/admin/badge-rules');
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $activeRuleId . '/backfill'), '/admin/badge-rules');
        self::assertTrue($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));

        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $activeRuleId . '/revoke'), '/admin/badge-rules');

        self::assertTrue($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'), 'a qualifying replacement rule must keep the badge even when its prior award was revoked');
        self::assertSame(2, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM badge_award_history WHERE badge_rule_id = ? AND action = 'award'",
            [$replacementRuleId],
        ), 'preserving ownership after a prior revoke records a fresh active award cycle');
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM badge_award_history WHERE badge_rule_id = ? AND action = 'revoke'",
            [$replacementRuleId],
        ));
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM user_badges WHERE user_id = ? AND badge_id = ?',
            [(int) $eligible['id'], (int) $badge['id']],
        ));
    }

    public function test_rule_can_be_backfilled_again_after_revoke_and_revoked_again(): void
    {
        $admin = $this->makeAdmin(['username' => 'badge_rule_rebackfill_admin']);
        $eligible = $this->makeUser(['username' => 'badge_rule_rebackfill_user']);
        $board = $this->makeBoard($this->makeCategory('Badge Rule Rebackfill'));
        $this->makeThread($board, $eligible, 'Rebackfill one', 'opening one');

        $badge = (new BadgeRepository($this->db))->findBySlug('conversation-starter');
        self::assertNotNull($badge);
        $badges = new BadgeRepository($this->db);
        $this->actingAs($admin);

        $this->assertRedirectContains($this->post('/admin/badge-rules', [
            'badge_id' => (int) $badge['id'],
            'rule_type' => 'thread_count',
            'threshold' => 1,
            'board_id' => (int) $board['id'],
        ]), '/admin/badge-rules');
        $ruleId = (int) $this->db->fetchValue('SELECT id FROM badge_rules ORDER BY id DESC LIMIT 1');
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $ruleId . '/enable'), '/admin/badge-rules');

        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $ruleId . '/backfill'), '/admin/badge-rules');
        self::assertTrue($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $ruleId . '/revoke'), '/admin/badge-rules');
        self::assertFalse($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));

        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $ruleId . '/backfill'), '/admin/badge-rules');
        self::assertTrue($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $ruleId . '/revoke'), '/admin/badge-rules');

        self::assertFalse($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));
        self::assertSame(2, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM badge_award_history WHERE badge_rule_id = ? AND action = 'award'",
            [$ruleId],
        ));
        self::assertSame(2, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM badge_award_history WHERE badge_rule_id = ? AND action = 'revoke'",
            [$ruleId],
        ));
    }

    public function test_rule_creation_rejects_unknown_vocabulary(): void
    {
        $this->setFlags(['badge_rules' => true]);
        $admin = $this->makeAdmin(['username' => 'badge_rule_reject_admin']);
        $badge = (new BadgeRepository($this->db))->findBySlug('conversation-starter');
        self::assertNotNull($badge);
        $this->actingAs($admin);

        $this->assertStatus(422, $this->post('/admin/badge-rules', [
            'badge_id' => (int) $badge['id'],
            'rule_type' => 'sql',
            'threshold' => 1,
        ]));
    }
}
