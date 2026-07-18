<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\ModerationAppealRepository;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * Console IA per ADMIN.md §9.2 (round-2 audit findings 12-13): the admin nav is
 * grouped (Dashboard · Moderation · Content · People · Appearance ·
 * Notifications · Integrations · Settings) with real Moderation entries, the
 * dashboard carries an Appeals card, and the two previously orphaned consoles
 * (/admin/roles/simulator, /admin/packages/security) have inbound links.
 */
final class AppAdminNavIaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_admin_nav_is_grouped_per_spec_with_moderation_entries(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'ia_admin']));
        $body = $this->get('/admin')->body();

        foreach (['Moderation', 'Content', 'People', 'Appearance', 'Notifications', 'Integrations', 'Settings'] as $label) {
            self::assertStringContainsString('class="admin-nav-group-title">' . $label, $body, "nav group '$label' missing");
        }
        self::assertStringContainsString('href="/mod/reports"', $body);
        self::assertStringContainsString('href="/mod/approvals"', $body);
        self::assertStringContainsString('href="/mod/appeals"', $body);
    }

    public function test_appeals_nav_entry_goes_disabled_when_the_flag_is_off(): void
    {
        (new SettingRepository($this->db))->set('features', ['appeals' => false]);
        $this->actingAs($this->makeAdmin(['username' => 'ia_admin_flags']));
        $body = $this->get('/admin')->body();
        self::assertStringNotContainsString('href="/mod/appeals"', $body);
        self::assertStringContainsString('Appeals', $body); // disabled span, not removed
    }

    public function test_appeals_dashboard_card_counts_open_appeals_and_follows_the_flag(): void
    {
        $appellant = (int) $this->makeUser(['username' => 'ia_appellant'])['id'];
        (new ModerationAppealRepository($this->db))->create([
            'appellant_id' => $appellant,
            'target_type' => 'post',
            'target_id' => 12345,
            'moderation_log_id' => null,
            'original_action' => 'delete_post',
            'target_summary' => 'removed reply',
            'reason' => 'please look again',
        ]);
        $this->actingAs($this->makeAdmin(['username' => 'ia_cards_admin']));

        $body = $this->get('/admin')->body();
        self::assertStringContainsString('Appeals', $body);
        self::assertStringContainsString('href="/mod/appeals"', $body);
        self::assertStringContainsString('Open moderation appeals', $body);

        (new SettingRepository($this->db))->set('features', ['appeals' => false]);
        self::assertStringNotContainsString('href="/mod/appeals"', $this->get('/admin')->body());
    }

    public function test_parent_pages_link_their_orphan_consoles(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'ia_orphans']));
        self::assertStringContainsString('href="/admin/roles/simulator"', $this->get('/admin/roles')->body());
        self::assertStringContainsString('href="/admin/packages/security"', $this->get('/admin/packages')->body());
    }
}
