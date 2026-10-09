<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;

final class CloudRunMonitoringContractTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public static function setUpBeforeClass(): void
    {
        if (!defined('RETROBOARDS_MONITOR_TEST')) {
            define('RETROBOARDS_MONITOR_TEST', true);
            require self::ROOT . '/deploy/cloudrun/monitoring/probe.php';
        }
    }

    public function test_daily_deadlines_use_utc_schedule_and_allow_first_boot_and_startup_grace(): void
    {
        $created = strtotime('2026-10-09 04:24:00 UTC');
        self::assertSame(strtotime('2026-10-09 03:10:00 UTC'), \monitoringLastDue('retroboards-cron-0310', strtotime('2026-10-09 06:55:00 UTC')));
        self::assertFalse(\monitoringMissedSchedule('retroboards-cron-0310', strtotime('2026-10-09 06:55:00 UTC'), $created, null));
        self::assertFalse(\monitoringMissedSchedule('retroboards-cron-0700', strtotime('2026-10-09 07:29:59 UTC'), $created, null));
        self::assertTrue(\monitoringMissedSchedule('retroboards-cron-0700', strtotime('2026-10-09 07:30:01 UTC'), $created, null));
        self::assertTrue(\monitoringMissedSchedule('retroboards-cron-0310', strtotime('2026-10-10 03:40:01 UTC'), $created, null));
        self::assertFalse(\monitoringMissedSchedule('retroboards-cron-0310', strtotime('2026-10-10 03:40:01 UTC'), $created, strtotime('2026-10-10 03:12:00 UTC')));
        // An upcoming current five-minute slot must not hide a missed old slot.
        self::assertTrue(\monitoringMissedSchedule('retroboards-cron-5m', strtotime('2026-10-09 07:00:00 UTC'), $created, strtotime('2026-10-09 06:25:30 UTC')));
        self::assertFalse(\monitoringMissedSchedule('retroboards-cron-5m', strtotime('2026-10-09 07:00:00 UTC'), $created, strtotime('2026-10-09 06:35:30 UTC')));
    }

    public function test_only_successful_scheduler_executions_satisfy_heartbeat(): void
    {
        $scheduler = 'scheduler@example.invalid';
        $success = ['type' => 'Completed', 'state' => 'CONDITION_SUCCEEDED'];
        $failed = ['type' => 'Completed', 'state' => 'CONDITION_FAILED'];
        $executions = [
            ['creator' => 'manual@example.invalid', 'completionTime' => '2026-10-09T07:00:00Z', 'conditions' => [$success]],
            ['creator' => $scheduler, 'completionTime' => '2026-10-09T06:50:00Z', 'conditions' => [$failed]],
            ['creator' => $scheduler, 'completionTime' => '2026-10-09T06:45:00Z', 'conditions' => [$success]],
            ['creator' => $scheduler, 'completionTime' => '2026-10-09T06:40:00Z', 'conditions' => [$success]],
            ['creator' => $scheduler, 'conditions' => [['type' => 'Started', 'state' => 'CONDITION_SUCCEEDED']]],
        ];
        self::assertSame(strtotime('2026-10-09T06:45:00Z'), \monitoringLatestScheduledSuccess($executions, $scheduler));
        self::assertNull(\monitoringLatestScheduledSuccess(array_slice($executions, 0, 2), $scheduler));
    }

    public function test_read_only_outbox_aggregates_exclude_future_retries_and_old_terminal_failures(): void
    {
        // A private connection and temporary table exercise real MySQL SQL
        // without committing fixture rows into the shared migrated schema.
        $db = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $pdo = $db->pdo();
        $pdo->exec('CREATE TEMPORARY TABLE email_deliveries (status VARCHAR(16), created_at DATETIME, next_attempt_at DATETIME NULL, last_attempt_at DATETIME NULL)');
        $pdo->exec(<<<'SQL'
            INSERT INTO email_deliveries VALUES
              ('queued', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR), NULL),
              ('queued', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR), DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE), NULL),
              ('queued', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 45 MINUTE), NULL, NULL),
              ('sent', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 HOUR), NULL, UTC_TIMESTAMP()),
              ('failed', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR), NULL, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)),
              ('failed', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 HOUR), NULL, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR))
            SQL);
        $snapshot = \monitoringOutbox($pdo);
        self::assertSame(2, $snapshot['due_count']);
        self::assertGreaterThanOrEqual(2700, $snapshot['oldest_due_seconds']);
        self::assertLessThan(2710, $snapshot['oldest_due_seconds']);
        self::assertSame(1, $snapshot['failed_recent_count']);
        self::assertFalse($pdo->inTransaction());
        self::assertSame(6, (int) $pdo->query('SELECT COUNT(*) FROM email_deliveries')->fetchColumn());
        $pdo->exec('DROP TEMPORARY TABLE email_deliveries');
    }

    public function test_monitor_has_no_mail_or_app_secret_and_is_updated_by_deploys(): void
    {
        $configure = file_get_contents(self::ROOT . '/deploy/cloudrun/monitoring/configure.py');
        $probe = file_get_contents(self::ROOT . '/deploy/cloudrun/monitoring/probe.php');
        $pipeline = file_get_contents(self::ROOT . '/deploy/cloudrun/cloudbuild.yaml');
        self::assertStringNotContainsString('APP_KEY', $configure);
        self::assertStringNotContainsString('CLOUDFLARE_EMAIL_API_TOKEN', $configure);
        self::assertStringContainsString('START TRANSACTION READ ONLY', $probe);
        self::assertStringNotContainsString('SELECT *', $probe);
        self::assertStringNotContainsString("->getMessage()", $probe);
        self::assertStringContainsString('CURLOPT_FOLLOWLOCATION => false', $probe);
        self::assertStringContainsString('CURLOPT_SSL_VERIFYPEER => true', $probe);
        self::assertStringContainsString('metadata.labels.role=monitor', $pipeline);
    }

    public function test_collector_database_failure_emits_only_a_bounded_stage_without_credentials(): void
    {
        $environment = array_merge(getenv(), [
            'MONITOR_APP_ROOT' => realpath(self::ROOT),
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '9',
            'DB_SOCKET' => '',
            'DB_USERNAME' => 'monitor-synthetic-user',
            'DB_PASSWORD' => 'monitor-synthetic-secret',
        ]);
        $process = proc_open([PHP_BINARY, self::ROOT . '/deploy/cloudrun/monitoring/probe.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(1, proc_close($process));
        self::assertSame('', $stderr);
        self::assertSame(['event' => 'retroboards_monitor', 'status' => 'error', 'severity' => 'ERROR', 'stage' => 'database'], json_decode($stdout, true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('monitor-synthetic-secret', $stdout);
        self::assertStringNotContainsString('monitor-synthetic-user', $stdout);
    }

    public function test_alert_policy_thresholds_keep_expected_noise_and_backup_boundaries(): void
    {
        $policies = json_decode(file_get_contents(self::ROOT . '/deploy/cloudrun/monitoring/policies.json'), true, 512, JSON_THROW_ON_ERROR);
        $byKey = array_column($policies, null, 'key');
        self::assertCount(9, $byKey);
        $heartbeat = $byKey['collector-absent']['conditions'][0]['conditionThreshold'];
        self::assertSame('60s', $heartbeat['duration']);
        self::assertSame('2700s', $heartbeat['aggregations'][0]['alignmentPeriod']);
        self::assertSame('COMPARISON_LT', $heartbeat['comparison']);
        self::assertSame(1, $heartbeat['thresholdValue']);
        self::assertSame('EVALUATION_MISSING_DATA_ACTIVE', $heartbeat['evaluationMissingData']);
        self::assertSame('180s', $byKey['availability']['conditions'][0]['conditionThreshold']['duration']);
        self::assertSame('300s', $byKey['http-errors']['conditions'][0]['conditionThreshold']['duration']);
        $backup = $byKey['backup-failed']['conditions'][0]['conditionMatchedLog']['filter'];
        self::assertStringContainsString('STATUS_FAILED', $backup);
        self::assertStringContainsString('STATUS_SKIPPED', $backup);
        self::assertStringNotContainsString('STATUS_ATTEMPT_FAILED', $backup);
        foreach ($policies as $policy) {
            self::assertTrue($policy['enabled']);
            if (isset($policy['conditions'][0]['conditionMatchedLog'])) {
                self::assertSame('3600s', $policy['alertStrategy']['notificationRateLimit']['period']);
            }
        }
    }
}
