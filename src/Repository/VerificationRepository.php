<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Single-use, time-boxed verification tokens (Phase-1 `verifications` table,
 * dormant until Phase 2). Only the SHA-256 hash of a token is stored, so a
 * database leak never yields a usable link. Used by password-reset (and, later,
 * email-verify / email-change), which all share the `type` enum.
 */
final class VerificationRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return int new verification id */
    public function create(int $userId, string $type, string $tokenHash, string $expiresAt, ?string $newEmail = null): int
    {
        return $this->db->insert(
            'INSERT INTO verifications (user_id, type, token_hash, new_email, expires_at)
             VALUES (?, ?, ?, ?, ?)',
            [$userId, $type, $tokenHash, $newEmail, $expiresAt],
        );
    }

    /**
     * The row for an unused, unexpired token of this type, or null.
     *
     * @return array<string,mixed>|null
     */
    public function findValid(string $tokenHash, string $type): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM verifications
             WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()
             LIMIT 1',
            [$tokenHash, $type],
        );
    }

    /**
     * Consume a token. Returns the number of rows updated (0 if it was already
     * used) so the caller can treat a single-use race as a failed reset.
     */
    public function markUsed(int $id): int
    {
        return $this->db->run(
            'UPDATE verifications SET used_at = UTC_TIMESTAMP() WHERE id = ? AND used_at IS NULL',
            [$id],
        )->rowCount();
    }

    /** Invalidate every outstanding token of a type for a user (one live link at a time). */
    public function invalidateOutstanding(int $userId, string $type): void
    {
        $this->db->run(
            'UPDATE verifications SET used_at = UTC_TIMESTAMP()
             WHERE user_id = ? AND type = ? AND used_at IS NULL',
            [$userId, $type],
        );
    }
}
