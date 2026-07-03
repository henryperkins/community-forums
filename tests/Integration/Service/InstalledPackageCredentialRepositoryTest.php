<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\InstalledPackageCredentialRepository;
use App\Repository\InstalledPackageRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class InstalledPackageCredentialRepositoryTest extends TestCase
{
    public function test_api_token_link_has_token_id_and_null_webhook(): void
    {
        $repo = new InstalledPackageCredentialRepository($this->db);
        $install = $this->seedInstall();
        $tokenId = $this->seedApiToken();

        $id = $repo->insertApiToken($install, $tokenId, 'pkg:acme read', '["forum.read"]', null);
        $row = $repo->find($id);

        self::assertSame('api_token', $row['kind']);
        self::assertSame($tokenId, (int) $row['api_token_id']);
        self::assertNull($row['webhook_id']);
        self::assertSame($id, (int) $repo->findByApiToken($tokenId)['id']);
    }

    public function test_webhook_link_has_webhook_id_and_null_token(): void
    {
        $repo = new InstalledPackageCredentialRepository($this->db);
        $install = $this->seedInstall();
        $hookId = $this->seedWebhook();

        $id = $repo->insertWebhook($install, $hookId, 'pkg:acme events', '["thread.created"]', null);
        $row = $repo->find($id);

        self::assertSame('webhook', $row['kind']);
        self::assertSame($hookId, (int) $row['webhook_id']);
        self::assertNull($row['api_token_id']);
        self::assertSame($id, (int) $repo->findByWebhook($hookId)['id']);
    }

    public function test_mark_revoked_is_idempotent_and_drops_from_active(): void
    {
        $repo = new InstalledPackageCredentialRepository($this->db);
        $install = $this->seedInstall();
        $id = $repo->insertApiToken($install, $this->seedApiToken(), 'pkg', '[]', null);

        self::assertCount(1, $repo->activeForInstall($install));
        self::assertSame(1, $repo->markRevoked($id), 'first revoke flips revoked_at');
        self::assertSame(0, $repo->markRevoked($id), 'second revoke is a no-op');
        self::assertSame([], $repo->activeForInstall($install));
        self::assertCount(1, $repo->forInstall($install), 'revoked rows still listed by forInstall');
    }

    private function seedInstall(): int
    {
        $seeded = RegistryFixtures::seed($this->db, SigningHarness::generate());

        return (new InstalledPackageRepository($this->db))->create([
            'package_id' => $seeded['package_id'],
            'release_id' => $seeded['release_id'],
            'digest' => $seeded['release_digest'],
            'source_registry_id' => $seeded['registry_id'],
            'publisher_id' => $seeded['publisher_id'],
            'trust_class' => 'reviewed_declarative',
            'review_status' => 'approved',
            'compat_min' => '0.1.0',
            'compat_max' => null,
            'installed_by' => null,
        ]);
    }

    private function seedApiToken(): int
    {
        $admin = $this->makeAdmin();

        return $this->db->insert(
            'INSERT INTO api_tokens (name, token_hash, scopes, created_by) VALUES (?, ?, ?, ?)',
            ['pkg-token-' . uniqid(), hash('sha256', uniqid('', true)), '["forum.read"]', $admin['id']],
        );
    }

    private function seedWebhook(): int
    {
        $admin = $this->makeAdmin();

        return $this->db->insert(
            'INSERT INTO webhooks (name, url, events, secret_ref, created_by) VALUES (?, ?, ?, ?, ?)',
            ['pkg-hook-' . uniqid(), 'https://example.test/hook', '["thread.created"]', 'svcsec_' . str_repeat('b', 12), $admin['id']],
        );
    }
}
