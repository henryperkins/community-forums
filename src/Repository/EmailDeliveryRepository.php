<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Durable email outbox + delivery log (P2-04). Instant sends carry an
 * idempotency_key (post_id:user_id) under the unique index uq_deliv_idem, so
 * the same (post, recipient) can be queued at most once even across retries
 * (DESIGN §9.6). digest/test/system sends use a NULL key (InnoDB allows
 * multiple NULLs).
 */
final class EmailDeliveryRepository
{
    /** @var list<int> seconds after failed attempts 1-4; attempt 5 is terminal by default. */
    private const BACKOFF_SECONDS = [300, 900, 3600, 21600];

    public function __construct(private Database $db)
    {
    }

    /**
     * Enqueue a send. Returns the new row id, or 0 when the idempotency key
     * already exists (the send was already queued — a no-op duplicate).
     */
    /** @param array<string,mixed>|null $payload */
    public function enqueue(?int $userId, string $email, string $kind, ?string $subject, ?string $idempotencyKey = null, ?array $payload = null, int $maxAttempts = 5): int
    {
        $maxAttempts = max(1, min(255, $maxAttempts));
        $stmt = $this->db->run(
            'INSERT IGNORE INTO email_deliveries (user_id, email, kind, subject, payload, status, idempotency_key, max_attempts, created_at)
             VALUES (:uid, :email, :kind, :subj, :payload, :status, :idem, :max_attempts, UTC_TIMESTAMP())',
            [
                'uid' => $userId,
                'email' => $email,
                'kind' => $kind,
                'subj' => $subject,
                'payload' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'status' => 'queued',
                'idem' => $idempotencyKey,
                'max_attempts' => $maxAttempts,
            ],
        );
        return $stmt->rowCount() > 0 ? (int) $this->db->pdo()->lastInsertId() : 0;
    }

    /** @param array<string,mixed> $payload */
    public function enqueueSystemForActiveUsers(int $actorId, string $subject, array $payload, string $idempotencyPrefix): int
    {
        return $this->db->run(
            'INSERT IGNORE INTO email_deliveries (user_id, email, kind, subject, payload, status, idempotency_key, max_attempts, created_at)
             SELECT u.id, u.email, "system", :subject, :payload, "queued", CONCAT(:prefix, u.id), 5, UTC_TIMESTAMP()
             FROM users u
             WHERE u.status = "active" AND u.id <> :actor',
            [
                'subject' => $subject,
                'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'prefix' => $idempotencyPrefix,
                'actor' => $actorId,
            ],
        )->rowCount();
    }

    /** Mark an already-enqueued row suppressed without sending (recipient on the suppression list). */
    public function markSuppressed(int $id): void
    {
        $this->db->run("UPDATE email_deliveries SET status = 'suppressed' WHERE id = ?", [$id]);
    }

    /**
     * Try to become the sole outbox drainer. Uses a connection-scoped MySQL
     * advisory lock (auto-released when the connection closes) so two concurrent
     * or overlapping worker runs cannot both send the same queued row. Returns
     * false when another worker already holds it.
     */
    public function acquireDrainLock(): bool
    {
        return (int) $this->db->fetchValue("SELECT GET_LOCK('rb_email_outbox', 0)") === 1;
    }

    public function releaseDrainLock(): void
    {
        $this->db->run("SELECT RELEASE_LOCK('rb_email_outbox')");
    }

    /** @return array<int,array<string,mixed>> oldest queued sends, for the worker */
    public function pending(int $limit = 50): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT * FROM email_deliveries
             WHERE status = 'queued'
               AND (next_attempt_at IS NULL OR next_attempt_at <= UTC_TIMESTAMP())
             ORDER BY COALESCE(next_attempt_at, created_at) ASC, id ASC
             LIMIT " . $limit,
        );
    }

    public function markSent(int $id, ?string $messageId = null): void
    {
        $this->db->run(
            "UPDATE email_deliveries
             SET status = 'sent', sent_at = UTC_TIMESTAMP(), message_id = ?,
                 attempt_count = attempt_count + 1, last_attempt_at = UTC_TIMESTAMP(),
                 next_attempt_at = NULL, error = NULL
             WHERE id = ?",
            [$messageId, $id],
        );
    }

    public function markFailed(int $id, string $error): void
    {
        $this->db->run(
            "UPDATE email_deliveries
             SET status = 'failed', error = ?, attempt_count = attempt_count + 1,
                 last_attempt_at = UTC_TIMESTAMP(), next_attempt_at = NULL
             WHERE id = ?",
            [substr($error, 0, 255), $id],
        );
    }

    /** Record a failed send attempt, keeping it queued until attempts are exhausted. */
    public function markAttemptFailed(int $id, string $error): string
    {
        $row = $this->find($id);
        if ($row === null) {
            return 'failed';
        }

        $nextAttempt = ((int) ($row['attempt_count'] ?? 0)) + 1;
        $maxAttempts = max(1, (int) ($row['max_attempts'] ?? 1));
        if ($nextAttempt >= $maxAttempts) {
            $this->db->run(
                "UPDATE email_deliveries
                 SET status = 'failed', attempt_count = attempt_count + 1,
                     last_attempt_at = UTC_TIMESTAMP(), next_attempt_at = NULL, error = ?
                 WHERE id = ?",
                [substr($error, 0, 255), $id],
            );
            return 'failed';
        }

        $delay = self::BACKOFF_SECONDS[min($nextAttempt - 1, count(self::BACKOFF_SECONDS) - 1)];
        $this->db->run(
            "UPDATE email_deliveries
             SET status = 'queued', attempt_count = attempt_count + 1,
                 last_attempt_at = UTC_TIMESTAMP(), next_attempt_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND),
                 error = ?
             WHERE id = ?",
            [$delay, substr($error, 0, 255), $id],
        );
        return 'queued';
    }

    public function markQueuedBlocked(string $reason): int
    {
        return $this->db->run(
            "UPDATE email_deliveries SET error = ? WHERE status = 'queued'",
            [substr($reason, 0, 255)],
        )->rowCount();
    }

    /** @return array<string,int> status => count, for queue observability */
    public function statusCounts(): array
    {
        $rows = $this->db->fetchAll('SELECT status, COUNT(*) AS n FROM email_deliveries GROUP BY status');
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['status']] = (int) $r['n'];
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM email_deliveries WHERE id = ?', [$id]);
    }

    /**
     * Filtered, paginated delivery log for the admin dashboard. LIMIT/OFFSET are
     * clamped + concatenated (never bound: EMULATE_PREPARES=false); filters bind.
     *
     * @return array<int,array<string,mixed>>
     */
    public function recent(int $limit, int $offset, ?string $status = null, ?string $kind = null, ?string $email = null): array
    {
        $limit = max(1, min(10000, $limit));
        $offset = max(0, $offset);
        $where = [];
        $params = [];
        if ($status !== null && $status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($kind !== null && $kind !== '') {
            $where[] = 'kind = :kind';
            $params['kind'] = $kind;
        }
        if ($email !== null && $email !== '') {
            $where[] = 'email LIKE :email';
            $params['email'] = '%' . $email . '%';
        }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        return $this->db->fetchAll(
            'SELECT id, user_id, email, kind, subject, status, attempt_count, max_attempts, last_attempt_at, next_attempt_at,
                    error, message_id, created_at, sent_at
             FROM email_deliveries' . $clause . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params,
        );
    }

    public function count(?string $status = null, ?string $kind = null, ?string $email = null): int
    {
        $where = [];
        $params = [];
        if ($status !== null && $status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($kind !== null && $kind !== '') {
            $where[] = 'kind = :kind';
            $params['kind'] = $kind;
        }
        if ($email !== null && $email !== '') {
            $where[] = 'email LIKE :email';
            $params['email'] = '%' . $email . '%';
        }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM email_deliveries' . $clause, $params);
    }

    /** Re-queue a failed send for the worker. Returns rows affected (0 if not failed). */
    public function requeue(int $id): int
    {
        return $this->db->run(
            "UPDATE email_deliveries
             SET status = 'queued', attempt_count = 0, last_attempt_at = NULL,
                 next_attempt_at = NULL, error = NULL
             WHERE id = ? AND status = 'failed'",
            [$id],
        )->rowCount();
    }
}
