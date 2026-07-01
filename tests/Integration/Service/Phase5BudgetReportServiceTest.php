<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\SettingRepository;
use App\Service\Phase5BudgetReportService;
use App\Service\Phase5FixtureSeeder;
use App\Support\Phase5Budgets;
use Tests\Support\TestCase;

final class Phase5BudgetReportServiceTest extends TestCase
{
    public function test_rows_cover_every_budget_with_a_resolver_baseline(): void
    {
        (new Phase5FixtureSeeder($this->db, new SettingRepository($this->db), 'testing'))->seed();
        $rows = (new Phase5BudgetReportService($this->db))->rows();

        self::assertCount(count(Phase5Budgets::all()), $rows);

        $byKey = [];
        foreach ($rows as $r) {
            $byKey[$r['key']] = $r;
        }
        self::assertSame('BASELINE', $byKey['resolver.p95']['status']);
        self::assertNotSame('—', $byKey['resolver.p95']['measured'], 'resolver baseline is a real number');
        self::assertStringContainsString('PENDING', $byKey['oidc.discovery_p95_cold']['status']);
        self::assertStringContainsString('5', $byKey['resolver.p95']['target']); // "5 ms"
    }

    public function test_render_and_write_produce_the_evidence_file(): void
    {
        (new Phase5FixtureSeeder($this->db, new SettingRepository($this->db), 'testing'))->seed();
        $svc = new Phase5BudgetReportService($this->db);

        $md = $svc->render();
        self::assertStringContainsString('# Phase 5 — Performance Budgets', $md);
        self::assertStringContainsString('resolver.p95', $md);
        self::assertStringContainsString('PHP', $md);

        $path = sys_get_temp_dir() . '/p5-budget-' . bin2hex(random_bytes(4)) . '.md';
        $bytes = $svc->write($path);
        self::assertGreaterThan(0, $bytes);
        self::assertFileExists($path);
        self::assertStringContainsString('resolver.p95', (string) file_get_contents($path));
        unlink($path);
    }
}
