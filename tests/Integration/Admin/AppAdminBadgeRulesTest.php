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

    public function test_badge_rule_admin_routes_are_dark_by_default(): void
    {
        $admin = $this->makeAdmin(['username' => 'badge_rule_dark_admin']);
        $this->actingAs($admin);

        $this->assertStatus(404, $this->get('/admin/badge-rules'));
        $this->assertStatus(404, $this->post('/admin/badge-rules', ['badge_id' => 1, 'rule_type' => 'post_count', 'threshold' => 1]));
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
