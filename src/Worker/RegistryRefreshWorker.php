<?php

declare(strict_types=1);

namespace App\Worker;

use App\Core\Telemetry;
use App\Repository\PackageRegistryRepository;
use App\Security\Registry\RegistryVerificationException;
use App\Service\Registry\RegistryAdvisoryService;
use App\Service\Registry\RegistrySnapshotService;
use App\Service\Registry\RegistryTransport;

/**
 * Cron worker keeping enabled registries inside their freshness window.
 * Request paths never perform registry network I/O.
 */
final class RegistryRefreshWorker
{
    public const SNAPSHOT_PATH = '/rb-snapshot-envelope.v1.json';
    public const ADVISORIES_PATH = '/rb-advisory-envelopes.v1.json';

    public function __construct(
        private PackageRegistryRepository $registries,
        private RegistrySnapshotService $snapshots,
        private RegistryAdvisoryService $advisories,
        private RegistryTransport $transport,
        private bool $enabled,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /** @return array{refreshed:int,unchanged:int,advisories:int,failed:int,skipped:int} */
    public function run(): array
    {
        $stats = ['refreshed' => 0, 'unchanged' => 0, 'advisories' => 0, 'failed' => 0, 'skipped' => 0];
        if (!$this->enabled) {
            $stats['skipped'] = 1;

            return $stats;
        }

        foreach ($this->registries->enabled() as $registry) {
            $base = rtrim((string) $registry['base_url'], '/');
            try {
                $result = $this->applySnapshotFrom($base . self::SNAPSHOT_PATH, (int) $registry['id']);
                $stats[$result === 'applied' ? 'refreshed' : 'unchanged']++;
                $stats['advisories'] += $this->ingestAdvisoriesFrom($base . self::ADVISORIES_PATH, (int) $registry['id']);
            } catch (RegistryVerificationException|\RuntimeException $e) {
                $stats['failed']++;
                $this->telemetry?->emit('registry.refresh', [
                    'registry' => (string) $registry['source_id'],
                    'result' => 'failed',
                    'reason' => $e instanceof RegistryVerificationException ? $e->code : $e->getMessage(),
                ]);
            }
        }

        // Ingest deferred per-advisory enforcement; reconcile installed policy
        // once for the whole batch instead of O(advisories) scans.
        if ($stats['advisories'] > 0) {
            $this->advisories->reconcileInstalledPolicies();
        }

        return $stats;
    }

    private function applySnapshotFrom(string $url, int $registryId): string
    {
        $envelope = $this->fetchJson($url);
        if (($envelope['format'] ?? null) !== 'rb-snapshot-envelope.v1') {
            throw new \RuntimeException('snapshot envelope has an unknown format');
        }

        $signature = base64_decode((string) ($envelope['signature'] ?? ''), true);
        if (!is_string($envelope['document'] ?? null) || $signature === false) {
            throw new \RuntimeException('snapshot envelope is malformed');
        }

        return $this->snapshots->applySnapshot(
            $registryId,
            (string) $envelope['document'],
            $signature,
            (string) ($envelope['key_id'] ?? ''),
        )['status'];
    }

    private function ingestAdvisoriesFrom(string $url, int $registryId): int
    {
        $result = $this->transport->fetch($url);
        if ($result->status === 404) {
            return 0;
        }
        if ($result->error !== null || $result->status !== 200) {
            throw new \RuntimeException('advisory fetch failed: ' . ($result->error ?? ('HTTP ' . $result->status)));
        }

        $doc = json_decode($result->body, true);
        if (!is_array($doc) || ($doc['format'] ?? null) !== 'rb-advisory-envelopes.v1' || !is_array($doc['advisories'] ?? null)) {
            throw new \RuntimeException('advisory envelope list is malformed');
        }

        $ingested = 0;
        foreach ($doc['advisories'] as $envelope) {
            if (!is_array($envelope)) {
                continue;
            }
            $signature = base64_decode((string) ($envelope['signature'] ?? ''), true);
            if (!is_string($envelope['document'] ?? null) || $signature === false) {
                continue;
            }
            $this->advisories->ingest(
                $registryId,
                (string) $envelope['document'],
                $signature,
                (string) ($envelope['key_id'] ?? ''),
                enforce: false,
            );
            $ingested++;
        }

        return $ingested;
    }

    /** @return array<string,mixed> */
    private function fetchJson(string $url): array
    {
        $result = $this->transport->fetch($url);
        if ($result->error !== null || $result->status !== 200) {
            throw new \RuntimeException('fetch failed: ' . ($result->error ?? ('HTTP ' . $result->status)));
        }

        $decoded = json_decode($result->body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('response is not a JSON object');
        }

        return $decoded;
    }
}
