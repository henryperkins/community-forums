<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class AccountDeletionRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function pendingForUser(int $userId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM account_deletion_requests WHERE user_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
            [$userId],
        );
    }

    public function create(int $userId, int $requestedBy, string $purgeAfter, ?string $reason = null): int
    {
        return $this->db->insert(
            'INSERT INTO account_deletion_requests (user_id, requested_by, status, requested_at, purge_after, reason)
             VALUES (?, ?, \'pending\', UTC_TIMESTAMP(), ?, ?)',
            [$userId, $requestedBy, $purgeAfter, $reason],
        );
    }

    public function cancel(int $id, int $canceledBy): bool
    {
        return $this->db->run(
            "UPDATE account_deletion_requests
             SET status = 'canceled', canceled_at = UTC_TIMESTAMP(), canceled_by = ?
             WHERE id = ? AND status = 'pending'",
            [$canceledBy, $id],
        )->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function due(int $limit = 100): array
    {
        $limit = max(1, min(1000, $limit));
        return $this->db->fetchAll(
            "SELECT * FROM account_deletion_requests
             WHERE status = 'pending' AND purge_after <= UTC_TIMESTAMP()
             ORDER BY purge_after ASC, id ASC
             LIMIT " . $limit,
        );
    }

    public function markPurged(int $id): bool
    {
        return $this->db->run(
            "UPDATE account_deletion_requests
             SET status = 'purged', purged_at = UTC_TIMESTAMP()
             WHERE id = ? AND status = 'pending'",
            [$id],
        )->rowCount() > 0;
    }
}
