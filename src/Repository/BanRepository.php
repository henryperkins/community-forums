<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Live site restrictions; history and mutation remain in the moderation service. */
final class BanRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function activeSiteRestrictionsForUpdate(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM bans WHERE user_id = ? AND scope = 'site' AND lifted_at IS NULL
             AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP()) ORDER BY id ASC FOR UPDATE",
            [$userId],
        );
    }
}
