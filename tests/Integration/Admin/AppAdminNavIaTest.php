<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\ModerationAppealRepository;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * Console IA per ADMIN.md §9.2 as amended by ADR 0024: the grouped 224px rail is
 * replaced by an eleven-area horizontal tier (Overview · Moderation · Content ·
 * People · Members · Appearance · Notifications · Integrations · Packages ·
 * Features · Settings) over a per-area tab strip. ADR 0023's round-2 findings
 * other than the IA clause survive the change and are still asserted here: real
 * Moderation entries, the Appeals dashboard card, and inbound links for the two
 * previously orphaned consoles (/admin/roles/simulator, /admin/packages/security).
 */
final class AppAdminNavIaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_admin_tier_lists_every_area_in_console_order(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'ia_admin']));
        $body = $this->get('/admin')->body();

        self::assertSame(
            1,
            preg_match('~<nav class="admin-tier"[^>]*>(?<tier>.*?)</nav>~s', $body, $matches),
            'the area tier is missing',
        );
        $tier = $matches['tier'];

        $cursor = -1;
        foreach ([
            'Overview', 'Moderation', 'Content', 'People', 'Members', 'Appearance',
            'Notifications', 'Integrations', 'Packages', 'Features', 'Settings',
        ] as $label) {
            $next = strpos($tier, '>' . $label . '<');
            self::assertNotFalse($next, "tier area '$label' missing");
            self::assertGreaterThan($cursor, $next, "tier order drifted at '$label'");
            $cursor = $next;
        }

        self::assertStringContainsString('aria-label="Admin areas"', $body);
        // The active area is a span, not an hrefless anchor: an <a> without href
        // is not focusable and reads as a generic element to assistive tech.
        self::assertMatchesRegularExpression(
            '~<span class="admin-tier-item is-active" aria-current="page">Overview</span>~',
            $tier,
        );
    }

    public function test_moderation_area_carries_the_queue_tabs(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'ia_admin_mod']));
        $body = $this->get('/admin/moderation')->body();

        self::assertStringContainsString('aria-label="Moderation sections"', $body);
        self::assertStringContainsString('href="/mod/reports"', $body);
        self::assertStringContainsString('href="/mod/approvals"', $body);
        self::assertStringContainsString('href="/mod/appeals"', $body);
    }

    public function test_appeals_tab_goes_disabled_when_the_flag_is_off(): void
    {
        (new SettingRepository($this->db))->set('features', ['appeals' => false]);
        $this->actingAs($this->makeAdmin(['username' => 'ia_admin_flags']));
        $body = $this->get('/admin/moderation')->body();
        self::assertStringNotContainsString('href="/mod/appeals"', $body);
        // Disabled span, not removed: the operator still learns the surface exists.
        self::assertStringContainsString('Appeals', $body);
        self::assertStringContainsString('Disabled until the feature flag is enabled', $body);
    }

    public function test_a_tier_area_goes_disabled_only_when_every_tab_is_dark(): void
    {
        (new SettingRepository($this->db))->set('features', [
            'package_registry' => false,
            'server_extensions' => false,
        ]);
        $this->actingAs($this->makeAdmin(['username' => 'ia_admin_area_dark']));
        $body = $this->get('/admin')->body();

        // Every Packages tab is dark, so the area itself must not link anywhere.
        self::assertStringNotContainsString('href="/admin/packages"', $body);
        self::assertStringContainsString('data-destination="/admin/packages"', $body);
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
