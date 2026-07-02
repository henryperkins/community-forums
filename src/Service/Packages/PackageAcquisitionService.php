<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Database;
use App\Core\Telemetry;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackageManifest;
use App\Security\Packages\PackagePolicyException;
use App\Security\Registry\TrustChainVerifier;
use App\Service\Registry\RegistryTransport;

/**
 * Verified release acquisition for snapshot-pinned rb-release.v1 documents.
 *
 * The signed release document is the content-addressed artifact: its sha256 is
 * the digest pinned by the signed registry snapshot, and the review block lives
 * inside those signed bytes. Cached bytes are re-verified on every call.
 */
final class PackageAcquisitionService
{
    private const REVIEW_STATUSES = ['unreviewed', 'submitted', 'approved', 'rejected'];

    public function __construct(
        private Database $db,
        private TrustChainVerifier $verifier,
        private RegistryTrustKeyRepository $trustKeys,
        private PackageReleaseRepository $releases,
        private PackageReviewDecisionRepository $reviewDecisions,
        private PackageTransparencyLogRepository $transparency,
        private PackageArtifactStore $artifacts,
        private ManifestValidator $manifests,
        private RegistryTransport $transport,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /**
     * @param array<string,mixed> $registry
     * @param array<string,mixed> $package
     * @param array<string,mixed> $release
     */
    public function ensureVerified(
        array $registry,
        array $package,
        array $release,
        ?\DateTimeImmutable $now = null,
    ): PackageManifest {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $digest = (string) ($release['digest'] ?? '');

        $document = $this->artifacts->get($digest);
        $signature = $this->nonEmptyString($release['signature'] ?? null);
        $keyId = $this->nonEmptyString($release['signed_key_id'] ?? null);
        $fetched = false;

        if ($document === null || $signature === null || $keyId === null) {
            [$document, $signature, $keyId] = $this->fetchEnvelope($registry, $package, $release);
            $fetched = true;
        }

        if (!hash_equals($digest, hash('sha256', $document))) {
            throw new PackagePolicyException(
                'artifact_digest',
                'Release document bytes do not hash to the snapshot-pinned digest.',
            );
        }

        $verified = $this->verifier->verify(
            $document,
            $signature,
            $keyId,
            $this->trustKeys->forRegistry((int) $registry['id']),
            'rb-release.v1',
            $now,
        );
        $payload = $verified->payload;

        if (
            ($payload['uid'] ?? null) !== (string) $package['package_uid']
            || ($payload['version'] ?? null) !== (string) $release['version']
        ) {
            throw new PackagePolicyException(
                'release_identity',
                'The signed release document does not match the pinned package/version identity.',
            );
        }

        $review = $payload['review'] ?? null;
        $reviewStatus = is_array($review) && is_string($review['status'] ?? null) ? $review['status'] : '';
        if (!in_array($reviewStatus, self::REVIEW_STATUSES, true)) {
            throw new PackagePolicyException('release_review', 'The release document review block is malformed.');
        }
        $decidedAt = is_array($review) && is_string($review['decided_at'] ?? null)
            ? $this->dateTimeForDatabase($review['decided_at'])
            : null;

        $manifestPayload = $payload['manifest'] ?? null;
        $manifest = $this->manifests->validate(
            is_array($manifestPayload) ? $manifestPayload : [],
            (string) $package['package_uid'],
            (string) $release['version'],
        );

        if (!$this->artifacts->has($digest)) {
            $this->artifacts->put($digest, $document);
        }

        $needsHydration = $fetched
            || ($release['manifest_json'] ?? null) === null
            || ($release['review_status'] ?? '') !== $reviewStatus;
        $needsReviewDecision = in_array($reviewStatus, ['approved', 'rejected'], true)
            && $this->reviewDecisions->latestForDigest((int) $package['id'], $digest) === null;

        if ($needsHydration || $needsReviewDecision) {
            $manifestJson = json_encode($manifestPayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $this->db->transaction(function () use (
                $release,
                $package,
                $registry,
                $manifestJson,
                $signature,
                $keyId,
                $reviewStatus,
                $decidedAt,
                $digest,
                $document,
                $fetched,
                $needsHydration,
                $needsReviewDecision,
            ): void {
                if ($needsHydration) {
                    $this->releases->hydrateSignedMetadata((int) $release['id'], $manifestJson, $signature, $keyId, $reviewStatus);
                }

                if ($needsReviewDecision) {
                    $this->reviewDecisions->record([
                        'package_id' => (int) $package['id'],
                        'release_id' => (int) $release['id'],
                        'version' => (string) $release['version'],
                        'digest' => $digest,
                        'decision' => $reviewStatus,
                        'decided_at' => $decidedAt,
                        'source' => 'release_document',
                        'evidence_json' => json_encode([
                            'format' => 'rb-release-envelope.v1',
                            'document' => $document,
                            'signature' => base64_encode($signature),
                            'key_id' => $keyId,
                        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    ]);
                }

                $this->transparency->record([
                    'package_uid' => (string) $package['package_uid'],
                    'version' => (string) $release['version'],
                    'digest' => $digest,
                    'event' => 'release_verified',
                    'source' => 'release_document',
                    'registry_id' => (int) $registry['id'],
                    'detail' => 'review=' . $reviewStatus,
                ]);

                $this->telemetry?->emit('package.release_verified', [
                    'package' => (string) $package['package_uid'],
                    'version' => (string) $release['version'],
                    'digest' => $digest,
                    'fetched' => $fetched,
                ]);
            });
        }

        return $manifest;
    }

    /**
     * @param array<string,mixed> $registry
     * @param array<string,mixed> $package
     * @param array<string,mixed> $release
     * @return array{0:string,1:string,2:string}
     */
    private function fetchEnvelope(array $registry, array $package, array $release): array
    {
        $result = $this->transport->fetch($this->releaseUrl($registry, $package, $release));
        if ($result->status !== 200 || $result->body === '') {
            throw new PackagePolicyException(
                'fetch_failed',
                'Could not fetch the release document (' . ($result->error ?? ('HTTP ' . $result->status)) . ').',
            );
        }

        $envelope = json_decode($result->body, true);
        if (
            !is_array($envelope)
            || ($envelope['format'] ?? null) !== 'rb-release-envelope.v1'
            || !is_string($envelope['document'] ?? null)
            || !is_string($envelope['signature'] ?? null)
            || !is_string($envelope['key_id'] ?? null)
        ) {
            throw new PackagePolicyException('fetch_failed', 'The release envelope is malformed.');
        }

        $signature = base64_decode($envelope['signature'], true);
        if ($signature === false) {
            throw new PackagePolicyException('fetch_failed', 'The release envelope signature is not valid base64.');
        }

        return [$envelope['document'], $signature, $envelope['key_id']];
    }

    /**
     * @param array<string,mixed> $registry
     * @param array<string,mixed> $package
     * @param array<string,mixed> $release
     */
    private function releaseUrl(array $registry, array $package, array $release): string
    {
        $base = rtrim((string) $registry['base_url'], '/');
        $sourceUrl = $release['source_url'] ?? null;
        $url = is_string($sourceUrl) && $sourceUrl !== ''
            ? $sourceUrl
            : $base . '/releases/' . (string) $package['package_uid'] . '/'
                . rawurlencode((string) $release['version']) . '/rb-release-envelope.v1.json';

        $baseParts = parse_url($base);
        $urlParts = parse_url($url);
        if (
            !is_array($baseParts)
            || !is_array($urlParts)
            || ($urlParts['scheme'] ?? '') !== ($baseParts['scheme'] ?? '-')
            || ($urlParts['host'] ?? '') !== ($baseParts['host'] ?? '-')
            || ($urlParts['port'] ?? null) !== ($baseParts['port'] ?? null)
        ) {
            throw new PackagePolicyException(
                'source_mismatch',
                'The release source URL is not same-origin with its pinned registry.',
            );
        }

        return $url;
    }

    private function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function dateTimeForDatabase(string $value): ?string
    {
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }
}
