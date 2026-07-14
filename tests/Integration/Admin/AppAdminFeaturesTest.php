<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppAdminFeaturesTest extends TestCase
{
    /** @param array<string,mixed> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_admin_can_review_declared_feature_flag_inventory(): void
    {
        $this->setFlags([
            'server_extensions' => true,
            'email' => false,
            'unknown_flag' => true,
        ]);
        $this->actingAs($this->makeAdmin(['username' => 'feature-admin']));

        $page = $this->get('/admin/features');

        $this->assertStatus(200, $page);
        self::assertStringContainsString('Feature flags', $page->body());
        self::assertStringContainsString('57 declared', $page->body());
        self::assertStringContainsString('49 default-on', $page->body());
        self::assertStringContainsString('8 default-dark', $page->body());
        self::assertStringContainsString('<code>server_extensions</code>', $page->body());
        self::assertStringContainsString('Phase 5 Gate B', $page->body());
        self::assertStringContainsString('Effective on', $page->body());
        self::assertStringContainsString('Override on', $page->body());
        self::assertStringContainsString('<code>email</code>', $page->body());
        self::assertStringContainsString('Override off', $page->body());
        self::assertStringContainsString('Unknown overrides', $page->body());
        self::assertStringContainsString('<code>unknown_flag</code>', $page->body());
    }

    public function test_console_override_column_matches_runtime_for_string_shapes(): void
    {
        // A hand-written {"passkeys":"false"} must read as a rollback at runtime,
        // and the read-only console must report the same state — not the raw
        // (bool)-truthy reading that would show "Override on" for a paused flag.
        $this->setFlags(['passkeys' => 'false']);
        $this->actingAs($this->makeAdmin(['username' => 'string-shape-admin']));

        $page = $this->get('/admin/features');

        $this->assertStatus(200, $page);
        self::assertStringContainsString('Override off', $page->body());
        self::assertStringNotContainsString('Override on', $page->body());
    }

    public function test_console_warns_when_the_features_setting_is_not_a_json_object(): void
    {
        // A double-encoded features value silently discards every override; the
        // one place an operator looks during an incident must say so instead of
        // rendering "No override" everywhere like a clean install.
        (new SettingRepository($this->db))->set('features', 'not-an-object');
        $this->actingAs($this->makeAdmin(['username' => 'corrupt-blob-admin']));

        $page = $this->get('/admin/features');

        $this->assertStatus(200, $page);
        self::assertStringContainsString('not a JSON object', $page->body());
        self::assertStringContainsString('overrides are being ignored', $page->body());
    }

    public function test_feature_inventory_classifies_readiness_and_links_actionable_surfaces(): void
    {
        // Pristine defaults: no features override, shadow resolver posture,
        // no GIPHY key — the 2026-07-13 readiness audit posture.
        $this->actingAs($this->makeAdmin(['username' => 'readiness-admin']));

        $page = $this->get('/admin/features');

        $this->assertStatus(200, $page);
        $body = $page->body();
        self::assertStringContainsString('Readiness / next step', $body);

        // The six categories, each anchored by its row-specific finding.
        self::assertStringContainsString('Ready for acceptance', $body);
        self::assertStringContainsString('Member journey verified end-to-end', $body); // group_dms
        self::assertStringContainsString('Missing user UI', $body);
        self::assertStringContainsString('no member file chooser', $body); // expanded_files
        self::assertStringContainsString('Missing admin operations', $body);
        self::assertStringContainsString('GET /admin/link-previews does not exist', $body); // link_previews
        self::assertStringContainsString('Safety-blocked', $body);
        self::assertStringContainsString('Theme safe mode does not suppress', $body); // custom_css
        self::assertStringContainsString('Operational configuration required', $body);
        self::assertStringContainsString('Resolver posture is', $body); // capabilities still in shadow
        self::assertStringContainsString('giphy_public_key is unset', $body); // slash_giphy without a key
        self::assertStringContainsString('Reserved (ADR 0018)', $body);
        self::assertStringContainsString('stays dark until its workstream lands its own release evidence', $body);

        // Actionable rows link to their real surfaces…
        self::assertStringContainsString('<a href="/mod/reports">Report queue</a>', $body);
        self::assertStringContainsString('<a href="/admin/roles">Roles &amp; resolver posture</a>', $body);
        self::assertStringContainsString('<a href="/admin/branding">Custom CSS editor</a>', $body);
        // …shipped default-on flags carry an Operations link to their console…
        self::assertStringContainsString('<a href="/admin/webhooks">Operations</a>', $body);
        self::assertStringContainsString('<a href="/admin/thread-intelligence">Operations</a>', $body);
        // …but a console that 404s while its flag is dark is never linked.
        self::assertStringNotContainsString('href="/admin/extensions"', $body);
    }

    public function test_operational_configuration_rows_clear_when_the_step_is_done(): void
    {
        // The two "Operational configuration required" rows are computed live:
        // a stored GIPHY key and an enforce resolver posture must clear them,
        // and readiness links must never point at a surface that would 404
        // (moderation_queue / branding rolled back drop the link, not the badge).
        (new SettingRepository($this->db))->set('giphy_public_key', 'pk-public-test');
        $this->withCapabilitiesEnforced(['moderation_queue' => false, 'branding' => false]);
        $this->actingAs($this->makeAdmin(['username' => 'dormant-clear-admin']));

        $page = $this->get('/admin/features');

        $this->assertStatus(200, $page);
        $body = $page->body();
        self::assertStringNotContainsString('Resolver posture is', $body);
        self::assertStringNotContainsString('giphy_public_key is unset', $body);
        self::assertStringContainsString('Ready for acceptance', $body);
        self::assertStringContainsString('Safety-blocked', $body);
        self::assertStringNotContainsString('href="/mod/reports"', $body);
        self::assertStringNotContainsString('href="/admin/branding"', $body);
    }

    public function test_feature_flag_inventory_is_admin_only_but_not_feature_gated(): void
    {
        $this->makeAdmin();
        $this->actingAs($this->makeUser(['username' => 'not-feature-admin']));
        $this->assertStatus(403, $this->get('/admin/features'));

        $allOff = array_fill_keys([
            'engagement', 'notifications', 'email', 'mentions', 'search', 'dms',
            'moderation_queue', 'community', 'oauth', 'presence', 'announcements',
            'rich_composer', 'wysiwyg_composer', 'drafts', 'server_drafts',
            'uploads', 'anti_abuse', 'appeals', 'branding', 'custom_css', 'seo',
            'product_tour', 'topic_workflow', 'group_dms', 'tags',
            'expanded_feeds', 'reputation_ledger', 'badge_rules',
            'community_memory', 'content_references', 'link_previews',
            'expanded_files', 'polls', 'custom_emoji', 'slash_giphy',
            'split_merge', 'profile_media', 'board_folders', 'bookmark_folders',
            'saved_feeds', 'custom_profile_fields', 'account_lifecycle',
            'automated_context', 'package_registry', 'package_themes',
            'capabilities', 'passkeys', 'provider_registry', 'invitations',
            'service_secrets', 'api_tokens', 'webhooks', 'first_party_hooks',
            'server_extensions', 'governance', 'service_principals',
            'verified_links',
        ], false);
        $this->setFlags($allOff);
        $this->actingAs($this->makeAdmin(['username' => 'feature-all-off-admin']));

        $page = $this->get('/admin/features');

        $this->assertStatus(200, $page);
        self::assertStringContainsString('Feature flags', $page->body());
        self::assertStringContainsString('href="/admin/features"', $page->body());
    }
}
