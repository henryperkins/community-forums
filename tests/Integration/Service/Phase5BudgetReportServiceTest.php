<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\SettingRepository;
use App\Service\BaselineMetricsService;
use App\Service\Phase5BudgetReportService;
use App\Service\Phase5FixtureSeeder;
use App\Support\Base64Url;
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

    public function test_resolver_row_is_measured_when_a_resolver_is_supplied(): void
    {
        (new Phase5FixtureSeeder($this->db, new SettingRepository($this->db), 'testing'))->seed(true);
        $resolver = new \App\Security\CapabilityResolver(
            new \App\Repository\RoleCapabilityRepository($this->db),
            new \App\Repository\RoleAssignmentRepository($this->db),
            new \App\Service\LegacyAuthorityProjection(new \App\Repository\BoardModeratorRepository($this->db)),
            new \App\Repository\ProtectedOwnerRepository($this->db),
            new \App\Repository\BoardRepository($this->db),
            new \App\Repository\BoardMemberRepository($this->db),
            new \App\Security\BoardPolicy(),
            new \App\Security\WriteGate(),
        );
        $report = new Phase5BudgetReportService($this->db, $resolver);
        $rows = [];
        foreach ($report->rows() as $row) {
            $rows[$row['key']] = $row;
        }

        self::assertStringStartsWith('MEASURED', $rows['resolver.p95']['status']);
        self::assertStringContainsString('ms resolver', $rows['resolver.p95']['measured']);
        self::assertStringContainsString('legacy', $rows['resolver.p95']['measured']);
        self::assertStringContainsString('Resolver p50/p95/p99', $report->render());
    }

    public function test_webauthn_ceremony_sampler_verifies_public_fixture_assertions(): void
    {
        $metrics = new BaselineMetricsService($this->db);
        if (!method_exists($metrics, 'measureWebauthnCeremony')) {
            self::fail('BaselineMetricsService must expose a fixture-backed WebAuthn ceremony sampler.');
        }

        $sample = $metrics->measureWebauthnCeremony();

        self::assertSame('webauthn_ceremony_assertion_verify', $sample['route_or_job']);
        self::assertSame(200, $sample['samples']);
        self::assertSame('public-only webauthn-budget-fixture.json assertions', $sample['data_fixture']);
        self::assertGreaterThan(0.0, $sample['p95']);
        self::assertLessThanOrEqual(Phase5Budgets::target('webauthn.ceremony_p95'), $sample['p95']);
        self::assertSame(0, $sample['query_count']);
        self::assertSame(0.0, $sample['error_rate']);
    }

    public function test_webauthn_ceremony_sampler_counts_invalid_fixture_samples_as_errors(): void
    {
        $source = dirname(__DIR__, 3) . '/docs/evidence/phase5/webauthn-budget-fixture.json';
        $fixture = json_decode((string) file_get_contents($source), true, flags: JSON_THROW_ON_ERROR);
        $fixture['samples'][0]['payload']['response']['signature'] = Base64Url::encode(random_bytes(64));

        $path = sys_get_temp_dir() . '/webauthn-budget-fixture-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        try {
            $sample = (new BaselineMetricsService($this->db))->measureWebauthnCeremony($path);
        } finally {
            unlink($path);
        }

        self::assertSame(200, $sample['samples']);
        self::assertGreaterThan(0.0, $sample['error_rate']);
        self::assertLessThan(1.0, $sample['error_rate']);
    }

    public function test_webauthn_budget_row_is_measured_from_the_public_fixture(): void
    {
        $report = new Phase5BudgetReportService($this->db);
        $rows = [];
        foreach ($report->rows() as $row) {
            $rows[$row['key']] = $row;
        }

        self::assertSame('MEASURED (PASS)', $rows['webauthn.ceremony_p95']['status']);
        self::assertStringContainsString('ms WebAuthn assertion verify', $rows['webauthn.ceremony_p95']['measured']);
        self::assertStringContainsString('WebAuthn ceremony p50/p95/p99', $report->render());
    }

    public function test_inc2_registry_rows_measure_and_config(): void
    {
        $service = new Phase5BudgetReportService(
            $this->db,
            null,
            new \App\Security\Registry\TrustChainVerifier(),
        );
        $rows = [];
        foreach ($service->rows() as $row) {
            $rows[$row['key']] = $row;
        }

        self::assertStringStartsWith('MEASURED', $rows['registry.signature_verify_p95']['status']);
        self::assertStringContainsString('ms', $rows['registry.signature_verify_p95']['measured']);
        self::assertSame('CONFIG', $rows['registry.snapshot_freshness']['status']);
        self::assertStringContainsString('86400', $rows['registry.snapshot_freshness']['measured']);
        self::assertStringContainsString('staged-enablement', $rows['registry.fetch_p95']['status']);
    }
}
