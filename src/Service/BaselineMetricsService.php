<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;

/**
 * Foundation F9 — measures baseline metrics on the Phase5FixtureSeeder corpus,
 * emitting the PHASE_5_PLAN §11.3 measurement envelope. The one hot path
 * measurable at Foundation is the legacy authorization read (user role/status +
 * board-moderator membership + board posting floor) — the exact path Increment
 * 1's capability resolver replaces, so its p50/p95/p99 is the baseline the `5ms`
 * resolver budget must beat. Read-only; no writes, no flag flips.
 */
final class BaselineMetricsService
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed> the §11.3 envelope */
    public function measureLegacyAuthorityRead(int $iterations = 200): array
    {
        $iterations = max(1, $iterations);
        $users = $this->db->fetchAll("SELECT id, role, status FROM users WHERE username LIKE 'p5fix_%' ORDER BY id ASC");
        $boards = $this->db->fetchAll("SELECT id, post_min_role FROM boards WHERE slug LIKE 'p5fix_%' ORDER BY id ASC");

        $samples = [];
        $queryCount = 0;
        $errors = 0;

        if ($users !== [] && $boards !== []) {
            for ($i = 0; $i < $iterations; $i++) {
                $u = $users[$i % count($users)];
                $b = $boards[$i % count($boards)];
                $t0 = hrtime(true);
                try {
                    // The legacy authority triplet (3 statements per decision).
                    $this->db->fetch('SELECT role, status FROM users WHERE id = ?', [(int) $u['id']]);
                    $this->db->fetchValue('SELECT 1 FROM board_moderators WHERE board_id = ? AND user_id = ?', [(int) $b['id'], (int) $u['id']]);
                    $this->db->fetchValue('SELECT post_min_role FROM boards WHERE id = ?', [(int) $b['id']]);
                    $queryCount += 3;
                } catch (\Throwable) {
                    $errors++;
                }
                $samples[] = (hrtime(true) - $t0) / 1_000_000; // ns → ms
            }
        }

        return [
            'route_or_job' => 'legacy_authority_read',
            'hardware_class' => getenv('RB_HARDWARE_CLASS') ?: 'unknown',
            'os_isolation_profile' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'db_version' => (string) ($this->db->fetchValue('SELECT VERSION()') ?? ''),
            'data_fixture' => 'phase5_fixture_v' . \App\Service\Phase5FixtureSeeder::FIXTURE_VERSION,
            'role_assignment_count' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM role_assignments'),
            'installed_package_count' => 0,
            'concurrency' => 1,
            'cache_state' => 'cold',
            'window' => $iterations . ' iterations',
            'p50' => self::percentile($samples, 50),
            'p95' => self::percentile($samples, 95),
            'p99' => self::percentile($samples, 99),
            'query_count' => $queryCount,
            'query_time_ms' => round(array_sum($samples), 4),
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'queue_age' => null,
            'error_rate' => $samples === [] ? 0.0 : round($errors / count($samples), 4),
        ];
    }

    /** @param list<float> $samples */
    private static function percentile(array $samples, int $p): float
    {
        if ($samples === []) {
            return 0.0;
        }
        sort($samples);
        $rank = (int) ceil(($p / 100) * count($samples)) - 1;
        $rank = max(0, min($rank, count($samples) - 1));
        return round($samples[$rank], 4);
    }
}
