<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** The deploy-dark /admin/packages/security console: overview render + flag-independent emergency brake. */
final class AppPackageSecurityConsoleTest extends TestCase
{
    private SigningHarness $root;
    /** @var array<string,mixed> */
    private array $seeded;
    private string $artifactDir;
    /** @var array<string,mixed> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artifactDir = sys_get_temp_dir() . '/rb-test-packages-security';
        $this->root = SigningHarness::generate();
        $this->seeded = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir);
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

    public function test_console_renders_overview_with_the_seeded_publisher(): void
    {
        $this->actingAs($this->admin);
        $response = $this->get('/admin/packages/security');
        $this->assertStatus(200, $response);
        $this->assertSeeText($response, 'Package security response');
        $this->assertSeeText($response, 'Acme Themes');
        self::assertSame('noindex', $response->getHeader('x-robots-tag'));
    }

    public function test_emergency_disable_requires_reauth_then_pauses_execution(): void
    {
        $this->actingAs($this->admin);

        $bad = $this->post('/admin/packages/security/execution', [
            'disabled' => '1',
            'current_password' => 'wrong',
        ]);
        $this->assertStatus(422, $bad);
        $this->assertSeeText($bad, 'password is incorrect');

        $ok = $this->post('/admin/packages/security/execution', [
            'disabled' => '1',
            'current_password' => 'password123',
            'reason' => 'incident-42',
        ]);
        $this->assertRedirectContains($ok, '/admin/packages/security');
        $this->assertSeeText($this->get('/admin/packages/security'), 'Package execution is halted');
    }

    public function test_console_routes_are_dark_without_the_flag(): void
    {
        (new SettingRepository($this->db))->set('features', ['package_registry' => false]);
        $this->actingAs($this->admin);
        $this->assertStatus(404, $this->get('/admin/packages/security'));
        $this->assertStatus(404, $this->post('/admin/packages/security/execution', [
            'disabled' => '1',
            'current_password' => 'password123',
        ]));
    }
}
