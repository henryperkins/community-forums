<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\RegistryTrustKeyRepository;
use App\Repository\SettingRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** The trust console: reauth-gated mutations, forged-rotation refusal, audit. */
final class AppRegistryAdminTest extends TestCase
{
    private SigningHarness $root;

    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int} */
    private array $ids;

    /** @var array<string,mixed> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        $this->setFlags(['package_registry' => true]);
        $this->admin = $this->makeAdmin(['password' => 'password123']);
        $this->actingAs($this->admin);
    }

    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_console_renders_sources_keys_blocklist_and_advisories(): void
    {
        $resp = $this->get('/admin/registries');
        $this->assertStatus(200, $resp);
        self::assertSame('noindex', $resp->getHeader('x-robots-tag'));
        self::assertStringContainsString('rb-test', $resp->body());
        self::assertStringContainsString('root-1', $resp->body());
        self::assertStringContainsString('Local blocklist', $resp->body());
    }

    public function test_pin_requires_reauth_and_preserves_the_form_on_error(): void
    {
        $fresh = SigningHarness::generate('root-2');
        $resp = $this->post('/admin/registries/' . $this->ids['registry_id'] . '/keys', [
            'key_id' => 'root-2',
            'public_key' => base64_encode($fresh->publicKey()),
            'current_password' => 'wrong',
        ]);
        $this->assertStatus(422, $resp);
        self::assertStringContainsString('root-2', $resp->body(), 'typed key id survives the failed post');
        self::assertNull((new RegistryTrustKeyRepository($this->db))->findKey($this->ids['registry_id'], 'root-2'));

        $ok = $this->post('/admin/registries/' . $this->ids['registry_id'] . '/keys', [
            'key_id' => 'root-2',
            'public_key' => base64_encode($fresh->publicKey()),
            'current_password' => 'password123',
        ]);
        $this->assertStatus(303, $ok);
        self::assertSame('noindex', $ok->getHeader('x-robots-tag'), 'redirects carry noindex too');
        self::assertNotNull((new RegistryTrustKeyRepository($this->db))->findKey($this->ids['registry_id'], 'root-2'));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'registry_pin_key'"));
    }

    public function test_signed_rotation_succeeds_and_forged_rotation_renders_422(): void
    {
        $successor = SigningHarness::generate('root-2');
        $rotation = $this->root->mintRotation($successor);
        $envelope = json_encode([
            'document' => $rotation['json'],
            'signature' => base64_encode($rotation['signature']),
            'key_id' => $rotation['key_id'],
        ], JSON_UNESCAPED_SLASHES);

        $ok = $this->post('/admin/registries/' . $this->ids['registry_id'] . '/rotate', [
            'envelope' => (string) $envelope,
            'current_password' => 'password123',
        ]);
        $this->assertStatus(303, $ok);
        $keys = new RegistryTrustKeyRepository($this->db);
        self::assertSame('active', $keys->findKey($this->ids['registry_id'], 'root-2')['status']);
        self::assertSame('rotated', $keys->find($this->ids['trust_key_id'])['status']);

        $attacker = SigningHarness::generate('evil-1');
        $forged = $attacker->mintRotation(SigningHarness::generate('root-3'));
        $badEnvelope = json_encode([
            'document' => $forged['json'],
            'signature' => base64_encode($forged['signature']),
            'key_id' => $forged['key_id'],
        ], JSON_UNESCAPED_SLASHES);
        $bad = $this->post('/admin/registries/' . $this->ids['registry_id'] . '/rotate', [
            'envelope' => (string) $badEnvelope,
            'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $bad);
        self::assertStringContainsString('unknown_key', $bad->body());
        self::assertNull($keys->findKey($this->ids['registry_id'], 'root-3'));
    }

    public function test_revoke_blocklist_and_advisory_ack_flows(): void
    {
        $resp = $this->post('/admin/registry-keys/' . $this->ids['trust_key_id'] . '/revoke', [
            'reason' => 'compromise drill',
            'current_password' => 'password123',
        ]);
        $this->assertStatus(303, $resp);
        self::assertSame('revoked', (new RegistryTrustKeyRepository($this->db))->find($this->ids['trust_key_id'])['status']);

        $digest = str_repeat('a', 64);
        $this->assertStatus(303, $this->post('/admin/blocklist', ['digest' => $digest, 'reason' => 'drill']));
        $blockId = (int) $this->db->fetchValue('SELECT id FROM local_package_blocks WHERE digest = ?', [$digest]);
        $this->assertStatus(422, $this->post('/admin/blocklist/' . $blockId . '/remove', ['current_password' => 'wrong']));
        $this->assertStatus(303, $this->post('/admin/blocklist/' . $blockId . '/remove', ['current_password' => 'password123']));

        $adv = $this->root->mintAdvisory();
        $envelope = json_encode([
            'document' => $adv['json'],
            'signature' => base64_encode($adv['signature']),
            'key_id' => $adv['key_id'],
        ], JSON_UNESCAPED_SLASHES);
        $refused = $this->post('/admin/registries/' . $this->ids['registry_id'] . '/advisories', [
            'envelope' => (string) $envelope,
            'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $refused);
        self::assertStringContainsString('revoked_key', $refused->body());
    }

    public function test_member_and_guest_access(): void
    {
        $this->logoutClient();
        $this->assertStatus(302, $this->get('/admin/registries'));

        $this->actingAs($this->makeUser());
        $this->assertStatus(403, $this->get('/admin/registries'));
    }
}
