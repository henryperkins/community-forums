<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Append-only cache of signed review evidence an install relied on. */
final class PackageReviewDecisionRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @param array<string,mixed> $entry */
    public function record(array $entry): int
    {
        return $this->db->insert(
            'INSERT INTO package_review_decisions
                (package_id, release_id, version, digest, decision, decided_at, source, evidence_json)
             VALUES (:package_id, :release_id, :version, :digest, :decision, :decided_at, :source, :evidence_json)',
            [
                'package_id' => $entry['package_id'],
                'release_id' => $entry['release_id'] ?? null,
                'version' => $entry['version'],
                'digest' => $entry['digest'],
                'decision' => $entry['decision'],
                'decided_at' => $entry['decided_at'] ?? null,
                'source' => $entry['source'] ?? 'release_document',
                'evidence_json' => $entry['evidence_json'] ?? null,
            ],
        );
    }

    /** @return array<string,mixed>|null */
    public function latestForDigest(int $packageId, string $digest): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM package_review_decisions WHERE package_id = :package_id AND digest = :digest ORDER BY id DESC LIMIT 1',
            ['package_id' => $packageId, 'digest' => $digest],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forPackage(int $packageId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM package_review_decisions WHERE package_id = ? ORDER BY id DESC',
            [$packageId],
        );
    }
}
