<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** The flag-gated /admin/packages/security console: overview render + flag-independent emergency brake. */
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

    public function test_the_emergency_brake_chip_is_not_the_admin_pill(): void
    {
        // .pill-admin is the accent-filled operator chip and carries three distinct
        // meanings across 41 call sites; the ledger forbids recolouring it. Slice 14
        // reclassified the armed brake to .pill-danger instead, and this is that
        // decision's regression guard — in both brake states.
        $this->actingAs($this->admin);

        $live = $this->get('/admin/packages/security');
        $this->assertStatus(200, $live);
        self::assertStringNotContainsString('pill-admin', $live->body());

        $this->assertRedirectContains($this->post('/admin/packages/security/execution', [
            'disabled' => '1',
            'current_password' => 'password123',
        ]), '/admin/packages/security');

        $armed = $this->get('/admin/packages/security');
        $this->assertStatus(200, $armed);
        self::assertStringContainsString('class="pill pill-danger"', $armed->body());
        self::assertStringNotContainsString('pill-admin', $armed->body());
    }

    public function test_advisory_and_blocklist_counts_card_survives_the_restyle(): void
    {
        // Two live counts that exist nowhere else on this console, plus the only
        // pointer to where an operator acts on them. The design drops the card.
        $this->actingAs($this->admin);

        $body = $this->get('/admin/packages/security')->body();

        self::assertStringContainsString('advisory record(s)', $body);
        self::assertStringContainsString('local block(s)', $body);
        self::assertStringContainsString('href="/admin/registries"', $body);
    }

    public function test_transparency_log_renders_an_empty_state(): void
    {
        // Neither the design nor production had one: an unseeded console rendered a
        // bare table head over an empty body, which reads as a broken table.
        $this->actingAs($this->admin);

        $body = $this->get('/admin/packages/security')->body();

        self::assertStringContainsString('No transparency entries yet.', $body);
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
