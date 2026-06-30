<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\ServerExtensionRepository;
use Tests\Support\TestCase;

final class AppAdminExtensionsTest extends TestCase
{
    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        $this->db->run(
            "INSERT INTO settings (`key`, value, updated_at) VALUES ('features', ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = UTC_TIMESTAMP()",
            [json_encode($flags, JSON_THROW_ON_ERROR)],
        );
    }

    public function test_extensions_admin_page_is_dark_by_default(): void
    {
        $this->makeAdmin();
        $this->actingAs($this->makeAdmin(['username' => 'ext-dark-admin']));

        $this->assertStatus(404, $this->get('/admin/extensions'));
    }

    public function test_extensions_admin_page_lists_handlers_and_probe(): void
    {
        $this->setFlags(['server_extensions' => true]);
        $admin = $this->makeAdmin(['username' => 'ext-admin-page']);
        $this->actingAs($admin);
        $packageId = $this->db->insert(
            "INSERT INTO packages (package_uid, name, type, trust_class, created_at)
             VALUES ('local.admin.extension', 'Admin Extension', 'server_extension', 'isolated_server', UTC_TIMESTAMP())",
        );
        $installedId = $this->db->insert(
            "INSERT INTO installed_packages (package_id, digest, trust_class, review_status, state, installed_by, installed_at)
             VALUES (?, REPEAT('b', 64), 'isolated_server', 'approved', 'enabled', ?, UTC_TIMESTAMP())",
            [$packageId, (int) $admin['id']],
        );
        (new ServerExtensionRepository($this->db))->upsertHandler($installedId, [
            'handler_key' => 'admin-handler',
            'entrypoint' => 'extension.php',
            'events' => ['topic.created'],
            'jobs' => [],
            'permissions' => ['broker' => [], 'outbound_hosts' => []],
            'resource_limits' => ['time_ms' => 1000],
            'storage_quota_bytes' => 0,
        ]);

        $page = $this->get('/admin/extensions');

        $this->assertStatus(200, $page);
        self::assertStringContainsString('Server extensions', $page->body());
        self::assertStringContainsString('Sandbox probe', $page->body());
        self::assertStringContainsString('admin-handler', $page->body());
        self::assertStringContainsString('Global emergency disable', $page->body());
    }

    public function test_extensions_admin_page_renders_feature_activation_index(): void
    {
        $this->setFlags([
            'server_extensions' => true,
            'polls' => true,
            'community_memory' => true,
            'content_references' => true,
            'bookmark_folders' => true,
            'custom_profile_fields' => true,
        ]);
        $this->actingAs($this->makeAdmin(['username' => 'activation-admin']));

        $page = $this->get('/admin/extensions');

        $this->assertStatus(200, $page);
        self::assertStringContainsString('feature-activation-index', $page->body());
        self::assertStringContainsString('The UI that was missing', $page->body());
        self::assertStringContainsString('feature-area-card', $page->body());
        self::assertStringContainsString('polls', $page->body());
        self::assertStringContainsString('bookmark_folders', $page->body());
        self::assertStringContainsString('link_previews', $page->body());
        self::assertStringContainsString('Design-ahead', $page->body());
    }
}
