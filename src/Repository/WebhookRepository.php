<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Single-table SQL for webhook endpoint config. */
final class WebhookRepository
{
    public function __construct(private Database $db)
    {
    }

    public function insert(string $name, string $url, string $eventsJson, string $secretRef, int $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO webhooks (name, url, events, secret_ref, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [$name, $url, $eventsJson, $secretRef, $createdBy],
        );
    }

    public function setSecretRef(int $id, string $ref): void
    {
        $this->db->run('UPDATE webhooks SET secret_ref = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$ref, $id]);
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM webhooks WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function list(): array
    {
        return $this->db->fetchAll(
            'SELECT id, name, url, events, is_active, consecutive_failures, disabled_at, disabled_reason,
                    last_status, last_delivered_at, created_at, updated_at
             FROM webhooks ORDER BY id DESC',
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function activeEndpoints(): array
    {
        return $this->db->fetchAll('SELECT * FROM webhooks WHERE is_active = 1 ORDER BY id ASC');
    }

    public function update(int $id, string $name, string $url, string $eventsJson): void
    {
        $this->db->run(
            'UPDATE webhooks SET name = ?, url = ?, events = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$name, $url, $eventsJson, $id],
        );
    }

    public function enable(int $id): int
    {
        return $this->db->run(
            'UPDATE webhooks
             SET is_active = 1, disabled_at = NULL, disabled_reason = NULL,
                 consecutive_failures = 0, updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND is_active = 0',
            [$id],
        )->rowCount();
    }

    public function disable(int $id, string $reason): int
    {
        return $this->db->run(
            'UPDATE webhooks
             SET is_active = 0, disabled_at = UTC_TIMESTAMP(), disabled_reason = ?,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND is_active = 1',
            [substr($reason, 0, 190), $id],
        )->rowCount();
    }

    public function delete(int $id): int
    {
        return $this->db->run('DELETE FROM webhooks WHERE id = ?', [$id])->rowCount();
    }

    public function incrementConsecutiveFailures(int $id): void
    {
        $this->db->run('UPDATE webhooks SET consecutive_failures = consecutive_failures + 1 WHERE id = ?', [$id]);
    }

    public function resetConsecutiveFailures(int $id): void
    {
        $this->db->run('UPDATE webhooks SET consecutive_failures = 0 WHERE id = ?', [$id]);
    }

    public function setLastStatus(int $id, ?int $status, bool $deliveredNow): void
    {
        if ($deliveredNow) {
            $this->db->run(
                'UPDATE webhooks SET last_status = ?, last_delivered_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [$status, $id],
            );
            return;
        }

        $this->db->run(
            'UPDATE webhooks SET last_status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$status, $id],
        );
    }
}
