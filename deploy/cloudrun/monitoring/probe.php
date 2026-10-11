<?php

declare(strict_types=1);

// Operations-only collector. It never invokes a worker, sends mail, or fetches
// message/recipient fields. configure.py supplies this file as php -r source so
// it can run with the current immutable production image before the next build.

function monitoringLastDue(string $job, int $now): int
{
    return match ($job) {
        'retroboards-cron-5m' => intdiv($now, 300) * 300,
        'retroboards-cron-6h' => intdiv($now, 21600) * 21600,
        'retroboards-cron-0310', 'retroboards-cron-0700' => (static function () use ($job, $now): int {
            $hour = $job === 'retroboards-cron-0310' ? '03:10:00' : '07:00:00';
            $today = strtotime(gmdate('Y-m-d', $now) . ' ' . $hour . ' UTC');
            return $today <= $now ? $today : $today - 86400;
        })(),
        default => throw new RuntimeException('unknown_job'),
    };
}

/** @param list<array<string,mixed>> $executions */
function monitoringLatestScheduledSuccess(array $executions, string $scheduler): ?int
{
    $latest = null;
    foreach ($executions as $execution) {
        if (($execution['creator'] ?? '') !== $scheduler || empty($execution['completionTime'])) {
            continue;
        }
        foreach ($execution['conditions'] ?? [] as $condition) {
            if (($condition['type'] ?? '') === 'Completed' && ($condition['state'] ?? '') === 'CONDITION_SUCCEEDED') {
                $time = strtotime((string) $execution['completionTime']);
                if ($time === false) {
                    throw new RuntimeException('invalid_completion_time');
                }
                $latest = max($latest ?? 0, $time);
            }
        }
    }
    return $latest;
}

function monitoringMissedSchedule(string $job, int $now, int $created, ?int $last): bool
{
    // Evaluate the latest grace-expired slot, so an upcoming frequent tick
    // cannot hide an older miss. Pre-creation slots are never required.
    $required = monitoringLastDue($job, $now - 1800);
    return $required >= $created && ($last === null || $last < $required);
}

/** @return array<string,mixed> */
function monitoringJsonGet(string $url, ?string $token = null): array
{
    $metadata = $url === 'http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token';
    if (!$metadata && !str_starts_with($url, 'https://run.googleapis.com/v2/projects/')
        && !str_starts_with($url, 'https://sqladmin.googleapis.com/sql/v1beta4/projects/')) {
        throw new RuntimeException('unexpected_api');
    }
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('curl_init');
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $metadata ? ['Metadata-Flavor: Google'] : ['Authorization: Bearer ' . $token],
    ]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($body === false || $status !== 200) {
        throw new RuntimeException('api_request_failed');
    }
    $value = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($value)) {
        throw new RuntimeException('invalid_api_json');
    }
    return $value;
}

/** @return array{due_count:int,oldest_due_seconds:int,failed_recent_count:int} */
function monitoringOutbox(PDO $pdo): array
{
    $pdo->exec('START TRANSACTION READ ONLY');
    try {
        $row = $pdo->query(<<<'SQL'
            SELECT COUNT(*) AS due_count,
                   COALESCE(MAX(TIMESTAMPDIFF(SECOND, COALESCE(next_attempt_at, created_at), UTC_TIMESTAMP())), 0) AS oldest_due_seconds
            FROM email_deliveries
            WHERE status = 'queued' AND (next_attempt_at IS NULL OR next_attempt_at <= UTC_TIMESTAMP())
            SQL)->fetch(PDO::FETCH_ASSOC);
        $failed = $pdo->query(<<<'SQL'
            SELECT COUNT(*) FROM email_deliveries
            WHERE status = 'failed' AND last_attempt_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 20 MINUTE)
            SQL)->fetchColumn();
        return [
            'due_count' => (int) $row['due_count'],
            'oldest_due_seconds' => max(0, (int) $row['oldest_due_seconds']),
            'failed_recent_count' => (int) $failed,
        ];
    } finally {
        $pdo->rollBack();
    }
}

function monitoringMain(): int
{
    $stage = 'database';
    try {
        $root = getenv('MONITOR_APP_ROOT') ?: '/var/www/html';
        require $root . '/vendor/autoload.php';
        $config = App\Core\Config::fromFile($root . '/config/config.php');
        $db = new App\Core\Database($config->get('db'));
        $snapshot = monitoringOutbox($db->pdo());

        $stage = 'identity';
        $token = monitoringJsonGet('http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token')['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('missing_identity');
        }
        $project = 'rising-woods-449718-v6';
        $scheduler = 'retroboards-scheduler@' . $project . '.iam.gserviceaccount.com';
        $now = time();
        $snapshot['jobs'] = [];
        $snapshot['missed_schedule_count'] = 0;
        $stage = 'jobs';
        foreach (['retroboards-cron-5m', 'retroboards-cron-6h', 'retroboards-cron-0310', 'retroboards-cron-0700'] as $job) {
            $base = 'https://run.googleapis.com/v2/projects/' . $project . '/locations/us-east4/jobs/' . $job;
            $metadata = monitoringJsonGet($base, $token);
            $created = strtotime((string) ($metadata['createTime'] ?? ''));
            if ($created === false) {
                throw new RuntimeException('missing_job_creation');
            }
            $last = null;
            $pageToken = '';
            for ($page = 0; $page < 3; $page++) {
                $data = monitoringJsonGet($base . '/executions?pageSize=100' . ($pageToken === '' ? '' : '&pageToken=' . rawurlencode($pageToken)), $token);
                $last = monitoringLatestScheduledSuccess($data['executions'] ?? [], $scheduler);
                $pageToken = (string) ($data['nextPageToken'] ?? '');
                if ($last !== null || $pageToken === '') {
                    break;
                }
            }
            if ($last === null && $pageToken !== '') {
                throw new RuntimeException('execution_history_limit');
            }
            $missed = monitoringMissedSchedule($job, $now, $created, $last);
            $snapshot['jobs'][$job] = ['scheduled_success_age_seconds' => $last === null ? null : max(0, $now - $last), 'missed' => $missed];
            $snapshot['missed_schedule_count'] += (int) $missed;
        }

        $stage = 'backups';
        $data = monitoringJsonGet('https://sqladmin.googleapis.com/sql/v1beta4/projects/' . $project . '/instances/imladris-boards/backupRuns?maxResults=100', $token);
        $latest = null;
        foreach ($data['items'] ?? [] as $backup) {
            if (($backup['type'] ?? '') === 'AUTOMATED' && ($backup['status'] ?? '') === 'SUCCESSFUL' && !empty($backup['endTime'])) {
                $time = strtotime((string) $backup['endTime']);
                if ($time === false) {
                    throw new RuntimeException('invalid_backup_time');
                }
                $latest = max($latest ?? 0, $time);
            }
        }
        $snapshot['automated_backup_age_seconds'] = $latest === null ? null : max(0, $now - $latest);
        $snapshot['automated_backup_stale'] = $latest === null || $now - $latest > 129600;
        echo json_encode(['event' => 'retroboards_monitor', 'status' => 'ok', 'severity' => 'INFO'] + $snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
        return 0;
    } catch (Throwable) {
        // Exceptions can contain credentials/SQL/API metadata; log only stage.
        echo json_encode(['event' => 'retroboards_monitor', 'status' => 'error', 'severity' => 'ERROR', 'stage' => $stage], JSON_THROW_ON_ERROR) . "\n";
        return 1;
    }
}

if (!defined('RETROBOARDS_MONITOR_TEST')) {
    exit(monitoringMain());
}
