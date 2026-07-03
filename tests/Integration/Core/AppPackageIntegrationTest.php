<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\InstalledPackageRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\SettingRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class AppPackageIntegrationTest extends TestCase
{
    private SigningHarness $root;
    private string $artifactDir;
    /** @var array<string,mixed> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artifactDir = sys_get_temp_dir() . '/rb-test-packages';
        $this->root = SigningHarness::generate();
        (new SettingRepository($this->db))->set('features', ['package_registry' => true]);
        $this->admin = $this->makeAdmin(['password' => 'password123']);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->artifactDir . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    /**
     * Seed and drive a remote_app install to enabled through the real kernel
     * lifecycle so permission rows are genuinely granted and state='enabled'.
     *
     * @param array<string,mixed> $permissions manifest permissions block
     * @param array<string,mixed> $settingsSchema manifest settings_schema block
     * @return array{package_id:int, installed_id:int, base:string}
     */
    private function installEnabledRemoteApp(array $permissions, array $settingsSchema, string $suffix = 'widget'): array
    {
        // ManifestValidator refuses settings_schema:{fields:[]} — omit it entirely
        // when the test declares no configurable settings.
        $manifest = ['permissions' => $permissions];
        if (!empty($settingsSchema['fields'])) {
            $manifest['settings_schema'] = $settingsSchema;
        }

        $seeded = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir, [
            'source_id' => 'rb-remote-' . $suffix,
            'publisher_uid' => 'acme-remote-' . $suffix,
            'publisher_name' => 'Acme Remote',
            'package_uid' => 'acme/remote-' . $suffix,
            'name' => 'Remote ' . ucfirst($suffix),
            'type' => 'remote_app',
            'trust_class' => 'reviewed_remote',
            'release' => ['manifest' => $manifest],
        ]);
        (new PackageRegistryRepository($this->db))->setEnabled((int) $seeded['registry_id'], true);

        $base = '/admin/packages/' . $seeded['package_id'];
        $this->post($base . '/install', ['current_password' => 'password123']);
        $this->post($base . '/consent', ['current_password' => 'password123']);
        $this->post($base . '/enable', ['current_password' => 'password123']);

        $installed = (new InstalledPackageRepository($this->db))->findByPackage((int) $seeded['package_id']);
        self::assertIsArray($installed, 'remote_app should be installed');
        self::assertSame('enabled', $installed['state'], 'remote_app should reach enabled state');

        return [
            'package_id' => (int) $seeded['package_id'],
            'installed_id' => (int) $installed['id'],
            'base' => $base,
        ];
    }

    public function test_integration_panel_renders_grant_summary_and_settings_form(): void
    {
        $app = $this->installEnabledRemoteApp(
            [
                'api_scopes' => ['read:boards'],
                'events' => ['topic.created'],
                'outbound_hosts' => ['hooks.example.test'],
                'data_classes' => ['content.public'],
            ],
            ['fields' => [
                ['key' => 'display_name', 'type' => 'string', 'label' => 'Display name', 'required' => false],
                ['key' => 'mode', 'type' => 'select', 'label' => 'Mode', 'required' => false, 'options' => ['live', 'test']],
            ]],
        );

        $detail = $this->get($app['base']);
        $this->assertStatus(200, $detail);
        self::assertSame('noindex', $detail->getHeader('x-robots-tag'));
        // Copy makes the runtime model explicit.
        $this->assertSeeText($detail, 'runs remotely');
        // Manifest = ceiling, grants = authority: the granted scope/event/host show.
        $this->assertSeeText($detail, 'read:boards');
        $this->assertSeeText($detail, 'topic.created');
        $this->assertSeeText($detail, 'hooks.example.test');
        // Settings form is generated from settings_schema.
        $this->assertSeeText($detail, 'Display name');
        $this->assertSeeText($detail, 'Mode');
        // No credentials yet, and no plaintext anywhere.
        $this->assertSeeText($detail, 'No credentials provisioned');
        $this->assertDontSeeText($detail, 'shown only once');
    }

    public function test_no_js_settings_save_persists_and_redisplays_value(): void
    {
        $app = $this->installEnabledRemoteApp(
            ['api_scopes' => ['read:boards']],
            ['fields' => [['key' => 'display_name', 'type' => 'string', 'label' => 'Display name', 'required' => false]]],
            'settings',
        );

        $save = $this->post($app['base'] . '/integration/settings', ['display_name' => 'Acme Bot']);
        $this->assertRedirectContains($save, $app['base']);
        self::assertSame('noindex', $save->getHeader('x-robots-tag'));

        $detail = $this->get($app['base']);
        $this->assertStatus(200, $detail);
        $this->assertSeeText($detail, 'Acme Bot');
    }

    public function test_settings_422_preserves_typed_non_secret_edits(): void
    {
        $app = $this->installEnabledRemoteApp(
            ['api_scopes' => ['read:boards']],
            ['fields' => [
                ['key' => 'display_name', 'type' => 'string', 'label' => 'Display name', 'required' => false],
                ['key' => 'max_items', 'type' => 'integer', 'label' => 'Max items', 'required' => false],
            ]],
            'draftloss',
        );

        // max_items is invalid, so the whole save aborts (422). display_name is a
        // valid edit that never persisted — the re-render must keep it typed in
        // rather than reverting to the stored (empty) value (anti-draft-loss).
        $save = $this->post($app['base'] . '/integration/settings', [
            'display_name' => 'Typed But Unsaved',
            'max_items' => 'not-a-number',
        ]);

        $this->assertStatus(422, $save);
        $this->assertSeeText($save, 'must be a whole number');
        $this->assertSeeText($save, 'Typed But Unsaved');
    }

    public function test_provision_reveals_credentials_once_then_lists_by_status(): void
    {
        (new SettingRepository($this->db))->set('features', ['package_registry' => true, 'service_secrets' => true, 'api_tokens' => true]);
        $app = $this->installEnabledRemoteApp(
            ['api_scopes' => ['read:boards']],   // api_scope-only → deterministic token mint, no webhook URL needed
            ['fields' => []],
            'provision',
        );

        $reveal = $this->post($app['base'] . '/integration/provision', ['current_password' => 'password123']);
        $this->assertStatus(200, $reveal);
        self::assertSame('noindex', $reveal->getHeader('x-robots-tag'));
        $this->assertSeeText($reveal, 'shown only once');

        // Reload: the credential is listed by label/status, and the plaintext is gone (one-time).
        $detail = $this->get($app['base']);
        $this->assertSeeText($detail, 'active');
        $this->assertSeeText($detail, 'read:boards');
        $this->assertDontSeeText($detail, 'shown only once');
    }

    public function test_provision_requires_reauth_and_mints_nothing_on_wrong_password(): void
    {
        (new SettingRepository($this->db))->set('features', ['package_registry' => true, 'service_secrets' => true, 'api_tokens' => true]);
        $app = $this->installEnabledRemoteApp(['api_scopes' => ['read:boards']], ['fields' => []], 'reauth');

        $bad = $this->post($app['base'] . '/integration/provision', ['current_password' => 'wrong-password']);
        $this->assertStatus(422, $bad);
        $this->assertDontSeeText($bad, 'shown only once');

        // No credential landed: the panel still says none provisioned.
        $detail = $this->get($app['base']);
        $this->assertSeeText($detail, 'No credentials provisioned');
    }

    public function test_integration_posts_reject_missing_csrf_token(): void
    {
        $app = $this->installEnabledRemoteApp(['api_scopes' => ['read:boards']], ['fields' => []], 'csrf');

        $noToken = $this->post($app['base'] . '/integration/provision', ['current_password' => 'password123'], false);
        $this->assertStatus(403, $noToken);
    }

    public function test_integration_routes_reject_non_integration_package_types(): void
    {
        // A theme install has no integration surface: gate resolves the type and 404s.
        $themePkg = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir, [
            'source_id' => 'rb-theme-x', 'publisher_uid' => 'acme-theme-x',
            'package_uid' => 'acme/theme-x', 'name' => 'Theme X', 'type' => 'theme',
        ]);
        $this->db->insert(
            "INSERT INTO installed_packages (package_id, digest, trust_class, review_status, state, installed_by, installed_at, updated_at)
             VALUES (?, REPEAT('a', 64), 'reviewed_declarative', 'approved', 'enabled', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [(int) $themePkg['package_id'], (int) $this->admin['id']],
        );

        $this->assertStatus(404, $this->post('/admin/packages/' . $themePkg['package_id'] . '/integration/settings', ['x' => '1']));
        // And an unknown package id is 404 too.
        $this->assertStatus(404, $this->post('/admin/packages/999999/integration/provision', ['current_password' => 'password123']));
    }

    public function test_integration_routes_are_dark_without_the_flag(): void
    {
        $app = $this->installEnabledRemoteApp(['api_scopes' => ['read:boards']], ['fields' => []], 'dark');
        (new SettingRepository($this->db))->set('features', ['package_registry' => false]);

        foreach ([
            '/integration/settings',
            '/integration/provision',
            '/integration/credentials/1/rotate',
            '/integration/credentials/1/revoke',
            '/integration/disable',
            '/integration/export',
        ] as $suffix) {
            $this->assertStatus(404, $this->post($app['base'] . $suffix, ['current_password' => 'password123']));
        }
    }

    /** @return array{package_id:int, token_plaintext:string} */
    private function seedProvisionedInstall(): array
    {
        (new SettingRepository($this->db))->set('features', ['package_registry' => true, 'service_secrets' => true, 'api_tokens' => true]);
        $app = $this->installEnabledRemoteApp(['api_scopes' => ['read:boards']], ['fields' => []], 'export');
        $reveal = $this->post($app['base'] . '/integration/provision', ['current_password' => 'password123']);
        $this->assertStatus(200, $reveal);
        preg_match('/rbt_[A-Za-z0-9]+/', $reveal->body(), $m);
        self::assertNotEmpty($m, 'provision reveal should carry the one-time token plaintext');

        return ['package_id' => $app['package_id'], 'token_plaintext' => $m[0]];
    }

    public function test_export_settings_returns_attribution_without_plaintext(): void
    {
        ['package_id' => $packageId, 'token_plaintext' => $token] = $this->seedProvisionedInstall();

        $response = $this->post('/admin/packages/' . $packageId . '/integration/export', []);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('application/json', $response->getHeader('content-type'));
        self::assertStringContainsString('attachment', $response->getHeader('content-disposition'));

        $body = $response->body();
        $export = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('settings', $export);
        self::assertArrayHasKey('credentials', $export);
        self::assertNotSame([], $export['credentials'], 'credential attribution is present');
        // Redaction: no minted token may ever appear in the export bytes, nor any secret ref.
        self::assertStringNotContainsString($token, $body, 'api token plaintext must not be exported');
        self::assertStringNotContainsString('svcsec_', $body, 'secret refs are not part of the operator export');
    }

    public function test_export_settings_is_dark_without_flag(): void
    {
        // package_registry stays default-off.
        (new SettingRepository($this->db))->set('features', ['package_registry' => false]);
        $this->assertStatus(404, $this->post('/admin/packages/1/integration/export', []));
    }
}
