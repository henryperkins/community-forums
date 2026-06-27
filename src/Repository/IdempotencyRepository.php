<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use PDOException;

/**
 * At-most-once submission keys (P3-03). A composer submit carries a client
 * idempotency token; the first commit records (user, hashed key) → result here
 * in the same transaction. A duplicate (double-click, retry, browser resend)
 * collides on the unique key and replays the original result instead of creating
 * a second post (PHASE_3_PLAN §8.5).
 */
final class IdempotencyRepository
{
    public function __construct(private Database $db)
    {
    }

    /** Hash a raw client token; null/blank/oversize tokens disable idempotency. */
    public function hash(mixed $token): ?string
    {
        if (!is_string($token)) {
            return null;
        }
        $token = trim($token);
        if ($token === '' || strlen($token) > 200) {
            return null;
        }
        return hash('sha256', $token);
    }

    /** @return array{result_type:string,result_id:int}|null */
    public function find(int $userId, string $hashedKey): ?array
    {
        $row = $this->db->fetch(
            'SELECT result_type, result_id FROM submission_idempotency WHERE user_id = ? AND idem_key = ?',
            [$userId, $hashedKey],
        );
        if ($row === null) {
            return null;
        }
        return ['result_type' => (string) $row['result_type'], 'result_id' => (int) $row['result_id']];
    }

    /**
     * Record the result. Returns false if the key already existed (a concurrent
     * duplicate won the race), letting the caller replay instead.
     */
    public function record(int $userId, string $hashedKey, string $context, string $resultType, int $resultId): bool
    {
        try {
            $this->db->run(
                'INSERT INTO submission_idempotency (user_id, idem_key, context, result_type, result_id, created_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$userId, $hashedKey, $context, $resultType, $resultId],
            );
            return true;
        } catch (PDOException $e) {
            if ($this->isDuplicateKey($e)) {
                return false;
            }
            // The dedup design relies on a blocking INSERT against the winner's
            // still-uncommitted unique-key row. Under contention that can surface
            // as a lock-wait timeout / deadlock instead of a clean 1062 — treat it
            // as a lost race too, so the caller replays the original instead of 500.
            if ($this->isLockContention($e)) {
                return false;
            }
            throw $e;
        }
    }

    /** Delete keys older than $days (housekeeping). */
    public function purgeOlderThan(int $days): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        return $this->db->run('DELETE FROM submission_idempotency WHERE created_at < ?', [$cutoff])->rowCount();
    }

    private function isDuplicateKey(PDOException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }

    /** MySQL lock-wait timeout (1205) or deadlock (1213). */
    private function isLockContention(PDOException $e): bool
    {
        return in_array($e->errorInfo[1] ?? null, [1205, 1213], true);
    }
}
