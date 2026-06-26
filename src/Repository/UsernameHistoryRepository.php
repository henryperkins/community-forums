<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Username change history (USER §7). Enables safe old-profile redirects and a
 * moderation trail when a member renames. Only the most recent owner of a freed
 * handle is followed for redirects (the live users table always wins first).
 */
final class UsernameHistoryRepository
{
    public function __construct(private Database $db)
    {
    }

    public function record(int $userId, string $oldUsername): void
    {
        $this->db->run(
            'INSERT INTO username_history (user_id, old_username, changed_at)
             VALUES (?, ?, UTC_TIMESTAMP())',
            [$userId, $oldUsername],
        );
    }

    /**
     * The user who most recently vacated $oldUsername, for a redirect from a stale
     * profile link. Returns null when the handle was never used or is live again.
     */
    public function currentUserIdForOldUsername(string $oldUsername): ?int
    {
        $id = $this->db->fetchValue(
            'SELECT user_id FROM username_history WHERE old_username = ? ORDER BY changed_at DESC, id DESC LIMIT 1',
            [$oldUsername],
        );
        return $id === false || $id === null ? null : (int) $id;
    }

    /** @return array<int,array<string,mixed>> */
    public function listForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT old_username, changed_at FROM username_history WHERE user_id = ? ORDER BY changed_at DESC',
            [$userId],
        );
    }
}
