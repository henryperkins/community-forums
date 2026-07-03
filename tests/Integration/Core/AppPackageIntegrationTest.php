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
        $seeded = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir, [
            'source_id' => 'rb-remote-' . $suffix,
            'publisher_uid' => 'acme-remote-' . $suffix,
            'publisher_name' => 'Acme Remote',
            'package_uid' => 'acme/remote-' . $suffix,
            'name' => 'Remote ' . ucfirst($suffix),
            'type' => 'remote_app',
            'trust_class' => 'reviewed_remote',
            'release' => ['manifest' => [
                'permissions' => $permissions,
                'settings_schema' => $settingsSchema,
            ]],
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
}
