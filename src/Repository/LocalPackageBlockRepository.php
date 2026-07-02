<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Registry-independent emergency blocklist (`local_package_blocks`, migration
 * 0049). A blocked digest or package identity refuses regardless of registry
 * availability or trust-root state.
 */
final class LocalPackageBlockRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM local_package_blocks ORDER BY id DESC');
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM local_package_blocks WHERE id = ?', [$id]);
    }

    public function add(?string $digest, ?string $packageUid, ?string $reason, ?int $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO local_package_blocks (digest, package_uid, reason, created_by) VALUES (?, ?, ?, ?)',
            [$digest, $packageUid, $reason, $createdBy],
        );
    }

    public function remove(int $id): void
    {
        $this->db->run('DELETE FROM local_package_blocks WHERE id = ?', [$id]);
    }

    /** True when the exact digest or the whole package identity is blocked. */
    public function isBlocked(?string $digest, ?string $packageUid): bool
    {
        if ($digest !== null) {
            $hit = $this->db->fetchValue('SELECT 1 FROM local_package_blocks WHERE digest = ? LIMIT 1', [$digest]);
            if ($hit !== false && $hit !== null) {
                return true;
            }
        }

        if ($packageUid !== null) {
            $hit = $this->db->fetchValue('SELECT 1 FROM local_package_blocks WHERE package_uid = ? LIMIT 1', [$packageUid]);
            if ($hit !== false && $hit !== null) {
                return true;
            }
        }

        return false;
    }
}
