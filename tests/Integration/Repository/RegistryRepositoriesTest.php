<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\LocalPackageBlockRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\RegistryTrustKeyRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class RegistryRepositoriesTest extends TestCase
{
    /** @return array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int} */
    private function fixture(): array
    {
        return RegistryFixtures::seed($this->db, SigningHarness::generate('root-1'));
    }

    public function test_registry_rows_round_trip(): void
    {
        $ids = $this->fixture();
        $repo = new PackageRegistryRepository($this->db);

        $row = $repo->findBySourceId('rb-test');
        self::assertNotNull($row);
        self::assertSame($ids['registry_id'], (int) $row['id']);
        self::assertSame([], $repo->enabled(), 'seeded registry starts dark');

        $repo->setEnabled($ids['registry_id'], true);
        self::assertCount(1, $repo->enabled());

        $repo->recordSnapshot($ids['registry_id'], str_repeat('a', 64), '2026-07-02 00:00:00', '2026-07-03 00:00:00');
        $row = $repo->find($ids['registry_id']);
        self::assertSame(str_repeat('a', 64), $row['last_snapshot_digest']);
        self::assertSame('2026-07-03 00:00:00', $row['snapshot_expires_at']);

        $newId = $repo->create('rb-two', 'Second', 'https://two.invalid');
        self::assertNotNull($repo->find($newId));
        self::assertCount(2, $repo->all());
    }

    public function test_trust_key_lifecycle_rows(): void
    {
        $ids = $this->fixture();
        $repo = new RegistryTrustKeyRepository($this->db);

        $keys = $repo->forRegistry($ids['registry_id']);
        self::assertCount(1, $keys);
        self::assertSame('root-1', $keys[0]['key_id']);
        self::assertNotNull($repo->findKey($ids['registry_id'], 'root-1'));

        $successor = SigningHarness::generate('root-2');
        $newId = $repo->pin($ids['registry_id'], 'root-2', $successor->publicKey(), null, null);
        self::assertSame('active', $repo->find($newId)['status']);

        $repo->markRotated($ids['trust_key_id']);
        $old = $repo->find($ids['trust_key_id']);
        self::assertSame('rotated', $old['status']);
        self::assertNotNull($old['valid_until']);

        $repo->revoke($newId, 'compromise drill');
        $revoked = $repo->find($newId);
        self::assertSame('revoked', $revoked['status']);
        self::assertSame('compromise drill', $revoked['revoked_reason']);
        self::assertNotNull($revoked['revoked_at']);
    }

    public function test_publisher_ensure_is_idempotent(): void
    {
        $this->fixture();
        $repo = new PackagePublisherRepository($this->db);
        $existing = $repo->findByUid('acme');
        self::assertNotNull($existing);
        self::assertSame((int) $existing['id'], $repo->ensure('acme', 'Acme Themes'));
        $fresh = $repo->ensure('umbrella', 'Umbrella Corp');
        self::assertSame($fresh, (int) $repo->findByUid('umbrella')['id']);
    }

    public function test_package_and_release_rows(): void
    {
        $ids = $this->fixture();
        $packages = new PackageRepository($this->db);
        $releases = new PackageReleaseRepository($this->db);

        $pkg = $packages->findByUid('acme/midnight-theme');
        self::assertNotNull($pkg);
        self::assertSame('none', $pkg['advisory_status']);

        $rel = $releases->findVersion($ids['package_id'], '1.0.0');
        self::assertNotNull($rel);
        self::assertNull($releases->findVersion($ids['package_id'], '9.9.9'));

        $newRelease = $releases->create([
            'package_id' => $ids['package_id'],
            'version' => '1.1.0',
            'digest' => hash('sha256', 'artifact:acme/midnight-theme:1.1.0'),
            'source_url' => null,
            'license' => 'MIT',
            'core_min' => '0.1.0',
            'core_max' => null,
            'channel' => 'stable',
        ]);
        self::assertCount(2, $releases->forPackage($ids['package_id']));

        $packages->setLatestRelease($ids['package_id'], $newRelease);
        self::assertSame($newRelease, (int) $packages->find($ids['package_id'])['latest_release_id']);

        $packages->setAdvisoryStatus($ids['package_id'], 'warned');
        $releases->setAdvisoryStatus($newRelease, 'blocked');
        self::assertSame('warned', $packages->find($ids['package_id'])['advisory_status']);
        self::assertSame('blocked', $releases->find($newRelease)['advisory_status']);

        $catalog = $packages->catalog();
        self::assertCount(1, $catalog);
        self::assertSame('rb-test', $catalog[0]['registry_source_id']);
        self::assertSame('Acme Themes', $catalog[0]['publisher_name']);
    }

    public function test_advisories_upsert_and_acknowledge(): void
    {
        $ids = $this->fixture();
        $repo = new PackageAdvisoryRepository($this->db);
        $admin = $this->makeAdmin();

        $advisoryId = $repo->upsert([
            'advisory_uid' => 'RB-TEST-0001',
            'registry_id' => $ids['registry_id'],
            'package_id' => $ids['package_id'],
            'affected_version_range' => '<=1.0.0',
            'affected_digest' => null,
            'severity' => 'high',
            'action' => 'warn',
            'summary' => 'first pass',
            'signed_evidence' => '{"doc":1}',
            'issued_at' => '2026-07-01 00:00:00',
        ]);
        $again = $repo->upsert([
            'advisory_uid' => 'RB-TEST-0001',
            'registry_id' => $ids['registry_id'],
            'package_id' => $ids['package_id'],
            'affected_version_range' => '<=1.0.0',
            'affected_digest' => null,
            'severity' => 'critical',
            'action' => 'block_new',
            'summary' => 'escalated',
            'signed_evidence' => '{"doc":2}',
            'issued_at' => '2026-07-02 00:00:00',
        ]);
        self::assertSame($advisoryId, $again);
        $row = $repo->findByUid('RB-TEST-0001');
        self::assertSame('block_new', $row['action']);
        self::assertSame('critical', $row['severity']);
        self::assertCount(1, $repo->forPackage($ids['package_id']));
        self::assertCount(1, $repo->all());

        $repo->acknowledge($advisoryId, (int) $admin['id']);
        $row = $repo->findByUid('RB-TEST-0001');
        self::assertNotNull($row['acknowledged_at']);
        self::assertSame((int) $admin['id'], (int) $row['acknowledged_by']);
    }

    public function test_local_blocks(): void
    {
        $this->fixture();
        $repo = new LocalPackageBlockRepository($this->db);
        $admin = $this->makeAdmin();
        $digest = str_repeat('b', 64);

        self::assertFalse($repo->isBlocked($digest, null));
        $blockId = $repo->add($digest, null, 'incident 42', (int) $admin['id']);
        self::assertTrue($repo->isBlocked($digest, null));
        self::assertTrue($repo->isBlocked($digest, 'acme/midnight-theme'));
        self::assertFalse($repo->isBlocked(null, 'acme/midnight-theme'));

        $uidBlock = $repo->add(null, 'acme/midnight-theme', null, null);
        self::assertTrue($repo->isBlocked(null, 'acme/midnight-theme'));
        self::assertCount(2, $repo->all());

        $repo->remove($blockId);
        self::assertFalse($repo->isBlocked($digest, null));
        self::assertNotNull($repo->find($uidBlock));
    }
}
