<?php

declare(strict_types=1);

namespace App\Service\Registry;

use App\Core\Database;
use App\Core\Telemetry;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\RegistrySnapshotRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\Registry\PackageIdentity;
use App\Security\Registry\RegistryVerificationException;
use App\Security\Registry\TrustChainVerifier;

/**
 * Verifies and applies signed catalogue snapshots.
 *
 * Fail-closed and atomic in production: trust-chain verification, freshness,
 * anti-replay, canonical identity, source pinning, release immutability, and
 * registry trust-class limits all pass before a snapshot is recorded.
 */
final class RegistrySnapshotService
{
    private const TYPES = ['theme', 'automation', 'remote_app', 'server_extension', 'local'];
    private const REGISTRY_TRUST_CLASSES = ['reviewed_declarative', 'reviewed_remote', 'isolated_server', 'local_dev'];
    private const CHANNELS = ['stable', 'beta', 'dev'];

    public function __construct(
        private Database $db,
        private TrustChainVerifier $verifier,
        private PackageRegistryRepository $registries,
        private RegistryTrustKeyRepository $trustKeys,
        private RegistrySnapshotRepository $snapshots,
        private PackagePublisherRepository $publishers,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /** @return array{status:string,packages:int,releases:int} */
    public function applySnapshot(
        int $registryId,
        string $documentJson,
        string $signature,
        string $keyId,
        ?\DateTimeImmutable $now = null,
    ): array {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $registry = $this->registries->find($registryId);
        if ($registry === null) {
            throw new \RuntimeException("Unknown registry id $registryId.");
        }

        try {
            $result = $this->verifyAndApply($registryId, $documentJson, $signature, $keyId, $now);
        } catch (RegistryVerificationException $e) {
            $this->telemetry?->emit('registry.snapshot', [
                'registry' => (string) $registry['source_id'],
                'result' => 'refused',
                'reason' => $e->code,
            ]);
            throw $e;
        }

        $this->telemetry?->emit('registry.snapshot', [
            'registry' => (string) $registry['source_id'],
            'result' => $result['status'],
            'packages' => $result['packages'],
            'releases' => $result['releases'],
            'digest' => hash('sha256', $documentJson),
        ]);

        return $result;
    }

    /** Freshness = the doc-declared expiry window has not lapsed. */
    public function isFresh(array $registryRow, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expires = $registryRow['snapshot_expires_at'] ?? null;

        return $expires !== null && $now <= new \DateTimeImmutable((string) $expires, new \DateTimeZone('UTC'));
    }

    /** @return array{status:string,packages:int,releases:int} */
    private function verifyAndApply(
        int $registryId,
        string $documentJson,
        string $signature,
        string $keyId,
        \DateTimeImmutable $now,
    ): array {
        $doc = $this->verifier->verify(
            $documentJson,
            $signature,
            $keyId,
            $this->trustKeys->forRegistry($registryId),
            'rb-registry-snapshot.v1',
            $now,
        );

        $generatedAt = $this->parseUtc($doc->payload['generated_at'] ?? null);
        $expiresAt = $this->parseUtc($doc->payload['expires_at'] ?? null);
        $entries = $doc->payload['packages'] ?? null;
        if ($generatedAt === null || $expiresAt === null || !is_array($entries)) {
            throw new RegistryVerificationException('malformed_snapshot', 'Snapshot must carry generated_at, expires_at, and a packages list.');
        }
        if ($expiresAt <= $now) {
            throw new RegistryVerificationException('expired_snapshot', 'Snapshot expired at ' . $expiresAt->format('Y-m-d H:i:s') . ' UTC.');
        }
        if ($generatedAt > $now->modify('+' . TrustChainVerifier::CLOCK_SKEW_SECONDS . ' seconds')) {
            throw new RegistryVerificationException('future_snapshot', 'Snapshot generated_at is in the future beyond tolerated skew.');
        }

        $digest = hash('sha256', $documentJson);
        if ($this->snapshots->findByDigest($registryId, $digest) !== null) {
            return ['status' => 'unchanged', 'packages' => count($entries), 'releases' => 0];
        }

        $latest = $this->snapshots->latestFor($registryId);
        if ($latest !== null && $generatedAt <= new \DateTimeImmutable((string) $latest['generated_at'], new \DateTimeZone('UTC'))) {
            throw new RegistryVerificationException('replayed_snapshot', 'Snapshot is not newer than the last applied snapshot.');
        }

        return $this->db->transaction(function () use ($registryId, $documentJson, $signature, $keyId, $digest, $generatedAt, $expiresAt, $entries): array {
            $newReleases = 0;
            foreach ($entries as $entry) {
                $newReleases += $this->applyEntry($registryId, is_array($entry) ? $entry : []);
            }

            $generated = $generatedAt->format('Y-m-d H:i:s');
            $expires = $expiresAt->format('Y-m-d H:i:s');
            $this->snapshots->record($registryId, $digest, $documentJson, $signature, $keyId, $generated, $expires);
            $this->registries->recordSnapshot($registryId, $digest, $generated, $expires);

            return ['status' => 'applied', 'packages' => count($entries), 'releases' => $newReleases];
        });
    }

    /** @param array<string,mixed> $entry @return int newly created releases */
    private function applyEntry(int $registryId, array $entry): int
    {
        $uid = (string) ($entry['uid'] ?? '');
        if (!PackageIdentity::isValidUid($uid)) {
            throw new RegistryVerificationException('invalid_uid', "Snapshot entry has a malformed package uid: '$uid'.");
        }

        $type = (string) ($entry['type'] ?? '');
        if (!in_array($type, self::TYPES, true)) {
            throw new RegistryVerificationException('entry_type', "Snapshot entry '$uid' has unknown type '$type'.");
        }

        $trustClass = (string) ($entry['trust_class'] ?? 'reviewed_declarative');
        if (!in_array($trustClass, self::REGISTRY_TRUST_CLASSES, true)) {
            throw new RegistryVerificationException('entry_trust_class', "Snapshot entry '$uid' asserts a non-registry trust class '$trustClass'.");
        }

        $package = $this->packages->findByUid($uid);
        if ($package !== null && (int) $package['registry_id'] !== $registryId) {
            throw new RegistryVerificationException('uid_conflict', "Package '$uid' is pinned to another source.");
        }

        if ($package === null) {
            $publisherUid = PackageIdentity::publisherUid($uid);
            $publisherId = $this->publishers->ensure($publisherUid, (string) ($entry['publisher_name'] ?? $publisherUid));
            $packageId = $this->packages->create([
                'package_uid' => $uid,
                'registry_id' => $registryId,
                'publisher_id' => $publisherId,
                'name' => (string) ($entry['name'] ?? explode('/', $uid, 2)[1]),
                'type' => $type,
                'trust_class' => $trustClass,
            ]);
        } else {
            $packageId = (int) $package['id'];
        }

        $created = 0;
        foreach ((array) ($entry['releases'] ?? []) as $release) {
            $created += $this->applyRelease($packageId, $uid, is_array($release) ? $release : []);
        }
        $this->packages->setLatestRelease($packageId, $this->latestStableId($packageId));

        return $created;
    }

    /** @param array<string,mixed> $release @return int 1 when a row was created */
    private function applyRelease(int $packageId, string $uid, array $release): int
    {
        $version = trim((string) ($release['version'] ?? ''));
        $digest = strtolower((string) ($release['digest'] ?? ''));
        $channel = (string) ($release['channel'] ?? 'stable');
        if ($version === '' || strlen($version) > 64 || preg_match('/^[0-9a-f]{64}$/', $digest) !== 1 || !in_array($channel, self::CHANNELS, true)) {
            throw new RegistryVerificationException('entry_release', "Snapshot release for '$uid' is malformed.");
        }

        $existing = $this->releases->findVersion($packageId, $version);
        if ($existing !== null) {
            if (!hash_equals((string) $existing['digest'], $digest)) {
                throw new RegistryVerificationException('release_digest_rewrite', "Snapshot tries to change the digest of '$uid' $version.");
            }

            return 0;
        }

        $this->releases->create([
            'package_id' => $packageId,
            'version' => $version,
            'digest' => $digest,
            'source_url' => isset($release['source_url']) ? (string) $release['source_url'] : null,
            'license' => isset($release['license']) ? (string) $release['license'] : null,
            'core_min' => isset($release['core_min']) ? (string) $release['core_min'] : null,
            'core_max' => isset($release['core_max']) ? (string) $release['core_max'] : null,
            'channel' => $channel,
        ]);

        return 1;
    }

    /** Highest stable version by version_compare; mirrored by RepairService later. */
    private function latestStableId(int $packageId): ?int
    {
        $best = null;
        foreach ($this->releases->forPackage($packageId) as $row) {
            if ((string) $row['channel'] !== 'stable') {
                continue;
            }
            if ($best === null || version_compare((string) $row['version'], (string) $best['version'], '>')) {
                $best = $row;
            }
        }

        return $best === null ? null : (int) $best['id'];
    }

    private function parseUtc(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
