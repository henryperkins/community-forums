<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\PackagePublisherRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\RegistrySnapshotRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\Registry\RegistryVerificationException;
use App\Security\Registry\TrustChainVerifier;
use App\Service\Registry\RegistrySnapshotService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** TM-SC-01/02 end-to-end on the ingest path; TM-SC-05 source substitution. */
final class RegistrySnapshotServiceTest extends TestCase
{
    private SigningHarness $root;

    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
    }

    private function service(): RegistrySnapshotService
    {
        return new RegistrySnapshotService(
            $this->db,
            new TrustChainVerifier(),
            new PackageRegistryRepository($this->db),
            new RegistryTrustKeyRepository($this->db),
            new RegistrySnapshotRepository($this->db),
            new PackagePublisherRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
        );
    }

    /** @param array<string,mixed> $overrides @return array{json:string,signature:string,key_id:string} */
    private function snapshot(array $overrides = []): array
    {
        return $this->root->mintSnapshot($overrides);
    }

    private function expectCode(string $code, callable $fn): void
    {
        try {
            $fn();
            self::fail("expected RegistryVerificationException($code)");
        } catch (RegistryVerificationException $e) {
            self::assertSame($code, $e->code);
        }
    }

    public function test_a_valid_snapshot_applies_and_reapply_is_an_unchanged_noop(): void
    {
        $snap = $this->snapshot(['packages' => [[
            'uid' => 'acme/midnight-theme',
            'type' => 'theme',
            'name' => 'Midnight Theme',
            'releases' => [
                ['version' => '1.0.0', 'digest' => hash('sha256', 'artifact:acme/midnight-theme:1.0.0'), 'core_min' => '0.1.0', 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none'],
                ['version' => '1.1.0', 'digest' => hash('sha256', 'artifact:acme/midnight-theme:1.1.0'), 'core_min' => '0.1.0', 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none'],
            ],
        ]]]);

        $out = $this->service()->applySnapshot($this->ids['registry_id'], $snap['json'], $snap['signature'], $snap['key_id']);
        self::assertSame('applied', $out['status']);
        self::assertSame(1, $out['packages']);
        self::assertSame(1, $out['releases'], 'only 1.1.0 is new; 1.0.0 exists from the fixture');

        $pkg = (new PackageRepository($this->db))->findByUid('acme/midnight-theme');
        $latest = (new PackageReleaseRepository($this->db))->find((int) $pkg['latest_release_id']);
        self::assertSame('1.1.0', $latest['version'], 'latest pointer follows the highest stable version');

        $registry = (new PackageRegistryRepository($this->db))->find($this->ids['registry_id']);
        self::assertSame(hash('sha256', $snap['json']), $registry['last_snapshot_digest']);
        self::assertTrue($this->service()->isFresh($registry));

        $again = $this->service()->applySnapshot($this->ids['registry_id'], $snap['json'], $snap['signature'], $snap['key_id']);
        self::assertSame('unchanged', $again['status']);
    }

    public function test_new_packages_create_publisher_and_package_rows(): void
    {
        $snap = $this->snapshot(['packages' => [[
            'uid' => 'umbrella/hive-automation',
            'type' => 'automation',
            'name' => 'Hive Automation',
            'releases' => [['version' => '0.9.0', 'digest' => str_repeat('c', 64), 'core_min' => null, 'core_max' => null, 'channel' => 'beta', 'advisory' => 'none']],
        ]]]);
        $out = $this->service()->applySnapshot($this->ids['registry_id'], $snap['json'], $snap['signature'], $snap['key_id']);
        self::assertSame('applied', $out['status']);

        $pkg = (new PackageRepository($this->db))->findByUid('umbrella/hive-automation');
        self::assertNotNull($pkg);
        self::assertSame('automation', $pkg['type']);
        self::assertSame('reviewed_declarative', $pkg['trust_class']);
        self::assertNull($pkg['latest_release_id'], 'a beta-only package has no stable latest');
        self::assertNotNull((new PackagePublisherRepository($this->db))->findByUid('umbrella'));
    }

    public function test_expired_replayed_future_and_tampered_snapshots_refuse(): void
    {
        $svc = $this->service();
        $rid = $this->ids['registry_id'];

        $expired = $this->root->mintExpiredSnapshot();
        $this->expectCode('expired_snapshot', fn () => $svc->applySnapshot($rid, $expired['json'], $expired['signature'], $expired['key_id']));

        $future = $this->snapshot(['generated_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 86400)]);
        $this->expectCode('future_snapshot', fn () => $svc->applySnapshot($rid, $future['json'], $future['signature'], $future['key_id']));

        $now = $this->snapshot();
        $svc->applySnapshot($rid, $now['json'], $now['signature'], $now['key_id']);
        $older = $this->snapshot(['generated_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600)]);
        $this->expectCode('replayed_snapshot', fn () => $svc->applySnapshot($rid, $older['json'], $older['signature'], $older['key_id']));

        $tampered = $this->snapshot(['generated_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 60)]);
        $this->expectCode('bad_signature', fn () => $svc->applySnapshot($rid, SigningHarness::tamper($tampered['json']), $tampered['signature'], $tampered['key_id']));
    }

    public function test_source_substitution_cannot_claim_a_pinned_uid(): void
    {
        $registries = new PackageRegistryRepository($this->db);
        $otherId = $registries->create('rb-evil', 'Evil Mirror', 'https://mirror.invalid');
        $otherRoot = SigningHarness::generate('evil-root');
        (new RegistryTrustKeyRepository($this->db))->pin($otherId, 'evil-root', $otherRoot->publicKey(), null, null);

        $snap = $otherRoot->mintSnapshot();
        $this->expectCode('uid_conflict', fn () => $this->service()->applySnapshot($otherId, $snap['json'], $snap['signature'], 'evil-root'));

        $pkg = (new PackageRepository($this->db))->findByUid('acme/midnight-theme');
        self::assertSame($this->ids['registry_id'], (int) $pkg['registry_id'], 'ownership unchanged');
    }

    public function test_release_immutability_identity_and_trust_class_rules(): void
    {
        $svc = $this->service();
        $rid = $this->ids['registry_id'];

        $rewrite = $this->snapshot(['packages' => [[
            'uid' => 'acme/midnight-theme', 'type' => 'theme',
            'releases' => [['version' => '1.0.0', 'digest' => str_repeat('d', 64), 'core_min' => null, 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none']],
        ]]]);
        $this->expectCode('release_digest_rewrite', fn () => $svc->applySnapshot($rid, $rewrite['json'], $rewrite['signature'], $rewrite['key_id']));

        $badUid = $this->snapshot(['generated_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 1), 'packages' => [[
            'uid' => '../etc/passwd', 'type' => 'theme',
            'releases' => [['version' => '1.0.0', 'digest' => str_repeat('e', 64), 'core_min' => null, 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none']],
        ]]]);
        $this->expectCode('invalid_uid', fn () => $svc->applySnapshot($rid, $badUid['json'], $badUid['signature'], $badUid['key_id']));

        $selfTrust = $this->snapshot(['generated_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 2), 'packages' => [[
            'uid' => 'acme/sneaky', 'type' => 'theme', 'trust_class' => 'first_party',
            'releases' => [['version' => '1.0.0', 'digest' => str_repeat('f', 64), 'core_min' => null, 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none']],
        ]]]);
        $this->expectCode('entry_trust_class', fn () => $svc->applySnapshot($rid, $selfTrust['json'], $selfTrust['signature'], $selfTrust['key_id']));

        $badType = $this->snapshot(['generated_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 3), 'packages' => [[
            'uid' => 'acme/odd', 'type' => 'rootkit',
            'releases' => [['version' => '1.0.0', 'digest' => str_repeat('a', 64), 'core_min' => null, 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none']],
        ]]]);
        $this->expectCode('entry_type', fn () => $svc->applySnapshot($rid, $badType['json'], $badType['signature'], $badType['key_id']));

        self::assertNull((new PackageRepository($this->db))->findByUid('acme/sneaky'));
        self::assertNull((new PackageRepository($this->db))->findByUid('acme/odd'));
    }
}
