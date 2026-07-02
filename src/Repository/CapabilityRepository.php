<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Thin wrapper over the capabilities catalogue. Capability meaning stays
 * code-owned; rows are seeded from CapabilityCatalog.
 */
final class CapabilityRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM capabilities WHERE retired_at IS NULL ORDER BY id ASC');
    }

    /**
     * @param list<string> $keys
     * @return array<string,int>
     */
    public function idsByKeys(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $in = implode(',', array_fill(0, count($keys), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id, capability_key FROM capabilities WHERE capability_key IN ($in)",
            array_values($keys),
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['capability_key']] = (int) $row['id'];
        }

        return $out;
    }
}
