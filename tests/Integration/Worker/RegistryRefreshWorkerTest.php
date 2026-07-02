<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\RegistrySnapshotRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\Registry\TrustChainVerifier;
use App\Service\Registry\ArrayRegistryTransport;
use App\Service\Registry\RegistryAdvisoryService;
use App\Service\Registry\RegistryFetchResult;
use App\Service\Registry\RegistrySnapshotService;
use App\Worker\RegistryRefreshWorker;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class RegistryRefreshWorkerTest extends TestCase
{
    private SigningHarness $root;

    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        (new PackageRegistryRepository($this->db))->setEnabled($this->ids['registry_id'], true);
    }

    /** @param array<string,RegistryFetchResult> $responses */
    private function worker(array $responses, bool $enabled = true): RegistryRefreshWorker
    {
        $snapshots = new RegistrySnapshotService(
            $this->db,
            new TrustChainVerifier(),
            new PackageRegistryRepository($this->db),
            new RegistryTrustKeyRepository($this->db),
            new RegistrySnapshotRepository($this->db),
            new PackagePublisherRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
        );
        $advisories = new RegistryAdvisoryService(
            $this->db,
            new TrustChainVerifier(),
            new RegistryTrustKeyRepository($this->db),
            new PackageAdvisoryRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new ModerationLogRepository($this->db),
        );

        return new RegistryRefreshWorker(
            new PackageRegistryRepository($this->db),
            $snapshots,
            $advisories,
            new ArrayRegistryTransport($responses),
            $enabled,
        );
    }

    /** @return array{0:string,1:string} snapshot + advisory envelope bodies */
    private function envelopes(): array
    {
        $snap = $this->root->mintSnapshot(['packages' => [[
            'uid' => 'acme/midnight-theme', 'type' => 'theme',
            'releases' => [
                ['version' => '1.0.0', 'digest' => hash('sha256', 'artifact:acme/midnight-theme:1.0.0'), 'core_min' => '0.1.0', 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none'],
                ['version' => '1.2.0', 'digest' => hash('sha256', 'artifact:acme/midnight-theme:1.2.0'), 'core_min' => '0.1.0', 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none'],
            ],
        ]]]);
        $adv = $this->root->mintAdvisory(['action' => 'warn']);

        $snapshotEnvelope = json_encode([
            'format' => 'rb-snapshot-envelope.v1',
            'document' => $snap['json'],
            'signature' => base64_encode($snap['signature']),
            'key_id' => $snap['key_id'],
        ], JSON_UNESCAPED_SLASHES);
        $advisoryEnvelopes = json_encode([
            'format' => 'rb-advisory-envelopes.v1',
            'advisories' => [[
                'document' => $adv['json'],
                'signature' => base64_encode($adv['signature']),
                'key_id' => $adv['key_id'],
            ]],
        ], JSON_UNESCAPED_SLASHES);

        return [(string) $snapshotEnvelope, (string) $advisoryEnvelopes];
    }

    public function test_refresh_applies_snapshot_and_advisories(): void
    {
        [$snapBody, $advBody] = $this->envelopes();
        $stats = $this->worker([
            'https://registry.invalid' . RegistryRefreshWorker::SNAPSHOT_PATH => new RegistryFetchResult(200, $snapBody, null),
            'https://registry.invalid' . RegistryRefreshWorker::ADVISORIES_PATH => new RegistryFetchResult(200, $advBody, null),
        ])->run();

        self::assertSame(1, $stats['refreshed']);
        self::assertSame(1, $stats['advisories']);
        self::assertSame(0, $stats['failed']);

        $pkg = (new PackageRepository($this->db))->findByUid('acme/midnight-theme');
        self::assertSame('warned', $pkg['advisory_status']);
        self::assertSame('1.2.0', (new PackageReleaseRepository($this->db))->find((int) $pkg['latest_release_id'])['version']);
    }

    public function test_flag_off_is_a_pure_noop(): void
    {
        [$snapBody] = $this->envelopes();
        $stats = $this->worker([
            'https://registry.invalid' . RegistryRefreshWorker::SNAPSHOT_PATH => new RegistryFetchResult(200, $snapBody, null),
        ], enabled: false)->run();

        self::assertSame(['refreshed' => 0, 'unchanged' => 0, 'advisories' => 0, 'failed' => 0, 'skipped' => 1], $stats);
        self::assertNull((new RegistrySnapshotRepository($this->db))->latestFor($this->ids['registry_id']));
    }

    public function test_tampered_snapshot_counts_failed_and_missing_advisories_are_fine(): void
    {
        [$snapBody] = $this->envelopes();
        $decoded = json_decode($snapBody, true);
        $decoded['document'] = SigningHarness::tamper((string) $decoded['document']);
        $tampered = (string) json_encode($decoded, JSON_UNESCAPED_SLASHES);

        $stats = $this->worker([
            'https://registry.invalid' . RegistryRefreshWorker::SNAPSHOT_PATH => new RegistryFetchResult(200, $tampered, null),
            'https://registry.invalid' . RegistryRefreshWorker::ADVISORIES_PATH => new RegistryFetchResult(404, '', null),
        ])->run();
        self::assertSame(1, $stats['failed']);
        self::assertSame(0, $stats['refreshed']);

        $stats = $this->worker([
            'https://registry.invalid' . RegistryRefreshWorker::SNAPSHOT_PATH => new RegistryFetchResult(0, '', 'response exceeded byte cap'),
        ])->run();
        self::assertSame(1, $stats['failed']);
    }

    public function test_second_run_with_the_same_snapshot_is_unchanged(): void
    {
        [$snapBody, $advBody] = $this->envelopes();
        $responses = [
            'https://registry.invalid' . RegistryRefreshWorker::SNAPSHOT_PATH => new RegistryFetchResult(200, $snapBody, null),
            'https://registry.invalid' . RegistryRefreshWorker::ADVISORIES_PATH => new RegistryFetchResult(200, $advBody, null),
        ];
        $this->worker($responses)->run();
        $stats = $this->worker($responses)->run();
        self::assertSame(['refreshed' => 0, 'unchanged' => 1, 'advisories' => 1, 'failed' => 0, 'skipped' => 0], $stats);
    }
}
