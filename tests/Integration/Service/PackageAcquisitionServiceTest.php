<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackagePolicyException;
use App\Security\Registry\RegistryVerificationException;
use App\Security\Registry\TrustChainVerifier;
use App\Service\Packages\PackageAcquisitionService;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Registry\ArrayRegistryTransport;
use App\Service\Registry\RegistryFetchResult;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageAcquisitionServiceTest extends TestCase
{
    private SigningHarness $root;

    /** @var array<string,mixed> */
    private array $seeded;

    private string $artifactDir;

    private PackageArtifactStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate();
        $this->artifactDir = sys_get_temp_dir() . '/rb-acquire-' . bin2hex(random_bytes(4));
        $this->store = new PackageArtifactStore($this->artifactDir);
        $this->seeded = RegistryFixtures::seed($this->db, $this->root);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->artifactDir . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->artifactDir);
        parent::tearDown();
    }

    /** @param array<string,RegistryFetchResult> $responses */
    private function service(array $responses = []): PackageAcquisitionService
    {
        return new PackageAcquisitionService(
            $this->db,
            new TrustChainVerifier(),
            new RegistryTrustKeyRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageReviewDecisionRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            $this->store,
            new ManifestValidator(),
            new ArrayRegistryTransport($responses),
        );
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,mixed>} registry, package, release rows */
    private function rows(?int $releaseId = null): array
    {
        return [
            (new PackageRegistryRepository($this->db))->find($this->seeded['registry_id']),
            (new PackageRepository($this->db))->find($this->seeded['package_id']),
            (new PackageReleaseRepository($this->db))->find($releaseId ?? $this->seeded['release_id']),
        ];
    }

    /** @param array<string,mixed> $minted */
    private function seedUnhydrated(array $minted): int
    {
        $this->db->run(
            'INSERT INTO package_releases (package_id, version, digest, channel, advisory_status)
             VALUES (:package_id, :version, :digest, \'stable\', \'none\')',
            [
                'package_id' => $this->seeded['package_id'],
                'version' => $minted['version'],
                'digest' => $minted['digest'],
            ],
        );

        return (int) $this->db->pdo()->lastInsertId();
    }

    private function releaseUrl(string $version): string
    {
        return 'https://registry.invalid/releases/acme/midnight-theme/' . $version . '/rb-release-envelope.v1.json';
    }

    public function test_cold_cache_fetches_verifies_hydrates_and_records_evidence(): void
    {
        $envelope = $this->root->mintReleaseEnvelope(['version' => '2.0.0']);
        $releaseId = $this->seedUnhydrated($envelope['release']);
        [$registry, $package, $release] = $this->rows($releaseId);

        $manifest = $this->service([
            $this->releaseUrl('2.0.0') => new RegistryFetchResult(200, $envelope['body'], null),
        ])->ensureVerified($registry, $package, $release);

        self::assertSame('acme/midnight-theme', $manifest->uid);
        self::assertSame('2.0.0', $manifest->version);
        self::assertTrue($this->store->verify($envelope['release']['digest']), 'artifact cached content-addressed');

        $hydrated = (new PackageReleaseRepository($this->db))->find($releaseId);
        self::assertSame('approved', $hydrated['review_status']);
        self::assertSame($this->root->keyId(), $hydrated['signed_key_id']);
        self::assertNotEmpty($hydrated['manifest_json']);

        $decision = (new PackageReviewDecisionRepository($this->db))
            ->latestForDigest($this->seeded['package_id'], $envelope['release']['digest']);
        self::assertSame('approved', $decision['decision']);
        self::assertSame('release_document', $decision['source']);
        self::assertNotEmpty($decision['evidence_json']);

        $log = (new PackageTransparencyLogRepository($this->db))->forPackageUid('acme/midnight-theme');
        self::assertSame('release_verified', $log[0]['event']);
    }

    public function test_warm_cache_never_touches_the_transport_and_reverifies(): void
    {
        $this->store->put(hash('sha256', $this->seeded['release_document']), $this->seeded['release_document']);
        [$registry, $package, $release] = $this->rows();

        $manifest = $this->service([])->ensureVerified($registry, $package, $release);
        self::assertSame('1.0.0', $manifest->version);
    }

    public function test_review_digest_binding_a_different_document_cannot_satisfy_the_pinned_digest(): void
    {
        $pinned = $this->root->mintRelease(['version' => '2.0.0']);
        $releaseId = $this->seedUnhydrated($pinned);
        [$registry, $package, $release] = $this->rows($releaseId);

        $other = $this->root->mintReleaseEnvelope(['version' => '2.0.0', 'manifest' => ['description' => 'different bytes']]);
        try {
            $this->service([$this->releaseUrl('2.0.0') => new RegistryFetchResult(200, $other['body'], null)])
                ->ensureVerified($registry, $package, $release);
            self::fail('expected artifact_digest refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('artifact_digest', $e->code);
        }
        self::assertNull(
            (new PackageReviewDecisionRepository($this->db))->latestForDigest($this->seeded['package_id'], $other['release']['digest']),
            'no review evidence may be cached for refused bytes',
        );
    }

    public function test_signature_from_an_unpinned_key_refuses_even_with_a_matching_digest(): void
    {
        $rogue = SigningHarness::generate('rogue-1');
        $envelope = $rogue->mintReleaseEnvelope(['version' => '2.0.0']);
        $releaseId = $this->seedUnhydrated($envelope['release']);
        [$registry, $package, $release] = $this->rows($releaseId);

        try {
            $this->service([$this->releaseUrl('2.0.0') => new RegistryFetchResult(200, $envelope['body'], null)])
                ->ensureVerified($registry, $package, $release);
            self::fail('expected trust-chain refusal');
        } catch (RegistryVerificationException $e) {
            self::assertSame('unknown_key', $e->code);
        }
    }

    public function test_identity_mismatch_refuses(): void
    {
        $envelope = $this->root->mintReleaseEnvelope(['uid' => 'acme/other-package', 'version' => '2.0.0']);
        $releaseId = $this->seedUnhydrated(['version' => '2.0.0', 'digest' => $envelope['release']['digest']]);
        [$registry, $package, $release] = $this->rows($releaseId);

        try {
            $this->service([$this->releaseUrl('2.0.0') => new RegistryFetchResult(200, $envelope['body'], null)])
                ->ensureVerified($registry, $package, $release);
            self::fail('expected release_identity refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('release_identity', $e->code);
        }
    }

    public function test_submitted_review_hydrates_without_recording_a_decision(): void
    {
        $envelope = $this->root->mintReleaseEnvelope(['version' => '2.0.0', 'review_status' => 'submitted']);
        $releaseId = $this->seedUnhydrated($envelope['release']);
        [$registry, $package, $release] = $this->rows($releaseId);

        $this->service([$this->releaseUrl('2.0.0') => new RegistryFetchResult(200, $envelope['body'], null)])
            ->ensureVerified($registry, $package, $release);

        self::assertSame('submitted', (new PackageReleaseRepository($this->db))->find($releaseId)['review_status']);
        self::assertNull(
            (new PackageReviewDecisionRepository($this->db))
                ->latestForDigest($this->seeded['package_id'], $envelope['release']['digest']),
        );
    }

    public function test_source_pinning_refuses_offsite_source_url(): void
    {
        $envelope = $this->root->mintReleaseEnvelope(['version' => '2.0.0']);
        $releaseId = $this->seedUnhydrated($envelope['release']);
        $this->db->run(
            'UPDATE package_releases SET source_url = :source_url WHERE id = :id',
            ['source_url' => 'https://evil.invalid/releases/x.json', 'id' => $releaseId],
        );
        [$registry, $package, $release] = $this->rows($releaseId);

        try {
            $this->service([])->ensureVerified($registry, $package, $release);
            self::fail('expected source_mismatch refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('source_mismatch', $e->code);
        }
    }

    public function test_transport_failure_is_a_coded_refusal(): void
    {
        $envelope = $this->root->mintReleaseEnvelope(['version' => '2.0.0']);
        $releaseId = $this->seedUnhydrated($envelope['release']);
        [$registry, $package, $release] = $this->rows($releaseId);

        try {
            $this->service([])->ensureVerified($registry, $package, $release);
            self::fail('expected fetch_failed refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('fetch_failed', $e->code);
        }
    }

    public function test_tampered_cached_artifact_fails_closed_on_reverify(): void
    {
        $digest = hash('sha256', $this->seeded['release_document']);
        $this->store->put($digest, $this->seeded['release_document']);
        file_put_contents($this->store->pathFor($digest), $this->seeded['release_document'] . ' ');
        [$registry, $package, $release] = $this->rows();

        try {
            $this->service([])->ensureVerified($registry, $package, $release);
            self::fail('expected artifact_digest refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('artifact_digest', $e->code);
        }
    }
}
