<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\PackagePublisherRepository;
use App\Repository\PublisherSigningKeyRepository;
use App\Repository\SettingRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** GA-DOD-09: no-JS publisher trust console end to end. */
final class AppPackagePublisherConsoleTest extends TestCase
{
    private SigningHarness $root;
    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int,release_digest:string,release_document:string} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('pub-root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $this->setFlags(['package_registry' => true]);
    }

    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    private function pid(): int
    {
        return $this->ids['publisher_id'];
    }

    private function enabledInstall(): int
    {
        return (int) $this->db->insert(
            "INSERT INTO installed_packages (package_id, release_id, digest, publisher_id, trust_class, review_status, state, installed_at)
             VALUES (?, ?, ?, ?, 'reviewed_declarative', 'approved', 'enabled', UTC_TIMESTAMP())",
            [$this->ids['package_id'], $this->ids['release_id'], $this->ids['release_digest'], $this->pid()],
        );
    }

    public function test_publisher_detail_renders_noindex_with_forms(): void
    {
        $response = $this->get('/admin/packages/publishers/' . $this->pid());
        $this->assertStatus(200, $response);
        self::assertSame('noindex', $response->getHeader('x-robots-tag'));
        $this->assertSeeText($response, 'Acme Themes');
        self::assertStringContainsString('/admin/packages/publishers/' . $this->pid() . '/suspend', $response->body());
    }

    public function test_suspend_cascades_then_reinstate_leaves_installs_disabled(): void
    {
        $installId = $this->enabledInstall();

        $suspend = $this->post('/admin/packages/publishers/' . $this->pid() . '/suspend', [
            'current_password' => 'password123',
            'reason' => 'signing key exfiltrated',
        ]);
        $this->assertRedirectContains($suspend, '/admin/packages/publishers/' . $this->pid());
        self::assertSame('suspended', (string) (new PackagePublisherRepository($this->db))->find($this->pid())['status']);
        self::assertSame('disabled', (string) $this->db->fetchValue('SELECT state FROM installed_packages WHERE id = ?', [$installId]));

        $reinstate = $this->post('/admin/packages/publishers/' . $this->pid() . '/reinstate', ['current_password' => 'password123']);
        $this->assertRedirectContains($reinstate, '/admin/packages/publishers/' . $this->pid());
        self::assertSame('active', (string) (new PackagePublisherRepository($this->db))->find($this->pid())['status']);
        self::assertSame('disabled', (string) $this->db->fetchValue('SELECT state FROM installed_packages WHERE id = ?', [$installId]));
    }

    public function test_wrong_password_is_422_and_preserves_state(): void
    {
        $installId = $this->enabledInstall();
        $response = $this->post('/admin/packages/publishers/' . $this->pid() . '/suspend', [
            'current_password' => 'nope',
            'reason' => 'x',
        ]);
        $this->assertStatus(422, $response);
        self::assertSame('active', (string) (new PackagePublisherRepository($this->db))->find($this->pid())['status']);
        self::assertSame('enabled', (string) $this->db->fetchValue('SELECT state FROM installed_packages WHERE id = ?', [$installId]));
    }

    public function test_pin_rotate_revoke_over_http(): void
    {
        $keys = new PublisherSigningKeyRepository($this->db);
        $this->assertRedirectContains(
            $this->post('/admin/packages/publishers/' . $this->pid() . '/keys', [
                'current_password' => 'password123',
                'key_id' => 'pub-root-1',
                'public_key' => base64_encode($this->root->publicKey()),
            ]),
            '/admin/packages/publishers/' . $this->pid(),
        );

        $successor = SigningHarness::generate('pub-root-2');
        $rotation = $this->root->mintRotation($successor);
        $envelope = json_encode([
            'document' => $rotation['json'],
            'signature' => base64_encode($rotation['signature']),
            'key_id' => $rotation['key_id'],
        ], JSON_UNESCAPED_SLASHES);
        $this->assertRedirectContains(
            $this->post('/admin/packages/publishers/' . $this->pid() . '/rotate', ['current_password' => 'password123', 'envelope' => $envelope]),
            '/admin/packages/publishers/' . $this->pid(),
        );

        $active = array_values(array_filter($keys->forPublisher($this->pid()), static fn (array $r): bool => (string) $r['status'] === 'active'));
        self::assertCount(1, $active);
        self::assertSame('pub-root-2', (string) $active[0]['key_id']);

        $this->assertRedirectContains(
            $this->post('/admin/publisher-keys/' . (int) $active[0]['id'] . '/revoke', ['current_password' => 'password123', 'reason' => 'compromise']),
            '/admin/packages/publishers/' . $this->pid(),
        );
        self::assertSame('revoked', (string) $keys->find((int) $active[0]['id'])['status']);
    }

    public function test_forged_rotation_is_422_and_pins_nothing(): void
    {
        $this->post('/admin/packages/publishers/' . $this->pid() . '/keys', [
            'current_password' => 'password123',
            'key_id' => 'pub-root-1',
            'public_key' => base64_encode($this->root->publicKey()),
        ]);
        $successor = SigningHarness::generate('pub-root-2');
        $rotation = $this->root->mintRotation($successor);
        $envelope = json_encode([
            'document' => $rotation['json'],
            'signature' => base64_encode(SigningHarness::tamper($rotation['signature'])),
            'key_id' => $rotation['key_id'],
        ], JSON_UNESCAPED_SLASHES);

        $response = $this->post('/admin/packages/publishers/' . $this->pid() . '/rotate', ['current_password' => 'password123', 'envelope' => $envelope]);
        $this->assertStatus(422, $response);
        $keyIds = array_map(static fn (array $r): string => (string) $r['key_id'], (new PublisherSigningKeyRepository($this->db))->forPublisher($this->pid()));
        self::assertSame(['pub-root-1'], $keyIds);
    }
}
