<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class SessionRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @param array{id:string,user_id:int,csrf_secret:string,user_agent:?string,expires_at:string,ip?:?string} $data
     */
    public function create(array $data): void
    {
        // sessions.ip is VARBINARY(16) — store packed (inet_pton), NULL if absent/invalid.
        $ip = isset($data['ip']) && is_string($data['ip']) && $data['ip'] !== ''
            ? (@inet_pton($data['ip']) ?: null)
            : null;

        $this->db->run(
            'INSERT INTO sessions (id, user_id, csrf_secret, ip, user_agent, created_at, last_seen_at, expires_at)
             VALUES (:id, :user_id, :csrf_secret, :ip, :user_agent, UTC_TIMESTAMP(), UTC_TIMESTAMP(), :expires_at)',
            [
                'id' => $data['id'],
                'user_id' => $data['user_id'],
                'csrf_secret' => $data['csrf_secret'],
                'ip' => $ip,
                'user_agent' => $data['user_agent'],
                'expires_at' => $data['expires_at'],
            ],
        );
    }

    /** @return array<string,mixed>|null Active (unrevoked, unexpired) session by id. */
    public function findActive(string $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM sessions WHERE id = ? AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            [$id],
        );
    }

    public function touch(string $id): void
    {
        $this->db->run('UPDATE sessions SET last_seen_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    public function revoke(string $id): void
    {
        $this->db->run('UPDATE sessions SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND revoked_at IS NULL', [$id]);
    }

    public function revokeAllForUser(int $userId): void
    {
        $this->db->run('UPDATE sessions SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL', [$userId]);
    }

    // ---- Active sessions & devices (USER §3.3, P2-10) ---------------------

    /**
     * Active (unrevoked, unexpired) sessions for a user, newest activity first.
     * ip is returned as a human-readable string (VARBINARY(16) → inet_ntop).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listActiveForUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, user_agent, INET6_NTOA(ip) AS ip, created_at, last_seen_at, expires_at
             FROM sessions
             WHERE user_id = ? AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()
             ORDER BY last_seen_at DESC, created_at DESC',
            [$userId],
        );
        return $rows;
    }

    /** Revoke one session, but only if it belongs to $userId. @return bool revoked */
    public function revokeForUser(string $id, int $userId): bool
    {
        return $this->db->run(
            'UPDATE sessions SET revoked_at = UTC_TIMESTAMP()
             WHERE id = ? AND user_id = ? AND revoked_at IS NULL',
            [$id, $userId],
        )->rowCount() > 0;
    }

    /** "Log out everywhere else": revoke all of the user's sessions except the current one. */
    public function revokeOthersForUser(int $userId, string $exceptId): void
    {
        $this->db->run(
            'UPDATE sessions SET revoked_at = UTC_TIMESTAMP()
             WHERE user_id = ? AND id <> ? AND revoked_at IS NULL',
            [$userId, $exceptId],
        );
    }
}
