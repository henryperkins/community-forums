<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\SettingRepository;
use App\Service\BaselineMetricsService;
use App\Service\Phase5FixtureSeeder;
use Tests\Support\TestCase;

final class BaselineMetricsServiceTest extends TestCase
{
    private const ENVELOPE_KEYS = [
        'route_or_job', 'hardware_class', 'os_isolation_profile', 'php_version', 'db_version',
        'data_fixture', 'role_assignment_count', 'installed_package_count', 'concurrency',
        'cache_state', 'window', 'p50', 'p95', 'p99', 'query_count', 'query_time_ms',
        'peak_memory_bytes', 'queue_age', 'error_rate',
    ];

    public function test_measure_returns_the_full_section_11_3_envelope(): void
    {
        (new Phase5FixtureSeeder($this->db, new SettingRepository($this->db), 'testing'))->seed();

        $rec = (new BaselineMetricsService($this->db))->measureLegacyAuthorityRead(50);

        foreach (self::ENVELOPE_KEYS as $k) {
            self::assertArrayHasKey($k, $rec, "envelope missing $k");
        }
        self::assertIsFloat($rec['p95']);
        self::assertGreaterThanOrEqual($rec['p50'], $rec['p95'], 'p95 >= p50');
        self::assertGreaterThanOrEqual($rec['p95'], $rec['p99'], 'p99 >= p95');
        self::assertGreaterThan(0, $rec['query_count'], 'measured real queries');
        self::assertSame(0.0, $rec['error_rate']);
        self::assertSame(PHP_VERSION, $rec['php_version']);
        self::assertNotSame('', (string) $rec['db_version']);
    }
}
