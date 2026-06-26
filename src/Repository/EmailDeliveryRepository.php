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
    public function __construct(private Database $db)
    {
    }

    /**
     * Enqueue a send. Returns the new row id, or 0 when the idempotency key
     * already exists (the send was already queued — a no-op duplicate).
     */
    public function enqueue(?int $userId, string $email, string $kind, ?string $subject, ?string $idempotencyKey = null): int
    {
        $stmt = $this->db->run(
            'INSERT IGNORE INTO email_deliveries (user_id, email, kind, subject, status, idempotency_key, created_at)
             VALUES (:uid, :email, :kind, :subj, :status, :idem, UTC_TIMESTAMP())',
            [
                'uid' => $userId,
                'email' => $email,
                'kind' => $kind,
                'subj' => $subject,
                'status' => 'queued',
                'idem' => $idempotencyKey,
            ],
        );
        return $stmt->rowCount() > 0 ? (int) $this->db->pdo()->lastInsertId() : 0;
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
            "SELECT * FROM email_deliveries WHERE status = 'queued' ORDER BY id ASC LIMIT " . $limit,
        );
    }

    public function markSent(int $id, ?string $messageId = null): void
    {
        $this->db->run(
            "UPDATE email_deliveries SET status = 'sent', sent_at = UTC_TIMESTAMP(), message_id = ? WHERE id = ?",
            [$messageId, $id],
        );
    }

    public function markFailed(int $id, string $error): void
    {
        $this->db->run(
            "UPDATE email_deliveries SET status = 'failed', error = ? WHERE id = ?",
            [substr($error, 0, 255), $id],
        );
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
}
