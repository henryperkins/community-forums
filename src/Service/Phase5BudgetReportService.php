<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Security\CapabilityResolver;
use App\Security\Registry\TrustChainVerifier;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageLifecycleService;
use App\Service\Packages\PackageUpdateService;
use App\Support\Phase5Budgets;

/**
 * Foundation F9 — renders the measured-vs-D11 budget report (entry-gate item
 * A3). Non-PENDING at Foundation: `resolver.p95` (a legacy BASELINE from
 * BaselineMetricsService — the number the future resolver must beat) and
 * `webhook.delivery_timeout` (a CONFIG cap). Every other D11 budget lists its
 * target and stays PENDING until its increment measures it on this same fixture.
 * Read-only; writes only the evidence file the caller names.
 */
final class Phase5BudgetReportService
{
    private ?array $baseline = null;
    private ?array $resolverSample = null;
    private ?array $signatureSample = null;
    private ?array $packageSample = null;

    public function __construct(
        private Database $db,
        private ?CapabilityResolver $resolver = null,
        private ?TrustChainVerifier $trustVerifier = null,
        private ?PackageLifecycleService $packageLifecycle = null,
        private ?PackageUpdateService $packageUpdates = null,
        private ?PackageArtifactStore $packageStore = null,
    )
    {
    }

    private function baseline(): array
    {
        return $this->baseline ??= (new BaselineMetricsService($this->db))->measureLegacyAuthorityRead();
    }

    private function resolverSample(): ?array
    {
        if ($this->resolver === null) {
            return null;
        }

        return $this->resolverSample ??= (new BaselineMetricsService($this->db))->measureResolver($this->resolver);
    }

    private function signatureSample(): ?array
    {
        if ($this->trustVerifier === null) {
            return null;
        }

        return $this->signatureSample ??= (new BaselineMetricsService($this->db))->measureSignatureVerify($this->trustVerifier);
    }

    private function packageSample(): ?array
    {
        if ($this->packageLifecycle === null || $this->packageUpdates === null || $this->packageStore === null) {
            return null;
        }

        return $this->packageSample ??= (new BaselineMetricsService($this->db))->measureInstallUpdate(
            $this->packageLifecycle,
            $this->packageUpdates,
            $this->db,
            $this->packageStore,
        );
    }

    /** @return array<int,array{key:string,metric:string,target:string,measured:string,status:string}> */
    public function rows(): array
    {
        $baseline = $this->baseline();
        $rows = [];
        foreach (Phase5Budgets::all() as $key => $b) {
            $target = $b['target'] . ' ' . $b['unit'] . ' (' . $b['statistic'] . ')';
            $measured = '—';
            $status = 'PENDING (' . $b['measurable_at'] . ')';

            if ($key === 'resolver.p95') {
                $resolverSample = $this->resolverSample();
                if ($resolverSample !== null) {
                    $measured = $resolverSample['p95'] . ' ms resolver (baseline ' . $baseline['p95'] . ' ms legacy)';
                    $status = ((float) $resolverSample['p95']) <= (float) $b['target'] ? 'MEASURED (PASS)' : 'MEASURED (FAIL)';
                } else {
                    $measured = $baseline['p95'] . ' ms legacy';
                    $status = 'BASELINE';
                }
            } elseif ($key === 'registry.signature_verify_p95') {
                $sample = $this->signatureSample();
                if ($sample !== null) {
                    $measured = $sample['p95'] . ' ms verify (' . $sample['data_fixture'] . ')';
                    $status = ((float) $sample['p95']) <= (float) $b['target'] ? 'MEASURED (PASS)' : 'MEASURED (FAIL)';
                }
            } elseif ($key === 'package.install_update_p95') {
                $sample = $this->packageSample();
                if ($sample !== null) {
                    $measured = $sample['p95'] . ' ms install/update (' . $sample['samples'] . ' samples)';
                    $status = ((float) $sample['p95']) <= (float) $b['target'] ? 'MEASURED (PASS)' : 'MEASURED (FAIL)';
                }
            } elseif ($key === 'registry.snapshot_freshness') {
                $measured = '86400 s enforced at ingest (freshness_window clamp + expired_snapshot refusal)';
                $status = 'CONFIG';
            } elseif ($key === 'webhook.delivery_timeout') {
                $measured = '5000 ms configured';
                $status = 'CONFIG';
            }

            $rows[] = ['key' => $key, 'metric' => $b['metric'], 'target' => $target, 'measured' => $measured, 'status' => $status];
        }
        return $rows;
    }

    public function render(): string
    {
        $env = $this->baseline();
        $out = "# Phase 5 — Performance Budgets (A3, Foundation F9)\n\n";
        $out .= "> Generated by `bin/console verify:phase5-budgets`. Foundation measures the\n";
        $out .= "> resolver baseline (legacy authority read) + config caps; each later increment\n";
        $out .= "> fills its PENDING row on this same `Phase5FixtureSeeder` corpus.\n\n";
        $out .= "## Measurement envelope (PHASE_5_PLAN §11.3)\n\n";
        $out .= '- Route/job: `' . $env['route_or_job'] . "`\n";
        $out .= '- PHP: ' . $env['php_version'] . ' · DB: ' . $env['db_version'] . "\n";
        $out .= '- Hardware class: ' . $env['hardware_class'] . ' · OS/isolation: ' . $env['os_isolation_profile'] . "\n";
        $out .= '- Fixture: ' . $env['data_fixture'] . ' · role assignments: ' . $env['role_assignment_count'] . "\n";
        $out .= '- Window: ' . $env['window'] . ' · concurrency: ' . $env['concurrency'] . ' · cache: ' . $env['cache_state'] . "\n";
        $out .= '- Legacy read p50/p95/p99 (ms): ' . $env['p50'] . ' / ' . $env['p95'] . ' / ' . $env['p99'] . "\n";
        $resolverSample = $this->resolverSample();
        if ($resolverSample !== null) {
            $out .= '- Resolver p50/p95/p99 (ms): ' . $resolverSample['p50'] . ' / ' . $resolverSample['p95'] . ' / ' . $resolverSample['p99']
                . ' · route/job: `' . $resolverSample['route_or_job'] . "`\n";
        }
        $signatureSample = $this->signatureSample();
        if ($signatureSample !== null) {
            $out .= '- Signature verify p50/p95/p99 (ms): ' . $signatureSample['p50'] . ' / ' . $signatureSample['p95'] . ' / ' . $signatureSample['p99']
                . ' · route/job: `' . $signatureSample['route_or_job'] . "`\n";
        }
        $packageSample = $this->packageSample();
        if ($packageSample !== null) {
            $out .= '- Package install/update p50/p95/p99 (ms): ' . $packageSample['p50'] . ' / ' . $packageSample['p95'] . ' / ' . $packageSample['p99']
                . ' · route/job: `' . $packageSample['route_or_job'] . '` · samples: ' . $packageSample['samples'] . "\n";
        }
        $out .= '- Queries: ' . $env['query_count'] . ' · query time (ms): ' . $env['query_time_ms']
             . ' · peak mem (bytes): ' . $env['peak_memory_bytes'] . ' · error rate: ' . $env['error_rate'] . "\n\n";
        $out .= "## Budgets vs D11 targets (ADR 0004 D11)\n\n";
        $out .= "| Budget | Metric | Target | Measured | Status |\n";
        $out .= "|---|---|---|---|---|\n";
        foreach ($this->rows() as $r) {
            $out .= '| `' . $r['key'] . '` | ' . $r['metric'] . ' | ' . $r['target'] . ' | ' . $r['measured'] . ' | ' . $r['status'] . " |\n";
        }
        return $out;
    }

    public function write(string $path): int
    {
        $bytes = file_put_contents($path, $this->render());
        if ($bytes === false) {
            throw new \RuntimeException("could not write budget report to $path");
        }
        return $bytes;
    }
}
