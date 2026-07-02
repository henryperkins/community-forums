<?php

declare(strict_types=1);

namespace App\Service\Registry;

use App\Core\Database;
use App\Core\Telemetry;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\Registry\RegistryVerificationException;
use App\Security\Registry\TrustChainVerifier;

/**
 * Signed-advisory ingest and evaluation.
 *
 * Advisory actions map to denormalized package/release statuses through an
 * escalate-only ladder. `force_disable` marks blocked in this increment; the
 * installed-package disable action lands with the install path in Inc 3.
 */
final class RegistryAdvisoryService
{
    public const ACTION_STATUS = [
        'warn' => 'warned',
        'block_new' => 'blocked',
        'force_disable' => 'blocked',
        'revoke' => 'revoked',
    ];

    private const RANK = ['none' => 0, 'warned' => 1, 'blocked' => 2, 'revoked' => 3];

    public function __construct(
        private Database $db,
        private TrustChainVerifier $verifier,
        private RegistryTrustKeyRepository $trustKeys,
        private PackageAdvisoryRepository $advisories,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private ModerationLogRepository $audit,
        private ?Telemetry $telemetry = null,
    ) {
    }

    public static function escalate(string $current, string $candidate): string
    {
        $currentRank = self::RANK[$current] ?? 0;
        $candidateRank = self::RANK[$candidate] ?? 0;

        return $candidateRank > $currentRank ? $candidate : $current;
    }

    public static function affectsVersion(?string $range, string $version): bool
    {
        $range = trim((string) $range);
        if ($range === '' || $range === '*') {
            return true;
        }
        if (preg_match('/^(<=|>=|<|>|=)?\s*(\d+\.\d+\.\d+(?:-[0-9A-Za-z][0-9A-Za-z.\-]*)?)$/', $range, $m) !== 1) {
            return true;
        }

        $op = $m[1] === '' ? '=' : $m[1];
        $bound = $m[2];

        return match ($op) {
            '<=' => version_compare($version, $bound, '<='),
            '<' => version_compare($version, $bound, '<'),
            '>=' => version_compare($version, $bound, '>='),
            '>' => version_compare($version, $bound, '>'),
            default => version_compare($version, $bound, '=='),
        };
    }

    /**
     * @param ?int $operatorId non-null only on the manual admin-console path,
     *   which writes an `advisory_ingest` audit row; the worker fetch path
     *   passes null (the signature itself is the authorization, and per-fetch
     *   auditing would flood the log).
     * @return array{advisory_id:int,action:string}
     */
    public function ingest(
        int $registryId,
        string $documentJson,
        string $signature,
        string $keyId,
        ?\DateTimeImmutable $now = null,
        ?int $operatorId = null,
    ): array {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $doc = $this->verifier->verify(
            $documentJson,
            $signature,
            $keyId,
            $this->trustKeys->forRegistry($registryId),
            'rb-advisory.v1',
            $now,
        );

        $advisoryUid = trim((string) ($doc->payload['advisory_uid'] ?? ''));
        $action = (string) ($doc->payload['action'] ?? '');
        $severity = (string) ($doc->payload['severity'] ?? 'medium');
        if ($advisoryUid === '' || !isset(self::ACTION_STATUS[$action]) || !in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            throw new RegistryVerificationException('malformed_advisory', 'Advisory must carry advisory_uid, a known action, and a known severity.');
        }

        // Source pinning for advisories (mirrors the snapshot uid_conflict rule):
        // a registry may only touch advisories/packages it already owns, so a
        // second pinned registry cannot overwrite or de-escalate another
        // registry's advisory by re-signing the same advisory_uid.
        $existing = $this->advisories->findByUid($advisoryUid);
        if ($existing !== null && $existing['registry_id'] !== null && (int) $existing['registry_id'] !== $registryId) {
            throw new RegistryVerificationException('advisory_registry_conflict', "Advisory $advisoryUid is owned by another registry; refusing cross-registry overwrite.");
        }

        $packageUid = trim((string) ($doc->payload['package_uid'] ?? ''));
        $package = $packageUid === '' ? null : $this->packages->findByUid($packageUid);
        if ($package !== null && $package['registry_id'] !== null && (int) $package['registry_id'] !== $registryId) {
            throw new RegistryVerificationException('advisory_package_conflict', "Advisory targets '$packageUid', which is pinned to another registry.");
        }

        $range = isset($doc->payload['affected_version_range']) ? (string) $doc->payload['affected_version_range'] : null;
        $affectedDigest = isset($doc->payload['affected_digest']) ? strtolower((string) $doc->payload['affected_digest']) : null;
        $affectedDigest = $affectedDigest === '' ? null : $affectedDigest;
        $issuedAt = $this->parseIssuedAt($doc->payload['issued_at'] ?? null);

        $result = $this->db->transaction(function () use ($registryId, $advisoryUid, $package, $range, $affectedDigest, $severity, $action, $doc, $documentJson, $issuedAt, $operatorId): array {
            $advisoryId = $this->advisories->upsert([
                'advisory_uid' => $advisoryUid,
                'registry_id' => $registryId,
                'package_id' => $package === null ? null : (int) $package['id'],
                'affected_version_range' => $range,
                'affected_digest' => $affectedDigest,
                'severity' => $severity,
                'action' => $action,
                'summary' => isset($doc->payload['summary']) ? mb_substr((string) $doc->payload['summary'], 0, 512) : null,
                'signed_evidence' => $documentJson,
                'issued_at' => $issuedAt,
            ]);

            if ($package !== null) {
                $this->evaluatePackage((int) $package['id']);
            }

            if ($operatorId !== null) {
                $this->audit->log([
                    'actor_id' => $operatorId,
                    'action' => 'advisory_ingest',
                    'target_type' => 'package',
                    'target_id' => $package === null ? 0 : (int) $package['id'],
                    'reason' => $advisoryUid,
                ]);
            }

            return ['advisory_id' => $advisoryId, 'action' => $action];
        });

        $this->telemetry?->emit('registry.advisory', [
            'advisory' => $advisoryUid,
            'action' => $action,
            'severity' => $severity,
            'package' => $packageUid !== '' ? $packageUid : null,
            'resolved' => $package !== null,
        ]);

        return $result;
    }

    public function evaluatePackage(int $packageId): void
    {
        $rows = $this->advisories->forPackage($packageId);

        $packageStatus = 'none';
        foreach ($rows as $advisory) {
            $packageStatus = self::escalate($packageStatus, self::ACTION_STATUS[(string) $advisory['action']] ?? 'none');
        }
        $this->packages->setAdvisoryStatus($packageId, $packageStatus);

        foreach ($this->releases->forPackage($packageId) as $release) {
            $status = 'none';
            foreach ($rows as $advisory) {
                $digest = $advisory['affected_digest'] ?? null;
                $hit = $digest !== null
                    ? hash_equals((string) $digest, (string) $release['digest'])
                    : self::affectsVersion($advisory['affected_version_range'] ?? null, (string) $release['version']);
                if ($hit) {
                    $status = self::escalate($status, self::ACTION_STATUS[(string) $advisory['action']] ?? 'none');
                }
            }
            $this->releases->setAdvisoryStatus((int) $release['id'], $status);
        }
    }

    public function acknowledge(User $admin, int $advisoryId): void
    {
        $advisory = $this->db->fetch('SELECT * FROM package_advisories WHERE id = ?', [$advisoryId]);
        if ($advisory === null) {
            throw new ValidationException(['advisory' => 'Advisory not found.']);
        }

        $this->db->transaction(function () use ($admin, $advisory, $advisoryId): void {
            $this->advisories->acknowledge($advisoryId, $admin->id());
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'advisory_ack',
                'target_type' => 'package',
                'target_id' => (int) ($advisory['package_id'] ?? 0),
                'reason' => (string) $advisory['advisory_uid'],
            ]);
        });
    }

    private function parseIssuedAt(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            // setTimezone before format: an embedded offset (e.g. "...-12:00")
            // is honored by the constructor and would otherwise be stored as
            // wall-clock, not UTC.
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }
}
