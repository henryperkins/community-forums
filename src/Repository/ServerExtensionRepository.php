<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class ServerExtensionRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @param array<string,mixed> $handler */
    public function upsertHandler(int $installedPackageId, array $handler): int
    {
        $this->db->run(
            'INSERT INTO server_extension_handlers
               (installed_package_id, handler_key, entrypoint, events_json, jobs_json, permissions_json,
                resource_limits_json, storage_quota_bytes, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'enabled\', UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
               entrypoint = VALUES(entrypoint),
               events_json = VALUES(events_json),
               jobs_json = VALUES(jobs_json),
               permissions_json = VALUES(permissions_json),
               resource_limits_json = VALUES(resource_limits_json),
               storage_quota_bytes = VALUES(storage_quota_bytes),
               updated_at = UTC_TIMESTAMP()',
            [
                $installedPackageId,
                (string) $handler['handler_key'],
                (string) $handler['entrypoint'],
                $this->json($handler['events'] ?? []),
                $this->json($handler['jobs'] ?? []),
                $this->json($handler['permissions'] ?? []),
                $this->json($handler['resource_limits'] ?? []),
                (int) ($handler['storage_quota_bytes'] ?? 0),
            ],
        );
        return (int) $this->db->fetchValue(
            'SELECT id FROM server_extension_handlers WHERE installed_package_id = ? AND handler_key = ?',
            [$installedPackageId, (string) $handler['handler_key']],
        );
    }

    /** @param array<string,mixed> $payload */
    public function enqueue(int $handlerId, string $eventName, array $payload, int $maxAttempts = 3): int
    {
        return $this->db->insert(
            'INSERT INTO server_extension_jobs
               (handler_id, event_name, payload_json, max_attempts, available_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [$handlerId, $eventName, $this->json($payload), max(1, $maxAttempts)],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function claim(int $limit): array
    {
        $limit = max(1, $limit);
        $rows = $this->db->fetchAll(
            "SELECT j.*, h.handler_key, h.entrypoint, h.permissions_json, h.resource_limits_json,
                    h.storage_quota_bytes, h.installed_package_id
             FROM server_extension_jobs j
             JOIN server_extension_handlers h ON h.id = j.handler_id
             JOIN installed_packages ip ON ip.id = h.installed_package_id
             WHERE j.status = 'queued'
               AND j.available_at <= UTC_TIMESTAMP()
               AND h.status = 'enabled'
               AND ip.state = 'enabled'
             ORDER BY j.available_at ASC, j.id ASC
             LIMIT " . $limit,
        );
        foreach ($rows as $row) {
            $this->db->run(
                "UPDATE server_extension_jobs SET status = 'running', locked_at = UTC_TIMESTAMP(), attempts = attempts + 1 WHERE id = ? AND status = 'queued'",
                [(int) $row['id']],
            );
        }
        return array_map(fn (array $row): array => $this->decodeJob($row), $rows);
    }

    /** @param array<string,mixed> $result */
    public function recordRun(array $job, array $result): void
    {
        $status = (string) ($result['status'] ?? 'failed');
        if (!in_array($status, ['succeeded', 'failed', 'timeout', 'quarantined'], true)) {
            $status = 'failed';
        }
        $this->db->run(
            'INSERT INTO server_extension_runs
               (job_id, handler_id, status, exit_code, duration_ms, output_bytes, stdout_json, error, started_at, finished_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [
                (int) $job['id'],
                (int) $job['handler_id'],
                $status,
                $result['exit_code'] ?? null,
                (int) ($result['duration_ms'] ?? 0),
                (int) ($result['output_bytes'] ?? 0),
                $this->json($result['stdout_json'] ?? null),
                isset($result['error']) ? substr((string) $result['error'], 0, 255) : null,
            ],
        );
        if ($status === 'succeeded') {
            $this->db->run("UPDATE server_extension_jobs SET status = 'succeeded', updated_at = UTC_TIMESTAMP() WHERE id = ?", [(int) $job['id']]);
            return;
        }
        if ($status === 'quarantined') {
            $this->db->run(
                "UPDATE server_extension_jobs
                 SET status = 'quarantined', last_error = ?, updated_at = UTC_TIMESTAMP()
                 WHERE id = ?",
                [isset($result['error']) ? substr((string) $result['error'], 0, 255) : 'extension quarantined', (int) $job['id']],
            );
            return;
        }
        $this->db->run(
            "UPDATE server_extension_jobs
             SET status = IF(attempts >= max_attempts, 'failed', 'queued'),
                 last_error = ?, available_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 300 SECOND),
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ?",
            [isset($result['error']) ? substr((string) $result['error'], 0, 255) : 'extension failed', (int) $job['id']],
        );
    }

    public function quarantineHandler(int $handlerId, string $reason): void
    {
        $this->db->run(
            "UPDATE server_extension_handlers SET status = 'quarantined', quarantine_reason = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?",
            [substr($reason, 0, 255), $handlerId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function handlers(): array
    {
        return $this->db->fetchAll(
            'SELECT h.*, p.package_uid, p.name AS package_name, ip.state AS install_state
             FROM server_extension_handlers h
             JOIN installed_packages ip ON ip.id = h.installed_package_id
             JOIN packages p ON p.id = ip.package_id
             ORDER BY h.updated_at DESC, h.id DESC',
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function recentRuns(int $limit = 50): array
    {
        return $this->db->fetchAll(
            'SELECT r.*, h.handler_key
             FROM server_extension_runs r
             JOIN server_extension_handlers h ON h.id = r.handler_id
             ORDER BY r.id DESC
             LIMIT ' . max(1, $limit),
        );
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null';
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function decodeJob(array $row): array
    {
        foreach (['payload_json', 'permissions_json', 'resource_limits_json'] as $key) {
            if (isset($row[$key]) && is_string($row[$key])) {
                $row[$key] = json_decode($row[$key], true) ?: [];
            }
        }
        return $row;
    }
}
