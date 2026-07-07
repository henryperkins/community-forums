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

        // Minimal construction covers every D11 budget EXCEPT the two opt-in
        // §11.3 lifecycle rows (gated behind $includeLifecycleSamples).
        self::assertCount(count(Phase5Budgets::all()) - 2, $rows);

        $byKey = [];
        foreach ($rows as $r) {
            $byKey[$r['key']] = $r;
        }
        self::assertSame('BASELINE', $byKey['resolver.p95']['status']);
        self::assertNotSame('—', $byKey['resolver.p95']['measured'], 'resolver baseline is a real number');
        // Inc 8 graduated the two oidc rows from PENDING to measured-by-default;
        // registry.fetch_p95 remains the canonical still-PENDING example.
        self::assertStringContainsString('MEASURED', $byKey['oidc.discovery_p95_cold']['status']);
        self::assertStringContainsString('PENDING', $byKey['registry.fetch_p95']['status']);
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

    public function test_oidc_discovery_rows_measure_cached_and_cold_paths(): void
    {
        // Inc 8 (P5-12): both D11 oidc rows measure on every report run.
        $report = new Phase5BudgetReportService($this->db);
        $rows = [];
        foreach ($report->rows() as $row) {
            $rows[$row['key']] = $row;
        }

        self::assertSame('MEASURED (PASS)', $rows['oidc.discovery_p95_cached']['status']);
        self::assertStringContainsString('ms cached discovery+JWKS', $rows['oidc.discovery_p95_cached']['measured']);
        self::assertSame('MEASURED (PASS)', $rows['oidc.discovery_p95_cold']['status']);
        self::assertStringContainsString('ms cold discovery+JWKS', $rows['oidc.discovery_p95_cold']['measured']);
    }

    public function test_oidc_discovery_sampler_exercises_cache_hit_and_cold_fetch_paths(): void
    {
        $svc = new BaselineMetricsService($this->db);

        $cached = $svc->measureOidcDiscovery(cold: false, iterations: 25);
        self::assertSame('oidc_discovery_cached', $cached['route_or_job']);
        self::assertSame(25, $cached['samples']);
        self::assertSame(0.0, $cached['error_rate'], 'cache-only path must never fetch (the bench transport throws)');
        self::assertGreaterThan(0, $cached['p95']);

        $cold = $svc->measureOidcDiscovery(cold: true, iterations: 25);
        self::assertSame('oidc_discovery_cold', $cold['route_or_job']);
        self::assertSame(25, $cold['samples']);
        self::assertSame(0.0, $cold['error_rate']);
        self::assertGreaterThanOrEqual($cached['p50'], $cold['p50'] + 0.5, 'cold path (fetch+persist) should not be cheaper than a cache hit beyond jitter');

        // The sampler cleans up its bench provider row.
        self::assertFalse(
            $this->db->fetchValue("SELECT 1 FROM identity_providers WHERE provider_key LIKE 'oidc-bench-%' LIMIT 1") !== false,
            'bench rows are removed after measurement',
        );
    }

    public function test_inc6_measured_only_metrics_are_emitted_when_opted_in(): void
    {
        // Regression guard for the Task 15 silent-loss gap: with the opt-in flag
        // set (as `verify:phase5-budgets` does), the two §11.3 measured-only
        // metrics must be produced BY the generator (so a regeneration re-emits
        // them) — never hand-appended to the evidence file.
        (new Phase5FixtureSeeder($this->db, new SettingRepository($this->db), 'testing'))->seed(true);
        $report = new Phase5BudgetReportService($this->db, includeLifecycleSamples: true);

        $rows = [];
        foreach ($report->rows() as $row) {
            $rows[$row['key']] = $row;
        }

        self::assertArrayHasKey('role_assignment.change_propagation_p95', $rows);
        self::assertArrayHasKey('permission_simulator.duration_p95', $rows);
        self::assertSame('MEASURED (no D11 target)', $rows['role_assignment.change_propagation_p95']['status']);
        self::assertSame('MEASURED (no D11 target)', $rows['permission_simulator.duration_p95']['status']);
        self::assertStringContainsString('no D11 target', $rows['role_assignment.change_propagation_p95']['target']);
        self::assertStringContainsString('revoke→can pair', $rows['role_assignment.change_propagation_p95']['measured']);
        self::assertStringContainsString('stale-after-revoke', $rows['role_assignment.change_propagation_p95']['measured']);
        self::assertStringContainsString('simulate() call', $rows['permission_simulator.duration_p95']['measured']);

        // …and the rendered document carries both rows, both envelope lines, and
        // the methodology note — so `verify:phase5-budgets` regenerates them.
        $md = $report->render();
        self::assertStringContainsString('`role_assignment.change_propagation_p95`', $md);
        self::assertStringContainsString('`permission_simulator.duration_p95`', $md);
        self::assertStringContainsString('Assignment-change propagation p50/p95/p99', $md);
        self::assertStringContainsString('Simulator duration p50/p95/p99', $md);
        self::assertStringContainsString('Measured-only metrics (§11.3, no D11 gate)', $md);
    }

    public function test_inc6_lifecycle_samplers_are_gated_off_by_default(): void
    {
        // Proves the opt-in gate: a minimal-construction consumer (every other
        // budget test, PackageInstallBudgetTest, ThemeBudgetTest) neither runs the
        // 200-iteration mutating benchmark nor carries the two rows. Guards against
        // the samplers being re-wired unconditionally.
        (new Phase5FixtureSeeder($this->db, new SettingRepository($this->db), 'testing'))->seed(true);
        $report = new Phase5BudgetReportService($this->db);

        $keys = array_column($report->rows(), 'key');
        self::assertNotContains('role_assignment.change_propagation_p95', $keys);
        self::assertNotContains('permission_simulator.duration_p95', $keys);

        $md = $report->render();
        self::assertStringNotContainsString('Assignment-change propagation p50/p95/p99', $md);
        self::assertStringNotContainsString('Simulator duration p50/p95/p99', $md);
        self::assertStringNotContainsString('Measured-only metrics (§11.3, no D11 gate)', $md);
        // The resolver gate row is unaffected by the lifecycle gate.
        self::assertStringContainsString('resolver.p95', $md);
    }

    public function test_assignment_change_propagation_sampler_is_immediate_and_writes_nothing_durable(): void
    {
        $before = (int) $this->db->fetchValue('SELECT COUNT(*) FROM users');
        $sample = (new BaselineMetricsService($this->db))->measureAssignmentChangePropagation(25);

        self::assertSame('role_assignment_change_propagation', $sample['route_or_job']);
        self::assertSame(25, $sample['samples']);
        self::assertSame(0, $sample['anomalies'], 'a revoked grant must never still resolve as allowed');
        self::assertGreaterThan(0.0, $sample['p95']);
        // The sampler mutates only inside the per-test transaction; the outer
        // suite rolls it back. Within this test the synthetic admin/subject exist,
        // so the count is strictly greater — proving the loop actually ran.
        self::assertGreaterThan($before, (int) $this->db->fetchValue('SELECT COUNT(*) FROM users'));
    }

    public function test_simulator_duration_sampler_runs_on_the_f9_fixture(): void
    {
        (new Phase5FixtureSeeder($this->db, new SettingRepository($this->db), 'testing'))->seed(true);
        $sample = (new BaselineMetricsService($this->db))->measureSimulatorDuration(40);

        self::assertSame('permission_simulator_simulate', $sample['route_or_job']);
        self::assertSame(40, $sample['samples']);
        self::assertGreaterThan(0.0, $sample['p95']);
        self::assertSame(0.0, $sample['error_rate'], 'every fixture actor resolves without a simulate() error');
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
