<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Core\Response;
use App\Repository\BadgeRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
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
        $this->assertRedirectContains($this->confirmedRuleAction($ruleId, 'backfill'), '/admin/badge-rules');
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

    public function test_rule_row_actions_render_in_the_designed_order(): void
    {
        // The design fixes the action cluster at Preview · Backfill · {toggle} ·
        // Revoke awards, with the enable/disable branch collapsed so both variants
        // land in the same slot. Nothing else guards the order, and the previous
        // markup emitted Enable before Backfill.
        $this->setFlags(['badge_rules' => true]);
        $admin = $this->makeAdmin(['username' => 'badge_rule_order_admin']);
        $this->actingAs($admin);

        $badges = new BadgeRepository($this->db);
        $first = $badges->findBySlug('conversation-starter');
        $second = $badges->findBySlug('first-post');
        self::assertNotNull($first);
        self::assertNotNull($second);

        foreach ([$first, $second] as $badge) {
            $this->assertRedirectContains($this->post('/admin/badge-rules', [
                'badge_id' => (int) $badge['id'],
                'rule_type' => 'post_count',
                'threshold' => 2,
            ]), '/admin/badge-rules');
        }
        // Rules are created inert; enable exactly one so both branches are covered.
        $enabledId = (int) $this->db->fetchValue('SELECT id FROM badge_rules ORDER BY id ASC LIMIT 1');
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $enabledId . '/enable', []), '/admin/badge-rules');

        $body = $this->get('/admin/badge-rules')->body();
        $listAt = strpos($body, '<ul class="link-list features-rule-list">');
        self::assertNotFalse($listAt, 'the rules list should render');
        $list = substr($body, $listAt, (int) strpos($body, '</ul>', $listAt) - $listAt);
        $rows = array_slice(explode('<li>', $list), 1);
        self::assertCount(2, $rows, 'both rules should be listed');

        $sawEnabled = false;
        $sawDisabled = false;
        foreach ($rows as $row) {
            $toggle = str_contains($row, '/disable"') ? 'Disable' : 'Enable';
            $toggle === 'Disable' ? $sawEnabled = true : $sawDisabled = true;

            $preview = strpos($row, '>Preview<');
            $backfill = strpos($row, '>Backfill<');
            $toggleAt = strpos($row, '>' . $toggle . '<');
            $revoke = strpos($row, '>Revoke awards<');

            self::assertNotFalse($preview);
            self::assertNotFalse($backfill);
            self::assertNotFalse($toggleAt);
            self::assertNotFalse($revoke);
            self::assertLessThan($backfill, $preview);
            self::assertLessThan($toggleAt, $backfill);
            self::assertLessThan($revoke, $toggleAt);
        }
        self::assertTrue($sawEnabled, 'one row should offer Disable');
        self::assertTrue($sawDisabled, 'one row should offer Enable');
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
        $this->assertRedirectContains($this->confirmedRuleAction($ruleId, 'backfill'), '/admin/badge-rules');

        $badges = new BadgeRepository($this->db);
        self::assertTrue($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));
        self::assertFalse($badges->hasBadgeSlug((int) $ineligible['id'], 'conversation-starter'));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM badge_award_history WHERE badge_rule_id = ? AND action = 'award'",
            [$ruleId],
        ));

        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $ruleId . '/disable'), '/admin/badge-rules');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_enabled FROM badge_rules WHERE id = ?', [$ruleId]));

        $this->assertRedirectContains($this->confirmedRuleAction($ruleId, 'revoke'), '/admin/badge-rules');
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
        $this->assertRedirectContains($this->confirmedRuleAction($originalRuleId, 'backfill'), '/admin/badge-rules');

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

        $this->assertRedirectContains($this->confirmedRuleAction($originalRuleId, 'revoke'), '/admin/badge-rules');

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

        $this->assertRedirectContains($this->confirmedRuleAction($backupRuleId, 'revoke'), '/admin/badge-rules');

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
        $this->assertRedirectContains($this->confirmedRuleAction($replacementRuleId, 'backfill'), '/admin/badge-rules');
        $this->assertRedirectContains($this->confirmedRuleAction($replacementRuleId, 'revoke'), '/admin/badge-rules');
        self::assertFalse($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));

        $this->assertRedirectContains($this->post('/admin/badge-rules', [
            'badge_id' => (int) $badge['id'],
            'rule_type' => 'post_count',
            'threshold' => 2,
            'board_id' => (int) $board['id'],
        ]), '/admin/badge-rules');
        $activeRuleId = (int) $this->db->fetchValue('SELECT id FROM badge_rules ORDER BY id DESC LIMIT 1');
        $this->assertRedirectContains($this->post('/admin/badge-rules/' . $activeRuleId . '/enable'), '/admin/badge-rules');
        $this->assertRedirectContains($this->confirmedRuleAction($activeRuleId, 'backfill'), '/admin/badge-rules');
        self::assertTrue($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));

        $this->assertRedirectContains($this->confirmedRuleAction($activeRuleId, 'revoke'), '/admin/badge-rules');

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

        $this->assertRedirectContains($this->confirmedRuleAction($ruleId, 'backfill'), '/admin/badge-rules');
        self::assertTrue($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));
        $this->assertRedirectContains($this->confirmedRuleAction($ruleId, 'revoke'), '/admin/badge-rules');
        self::assertFalse($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));

        $this->assertRedirectContains($this->confirmedRuleAction($ruleId, 'backfill'), '/admin/badge-rules');
        self::assertTrue($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));
        $this->assertRedirectContains($this->confirmedRuleAction($ruleId, 'revoke'), '/admin/badge-rules');

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

    public function test_backfill_requires_typed_confirmation_and_preserves_wrong_input_without_awarding(): void
    {
        $admin = $this->makeAdmin(['username' => 'badge_confirm_backfill_admin']);
        $eligible = $this->makeUser(['username' => 'badge_confirm_backfill_user']);
        $this->db->run('UPDATE users SET reputation = 5 WHERE id = ?', [(int) $eligible['id']]);
        $badges = new BadgeRepository($this->db);
        $badge = $badges->findBySlug('conversation-starter');
        self::assertNotNull($badge);
        $ruleId = $badges->createRule((int) $badge['id'], 'reputation', 5, null, (int) $admin['id']);
        $this->actingAs($admin);

        $this->assertStatus(422, $this->post('/admin/badge-rules/' . $ruleId . '/backfill'));
        $token = $this->impactToken($this->get('/admin/badge-rules/' . $ruleId . '/backfill'));
        $this->assertStatus(422, $this->post('/admin/badge-rules/' . $ruleId . '/backfill', ['confirm' => 'BACKFILL ' . $ruleId]));
        $invalid = $this->post('/admin/badge-rules/' . $ruleId . '/backfill', ['confirm' => 'BACKFILL wrong', 'impact_token' => $token]);
        $this->assertStatus(422, $invalid);
        self::assertStringContainsString('value="BACKFILL wrong"', $invalid->body());
        self::assertFalse($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));
        self::assertSame([], $badges->activeRuleAwards($ruleId));
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'badge_rule.backfill' AND target_id = ?", [$ruleId]));
    }

    public function test_revoke_requires_typed_confirmation_and_preserves_awards_without_it(): void
    {
        $admin = $this->makeAdmin(['username' => 'badge_confirm_revoke_admin']);
        $eligible = $this->makeUser(['username' => 'badge_confirm_revoke_user']);
        $badges = new BadgeRepository($this->db);
        $badge = $badges->findBySlug('conversation-starter');
        self::assertNotNull($badge);
        $ruleId = $badges->createRule((int) $badge['id'], 'reputation', 5, null, (int) $admin['id']);
        $badges->awardRuleBadge((int) $eligible['id'], (int) $badge['id'], $ruleId, (int) $admin['id'], 'rule:' . $ruleId . ':v1:user:' . (int) $eligible['id']);
        $this->actingAs($admin);

        $this->assertStatus(422, $this->post('/admin/badge-rules/' . $ruleId . '/revoke'));
        $token = $this->impactToken($this->get('/admin/badge-rules/' . $ruleId . '/revoke'));
        $this->assertStatus(422, $this->post('/admin/badge-rules/' . $ruleId . '/revoke', ['confirm' => 'REVOKE ' . $ruleId]));
        $invalid = $this->post('/admin/badge-rules/' . $ruleId . '/revoke', ['confirm' => 'REVOKE wrong', 'impact_token' => $token]);
        $this->assertStatus(422, $invalid);
        self::assertStringContainsString('value="REVOKE wrong"', $invalid->body());
        self::assertTrue($badges->hasBadgeSlug((int) $eligible['id'], 'conversation-starter'));
        self::assertCount(1, $badges->activeRuleAwards($ruleId));
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'badge_rule.revoke' AND target_id = ?", [$ruleId]));
    }

    public function test_backfill_confirmation_shows_processing_batch_and_rejects_changed_eligibility(): void
    {
        $admin = $this->makeAdmin(['username' => 'badge_confirm_batch_admin']);
        $users = new UserRepository($this->db);
        for ($i = 0; $i < 1001; $i++) {
            $users->create(['username' => 'badge_batch_' . $i, 'email' => 'badge_batch_' . $i . '@example.test', 'password_hash' => null, 'display_name' => null]);
        }
        $this->db->run('UPDATE users SET reputation = 5 WHERE username LIKE ?', ['badge_batch_%']);
        $badges = new BadgeRepository($this->db);
        $badge = $badges->findBySlug('conversation-starter');
        self::assertNotNull($badge);
        $ruleId = $badges->createRule((int) $badge['id'], 'reputation', 5, null, (int) $admin['id']);
        $this->actingAs($admin);
        $preview = $this->get('/admin/badge-rules/' . $ruleId . '/preview');
        self::assertStringContainsString('up to 100 members', $preview->body());
        self::assertStringContainsString('up to 1,000 members', $preview->body());

        $confirm = $this->get('/admin/badge-rules/' . $ruleId . '/backfill');
        $this->assertStatus(200, $confirm);
        self::assertStringContainsString('1,000 members in this batch', $confirm->body());
        self::assertStringContainsString('More members qualify', $confirm->body());
        $token = $this->impactToken($confirm);
        $this->db->run('UPDATE users SET reputation = 0 WHERE username = ?', ['badge_batch_0']);

        $stale = $this->post('/admin/badge-rules/' . $ruleId . '/backfill', ['confirm' => 'BACKFILL ' . $ruleId, 'impact_token' => $token]);
        $this->assertStatus(422, $stale);
        self::assertStringContainsString('impact has changed', $stale->body());
        self::assertSame([], $badges->activeRuleAwards($ruleId));
        $this->assertRedirectContains($this->confirmedRuleAction($ruleId, 'backfill'), '/admin/badge-rules');
        self::assertCount(1000, $badges->activeRuleAwards($ruleId));
    }

    public function test_revoke_rejects_a_changed_award_batch_without_removing_either_badge(): void
    {
        $admin = $this->makeAdmin(['username' => 'revoke_admin']);
        $first = $this->makeUser(['username' => 'revoke_first']);
        $second = $this->makeUser(['username' => 'revoke_second']);
        $badges = new BadgeRepository($this->db);
        $badge = $badges->findBySlug('conversation-starter');
        self::assertNotNull($badge);
        $ruleId = $badges->createRule((int) $badge['id'], 'reputation', 5, null, (int) $admin['id']);
        $badges->awardRuleBadge((int) $first['id'], (int) $badge['id'], $ruleId, (int) $admin['id'], 'rule:' . $ruleId . ':v1:user:' . (int) $first['id']);
        $this->actingAs($admin);
        $confirm = $this->get('/admin/badge-rules/' . $ruleId . '/revoke');
        $this->assertStatus(200, $confirm);
        self::assertStringContainsString('Active rule awards to revoke</dt><dd>1</dd>', $confirm->body());
        self::assertCount(1, $badges->activeRuleAwards($ruleId), 'GET confirmation does not revoke awards');
        $token = $this->impactToken($confirm);
        $badges->awardRuleBadge((int) $second['id'], (int) $badge['id'], $ruleId, (int) $admin['id'], 'rule:' . $ruleId . ':v1:user:' . (int) $second['id']);

        $stale = $this->post('/admin/badge-rules/' . $ruleId . '/revoke', ['confirm' => 'REVOKE ' . $ruleId, 'impact_token' => $token]);
        $this->assertStatus(422, $stale);
        self::assertStringContainsString('Active rule awards to revoke</dt><dd>2</dd>', $stale->body());
        self::assertStringContainsString('value="REVOKE ' . $ruleId . '"', $stale->body());
        self::assertTrue($badges->hasBadgeSlug((int) $first['id'], 'conversation-starter'));
        self::assertTrue($badges->hasBadgeSlug((int) $second['id'], 'conversation-starter'));
        $this->assertRedirectContains($this->confirmedRuleAction($ruleId, 'revoke'), '/admin/badge-rules');
        self::assertFalse($badges->hasBadgeSlug((int) $first['id'], 'conversation-starter'));
        self::assertFalse($badges->hasBadgeSlug((int) $second['id'], 'conversation-starter'));
    }

    public function test_confirmation_pages_and_actions_keep_flag_admin_and_account_write_gates(): void
    {
        $admin = $this->makeAdmin(['username' => 'badge_confirm_gate_admin']);
        $member = $this->makeUser(['username' => 'badge_confirm_gate_member']);
        $badges = new BadgeRepository($this->db);
        $badge = $badges->findBySlug('conversation-starter');
        self::assertNotNull($badge);
        $ruleId = $badges->createRule((int) $badge['id'], 'reputation', 5, null, (int) $admin['id']);
        $this->actingAs($member);
        foreach (['backfill', 'revoke'] as $action) {
            $this->assertStatus(403, $this->get('/admin/badge-rules/' . $ruleId . '/' . $action));
            $this->assertStatus(403, $this->post('/admin/badge-rules/' . $ruleId . '/' . $action));
        }
        $this->actingAs($admin);
        $this->setFlags(['badge_rules' => false]);
        foreach (['backfill', 'revoke'] as $action) {
            $this->assertStatus(404, $this->get('/admin/badge-rules/' . $ruleId . '/' . $action));
            $this->assertStatus(404, $this->post('/admin/badge-rules/' . $ruleId . '/' . $action));
        }
        $this->setFlags(['badge_rules' => true]);
        $this->db->run("UPDATE users SET status = 'suspended' WHERE id = ?", [(int) $admin['id']]);
        foreach (['backfill', 'revoke'] as $action) {
            $this->assertStatus(403, $this->get('/admin/badge-rules/' . $ruleId . '/' . $action));
            $this->assertStatus(403, $this->post('/admin/badge-rules/' . $ruleId . '/' . $action));
        }
    }

    private function confirmedRuleAction(int $ruleId, string $action): Response
    {
        $confirm = $this->get('/admin/badge-rules/' . $ruleId . '/' . $action);
        $this->assertStatus(200, $confirm);
        return $this->post('/admin/badge-rules/' . $ruleId . '/' . $action, [
            'confirm' => strtoupper($action) . ' ' . $ruleId,
            'impact_token' => $this->impactToken($confirm),
        ]);
    }

    private function impactToken(Response $response): string
    {
        self::assertSame(1, preg_match('/name="impact_token" value="([a-f0-9]+)"/', $response->body(), $matches));
        return $matches[1];
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
