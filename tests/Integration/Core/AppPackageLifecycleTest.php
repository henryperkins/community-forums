<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\InstalledPackageRepository;
use App\Repository\SettingRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class AppPackageLifecycleTest extends TestCase
{
    private SigningHarness $root;

    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int,release_digest:string,release_document:string} */
    private array $seeded;

    private string $artifactDir;

    /** @var array<string,mixed> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artifactDir = sys_get_temp_dir() . '/rb-test-packages';
        $this->root = SigningHarness::generate();
        $this->seeded = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir);
        (new \App\Repository\PackageRegistryRepository($this->db))->setEnabled((int) $this->seeded['registry_id'], true);
        (new SettingRepository($this->db))->set('features', ['package_registry' => true]);
        $this->admin = $this->makeAdmin(['password' => 'password123']);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->artifactDir . '/*.json') ?: [] as $file) {
            unlink($file);
        }

        parent::tearDown();
    }

    private function detailPath(): string
    {
        return '/admin/packages/' . $this->seeded['package_id'];
    }

    public function test_lifecycle_routes_are_dark_without_the_flag(): void
    {
        (new SettingRepository($this->db))->set('features', ['package_registry' => false]);
        $this->actingAs($this->admin);

        $this->assertStatus(404, $this->post($this->detailPath() . '/plan', []));
        $this->assertStatus(404, $this->get($this->detailPath() . '/consent'));
        $this->assertStatus(404, $this->post($this->detailPath() . '/install', ['current_password' => 'password123']));
    }

    public function test_guest_and_non_admin_are_denied_before_anything_else(): void
    {
        $response = $this->get($this->detailPath() . '/consent');
        $this->assertRedirectContains($response, '/login');

        $this->actingAs($this->makeUser());
        $this->assertStatus(403, $this->post($this->detailPath() . '/install', ['current_password' => 'x']));
        $this->assertStatus(403, $this->post($this->detailPath() . '/plan', []));
    }

    public function test_full_no_js_install_journey(): void
    {
        $this->actingAs($this->admin);

        $plan = $this->post($this->detailPath() . '/plan', []);
        $this->assertStatus(200, $plan);
        $this->assertSeeText($plan, 'Midnight Theme');
        $this->assertSeeText($plan, 'Install plan');
        $this->assertSeeText($plan, 'Store its own settings and data');
        self::assertSame('noindex', $plan->getHeader('x-robots-tag'));

        $install = $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);
        $this->assertRedirectContains($install, $this->detailPath() . '/consent');
        $installed = (new InstalledPackageRepository($this->db))->findByPackage((int) $this->seeded['package_id']);
        self::assertIsArray($installed);
        $installedId = (int) $installed['id'];

        $consentForm = $this->get($this->detailPath() . '/consent');
        $this->assertStatus(200, $consentForm);
        $this->assertSeeText($consentForm, 'Store its own settings and data');
        $this->assertSeeText($consentForm, 'Grant and continue');

        $consent = $this->post($this->detailPath() . '/consent', ['current_password' => 'password123']);
        $this->assertRedirectContains($consent, $this->detailPath());

        $enable = $this->post($this->detailPath() . '/enable', ['current_password' => 'password123']);
        $this->assertRedirectContains($enable, $this->detailPath());
        self::assertSame('enabled', (new InstalledPackageRepository($this->db))->find($installedId)['state']);

        $detail = $this->get($this->detailPath());
        $this->assertSeeText($detail, 'Enabled');
        $this->assertSeeText($detail, 'Disable');
        $this->assertDontSeeText($detail, 'Install does not exist yet');

        $disable = $this->post($this->detailPath() . '/disable', []);
        $this->assertRedirectContains($disable, $this->detailPath());
        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($installedId)['state']);
    }

    public function test_update_with_expansion_stages_then_consent_applies(): void
    {
        $this->actingAs($this->admin);
        $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);
        $this->post($this->detailPath() . '/consent', ['current_password' => 'password123']);

        $expanded = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '1.1.0',
            'manifest' => ['permissions' => [
                'data_classes' => ['package.own_storage'],
                'outbound_hosts' => ['api.example.com'],
            ]],
        ], $this->artifactDir);

        $update = $this->post($this->detailPath() . '/update', []);
        $this->assertStatus(422, $update);

        $update = $this->post($this->detailPath() . '/update', ['current_password' => 'password123']);
        $this->assertRedirectContains($update, $this->detailPath() . '/consent');

        $consentForm = $this->get($this->detailPath() . '/consent');
        $this->assertSeeText($consentForm, 'New permissions');
        $this->assertSeeText($consentForm, 'api.example.com');

        $apply = $this->post($this->detailPath() . '/consent', [
            'intent' => 'approve_update',
            'staged_release_id' => (string) $expanded['release_id'],
            'current_password' => 'password123',
        ]);
        $this->assertRedirectContains($apply, $this->detailPath());
        $this->assertSeeText($this->get($this->detailPath()), '1.1.0');
    }

    public function test_consent_intent_post_never_applies_a_staged_update(): void
    {
        $this->actingAs($this->admin);
        $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);
        $this->post($this->detailPath() . '/consent', ['intent' => 'consent', 'current_password' => 'password123']);
        RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '1.1.0',
            'manifest' => ['permissions' => [
                'data_classes' => ['package.own_storage'],
                'outbound_hosts' => ['api.example.com'],
            ]],
        ], $this->artifactDir);
        $this->post($this->detailPath() . '/update', ['current_password' => 'password123']);

        $response = $this->post($this->detailPath() . '/consent', ['intent' => 'consent', 'current_password' => 'password123']);

        $this->assertStatus(422, $response);
        $install = (new InstalledPackageRepository($this->db))->findByPackage((int) $this->seeded['package_id']);
        self::assertIsArray($install);
        self::assertSame($this->seeded['release_digest'], $install['digest'], 'a grant-intent POST must never activate a staged release');
        self::assertNotNull($install['staged_release_id'], 'the staged update stays pending for an explicit approval');
    }

    public function test_stale_staged_approval_is_refused_when_the_stage_changed(): void
    {
        $this->actingAs($this->admin);
        $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);
        $this->post($this->detailPath() . '/consent', ['intent' => 'consent', 'current_password' => 'password123']);
        $expanded = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '1.1.0',
            'manifest' => ['permissions' => [
                'data_classes' => ['package.own_storage'],
                'outbound_hosts' => ['api.example.com'],
            ]],
        ], $this->artifactDir);
        $this->post($this->detailPath() . '/update', ['current_password' => 'password123']);

        $response = $this->post($this->detailPath() . '/consent', [
            'intent' => 'approve_update',
            'staged_release_id' => (string) ((int) $expanded['release_id'] + 1),
            'current_password' => 'password123',
        ]);

        $this->assertStatus(422, $response);
        $install = (new InstalledPackageRepository($this->db))->findByPackage((int) $this->seeded['package_id']);
        self::assertIsArray($install);
        self::assertSame($this->seeded['release_digest'], $install['digest'], 'an approval bound to a different stage must not activate the current one');
        self::assertSame((int) $expanded['release_id'], (int) $install['staged_release_id'], 'the pending stage is left for a fresh review');
    }

    public function test_staged_consent_get_uses_hydrated_metadata_without_reverifying_or_mutating(): void
    {
        $this->actingAs($this->admin);
        $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);
        $this->post($this->detailPath() . '/consent', ['current_password' => 'password123']);

        $expanded = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '1.1.0',
            'manifest' => ['permissions' => [
                'data_classes' => ['package.own_storage'],
                'outbound_hosts' => ['api.example.com'],
            ]],
        ], $this->artifactDir);
        $this->post($this->detailPath() . '/update', ['current_password' => 'password123']);
        $this->db->run('UPDATE package_releases SET manifest_json = NULL WHERE id = ?', [(int) $expanded['release_id']]);
        $logCount = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM package_transparency_log WHERE event = 'release_verified'",
        );

        $response = $this->get($this->detailPath() . '/consent');

        $this->assertStatus(200, $response);
        $this->assertSeeText($response, 'release_unverified');
        self::assertNull(
            $this->db->fetchValue('SELECT manifest_json FROM package_releases WHERE id = ?', [(int) $expanded['release_id']]),
            'GET /consent must not hydrate release metadata',
        );
        self::assertSame(
            $logCount,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM package_transparency_log WHERE event = 'release_verified'"),
            'GET /consent must not write transparency evidence',
        );
    }

    public function test_staged_consent_get_renders_refusal_instead_of_500_when_source_is_unresolvable(): void
    {
        $this->actingAs($this->admin);
        $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);
        $this->post($this->detailPath() . '/consent', ['current_password' => 'password123']);
        RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '1.1.0',
            'manifest' => ['permissions' => [
                'data_classes' => ['package.own_storage'],
                'outbound_hosts' => ['api.example.com'],
            ]],
        ], $this->artifactDir);
        $this->post($this->detailPath() . '/update', ['current_password' => 'password123']);
        $this->db->run('UPDATE packages SET registry_id = NULL WHERE id = ?', [(int) $this->seeded['package_id']]);

        $response = $this->get($this->detailPath() . '/consent');

        $this->assertStatus(200, $response);
        $this->assertSeeText($response, 'source_mismatch');
    }

    public function test_install_with_bad_release_and_wrong_password_renders_422_not_500(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post($this->detailPath() . '/install', [
            'release_id' => '999999',
            'current_password' => 'wrong-password',
        ]);

        $this->assertStatus(422, $response);
        self::assertNull(
            (new InstalledPackageRepository($this->db))->findByPackage((int) $this->seeded['package_id']),
            'a failed reauth against an unresolvable release must never install',
        );
    }

    public function test_failed_update_preserves_the_operator_selected_target_release(): void
    {
        $this->actingAs($this->admin);
        $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);
        $this->post($this->detailPath() . '/consent', ['intent' => 'consent', 'current_password' => 'password123']);
        $this->post($this->detailPath() . '/enable', ['current_password' => 'password123']);

        $mid = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '1.1.0',
            'manifest' => ['permissions' => ['data_classes' => ['package.own_storage']]],
        ], $this->artifactDir);
        RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '1.2.0',
            'manifest' => ['permissions' => ['data_classes' => ['package.own_storage']]],
        ], $this->artifactDir);

        $response = $this->post($this->detailPath() . '/update', [
            'release_id' => (string) $mid['release_id'],
            'current_password' => 'wrong-password',
        ]);

        $this->assertStatus(422, $response);
        self::assertStringContainsString(
            'value="' . (int) $mid['release_id'] . '" selected',
            $response->body(),
            'the failed update form must keep the operator\'s chosen target, not reset to latest',
        );
    }

    public function test_refusals_render_422_with_the_coded_reason(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/blocklist', ['digest' => $this->seeded['release_digest'], 'reason' => 'incident']);

        $plan = $this->post($this->detailPath() . '/plan', []);
        $this->assertStatus(200, $plan);
        $this->assertSeeText($plan, 'local blocklist');

        $install = $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);
        $this->assertStatus(422, $install);
        $this->assertSeeText($install, 'locally_blocked');
        self::assertNull((new InstalledPackageRepository($this->db))->findByPackage((int) $this->seeded['package_id']));
    }

    public function test_uninstall_and_export_round_trip(): void
    {
        $this->actingAs($this->admin);
        $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);

        $export = $this->post($this->detailPath() . '/export', []);
        $this->assertStatus(200, $export);
        self::assertStringContainsString('application/json', (string) $export->getHeader('content-type'));
        self::assertStringContainsString('rb-install-export.v1', $export->body());

        $uninstall = $this->post($this->detailPath() . '/uninstall', ['current_password' => 'password123']);
        $this->assertRedirectContains($uninstall, $this->detailPath());
        $this->assertSeeText($this->get($this->detailPath()), 'Uninstalled');
    }

    public function test_suspended_admin_cannot_mutate(): void
    {
        $suspended = $this->makeAdmin(['password' => 'password123']);
        $this->db->run(
            "UPDATE users SET status = 'suspended', suspended_until = '2099-01-01 00:00:00' WHERE id = ?",
            [$suspended['id']],
        );
        $this->actingAs($suspended);

        $response = $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);
        self::assertGreaterThanOrEqual(400, $response->status(), 'state beats role on every write path');
        self::assertNull((new InstalledPackageRepository($this->db))->findByPackage((int) $this->seeded['package_id']));
    }
}
