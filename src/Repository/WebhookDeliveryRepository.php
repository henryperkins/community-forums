<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Durable webhook-delivery ledger with retry/backoff/dead-letter state. */
final class WebhookDeliveryRepository
{
    public function __construct(private Database $db)
    {
    }

    public function enqueue(int $webhookId, string $eventType, string $eventId, string $payloadJson, int $maxAttempts): int
    {
        $stmt = $this->db->run(
            "INSERT IGNORE INTO webhook_deliveries
                (webhook_id, event_type, event_id, payload, status, attempt_count, max_attempts, created_at)
             VALUES (:wid, :etype, :eid, :payload, 'queued', 0, :maxa, UTC_TIMESTAMP())",
            ['wid' => $webhookId, 'etype' => $eventType, 'eid' => $eventId, 'payload' => $payloadJson, 'maxa' => $maxAttempts],
        );
        return $stmt->rowCount() > 0 ? (int) $this->db->pdo()->lastInsertId() : 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function claim(int $limit): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT d.*, w.url AS url, w.secret_ref AS secret_ref, w.consecutive_failures AS consecutive_failures
             FROM webhook_deliveries d
             JOIN webhooks w ON w.id = d.webhook_id
             WHERE d.status = 'queued'
               AND w.is_active = 1
               AND (d.next_attempt_at IS NULL OR d.next_attempt_at <= UTC_TIMESTAMP())
             ORDER BY d.next_attempt_at ASC, d.id ASC
             LIMIT " . $limit,
        );
    }

    public function markDelivered(int $id, int $httpStatus): void
    {
        $this->db->run(
            "UPDATE webhook_deliveries
             SET status = 'delivered', delivered_at = UTC_TIMESTAMP(), last_attempt_at = UTC_TIMESTAMP(),
                 attempt_count = attempt_count + 1, response_status = ?, error = NULL, next_attempt_at = NULL
             WHERE id = ?",
            [$httpStatus, $id],
        );
    }

    public function recordFailure(int $id, ?int $httpStatus, string $error, ?string $nextAttemptAt, bool $dead): void
    {
        $this->db->run(
            'UPDATE webhook_deliveries
             SET status = ?, attempt_count = attempt_count + 1, last_attempt_at = UTC_TIMESTAMP(),
                 response_status = ?, error = ?, next_attempt_at = ?
             WHERE id = ?',
            [$dead ? 'dead' : 'queued', $httpStatus, substr($error, 0, 255), $nextAttemptAt, $id],
        );
    }

    public function requeue(int $webhookId, int $deliveryId): int
    {
        return $this->db->run(
            "UPDATE webhook_deliveries
             SET status = 'queued', attempt_count = 0, next_attempt_at = NULL,
                 error = NULL, response_status = NULL, last_attempt_at = NULL
             WHERE id = ? AND webhook_id = ? AND status = 'dead'",
            [$deliveryId, $webhookId],
        )->rowCount();
    }

    /** @return array<int,array<string,mixed>> */
    public function listForWebhook(int $webhookId, int $limit = 50): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            'SELECT * FROM webhook_deliveries WHERE webhook_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$webhookId],
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM webhook_deliveries WHERE id = ?', [$id]);
    }

    public function acquireDrainLock(): bool
    {
        return (int) $this->db->fetchValue("SELECT GET_LOCK('rb_webhook_outbox', 0)") === 1;
    }

    public function releaseDrainLock(): void
    {
        $this->db->run("SELECT RELEASE_LOCK('rb_webhook_outbox')");
    }

    /** @return array<string,int> */
    public function statusCounts(): array
    {
        $rows = $this->db->fetchAll('SELECT status, COUNT(*) AS n FROM webhook_deliveries GROUP BY status');
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['status']] = (int) $r['n'];
        }
        return $out;
    }
}
