<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Security\CapabilityResolver;
use App\Security\Registry\TrustChainVerifier;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageLifecycleService;
use App\Service\Packages\PackageUpdateService;
use App\Service\Packages\ThemeStateService;
use App\Support\Phase5Budgets;

/**
 * Foundation F9 — renders the measured-vs-D11 budget report (entry-gate item
 * A3). Non-PENDING at Foundation: `resolver.p95` (a legacy BASELINE from
 * BaselineMetricsService — the number the future resolver must beat) and
 * `webhook.delivery_timeout` (a CONFIG cap). Every other D11 budget lists its
 * target and stays PENDING until its increment measures it on this same fixture.
 *
 * Read-only by default. The two §11.3 measured-only Inc-6 rows
 * (`role_assignment.change_propagation_p95`, `permission_simulator.duration_p95`)
 * are gated behind the opt-in `$includeLifecycleSamples` flag: only then do the
 * mutating 200-iteration samplers run (inside the caller's rollback transaction)
 * and only then are those two rows emitted. Minimal-construction consumers
 * (other budget tests) leave the flag off, so they neither run the benchmark nor
 * carry the rows — exactly as the other optional samplers omit their rows when
 * their collaborator is absent. `bin/console verify:phase5-budgets` opts in so
 * the evidence doc regenerates with both rows.
 */
final class Phase5BudgetReportService
{
    private ?array $baseline = null;
    private ?array $resolverSample = null;
    private ?array $signatureSample = null;
    private ?array $packageSample = null;
    private ?array $themeSample = null;
    private ?array $webauthnSample = null;
    private ?array $oidcCachedSample = null;
    private ?array $oidcColdSample = null;
    private ?array $propagationSample = null;
    private ?array $simulatorSample = null;

    public function __construct(
        private Database $db,
        private ?CapabilityResolver $resolver = null,
        private ?TrustChainVerifier $trustVerifier = null,
        private ?PackageLifecycleService $packageLifecycle = null,
        private ?PackageUpdateService $packageUpdates = null,
        private ?PackageArtifactStore $packageStore = null,
        private ?ThemeStateService $themeState = null,
        private bool $includeLifecycleSamples = false,
    ) {
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

    private function themeSample(): ?array
    {
        if ($this->packageLifecycle === null || $this->themeState === null || $this->packageStore === null) {
            return null;
        }

        return $this->themeSample ??= (new BaselineMetricsService($this->db))->measureThemeBuildApply(
            $this->packageLifecycle,
            $this->themeState,
            $this->db,
            $this->packageStore,
        );
    }

    private function webauthnSample(): array
    {
        return $this->webauthnSample ??= (new BaselineMetricsService($this->db))->measureWebauthnCeremony();
    }

    /** Inc 8 — cheap self-fixturing sampler, so it runs on every report. */
    private function oidcSample(bool $cold): array
    {
        if ($cold) {
            return $this->oidcColdSample ??= (new BaselineMetricsService($this->db))->measureOidcDiscovery(true);
        }
        return $this->oidcCachedSample ??= (new BaselineMetricsService($this->db))->measureOidcDiscovery(false);
    }

    /**
     * §11.3 measured-only (no D11 gate). Opt-in only: null unless the caller set
     * `$includeLifecycleSamples`, because this sampler mutates and runs a
     * 200-iteration benchmark. When opted in it builds its own fixture, so
     * `verify:phase5-budgets` re-emits this row every run instead of ever losing
     * it. Memoized so the in-transaction rows() measurement is the one reused by
     * the post-rollback render() (mirrors packageSample/themeSample).
     */
    private function propagationSample(): ?array
    {
        if (!$this->includeLifecycleSamples) {
            return null;
        }

        return $this->propagationSample ??= (new BaselineMetricsService($this->db))->measureAssignmentChangePropagation();
    }

    /** §11.3 measured-only (no D11 gate). Opt-in only (see propagationSample). */
    private function simulatorSample(): ?array
    {
        if (!$this->includeLifecycleSamples) {
            return null;
        }

        return $this->simulatorSample ??= (new BaselineMetricsService($this->db))->measureSimulatorDuration();
    }

    /** @return array<int,array{key:string,metric:string,target:string,measured:string,status:string}> */
    public function rows(): array
    {
        $baseline = $this->baseline();
        $rows = [];
        foreach (Phase5Budgets::all() as $key => $b) {
            $target = $b['target'] === null
                ? 'no D11 target (measurement only)'
                : $b['target'] . ' ' . $b['unit'] . ' (' . $b['statistic'] . ')';
            $measured = '—';
            $status = 'PENDING (' . $b['measurable_at'] . ')';

            if ($key === 'role_assignment.change_propagation_p95') {
                $sample = $this->propagationSample();
                if ($sample === null) {
                    continue; // opt-in only — omitted (and un-benchmarked) for minimal consumers
                }
                $measured = $sample['p95'] . ' ms revoke→can pair (' . $sample['samples'] . ' iterations, '
                    . $sample['anomalies'] . '/' . $sample['samples'] . ' stale-after-revoke)';
                $status = 'MEASURED (no D11 target)';
            } elseif ($key === 'permission_simulator.duration_p95') {
                $sample = $this->simulatorSample();
                if ($sample === null) {
                    continue; // opt-in only — omitted (and un-benchmarked) for minimal consumers
                }
                $measured = $sample['p95'] . ' ms simulate() call (' . $sample['samples'] . ' iterations, F9 fixture)';
                $status = 'MEASURED (no D11 target)';
            } elseif ($key === 'resolver.p95') {
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
            } elseif ($key === 'theme.build_apply_p95') {
                $sample = $this->themeSample();
                if ($sample !== null) {
                    $measured = $sample['p95'] . ' ms theme build/apply (' . $sample['samples'] . ' samples)';
                    $status = ((float) $sample['p95']) <= (float) $b['target'] ? 'MEASURED (PASS)' : 'MEASURED (FAIL)';
                }
            } elseif ($key === 'webauthn.ceremony_p95') {
                $sample = $this->webauthnSample();
                $measured = $sample['p95'] . ' ms WebAuthn assertion verify (' . $sample['samples'] . ' samples)';
                $status = ((float) $sample['p95']) <= (float) $b['target'] ? 'MEASURED (PASS)' : 'MEASURED (FAIL)';
            } elseif ($key === 'oidc.discovery_p95_cached') {
                $sample = $this->oidcSample(false);
                $measured = $sample['p95'] . ' ms cached discovery+JWKS (' . $sample['samples'] . ' iterations; row load + authorizeUrl + JWKS cache hit)';
                $status = ((float) $sample['p95']) <= (float) $b['target'] ? 'MEASURED (PASS)' : 'MEASURED (FAIL)';
            } elseif ($key === 'oidc.discovery_p95_cold') {
                $sample = $this->oidcSample(true);
                $measured = $sample['p95'] . ' ms cold discovery+JWKS (' . $sample['samples'] . ' iterations; fetch+validate+persist, in-process transport — remote RTT excluded)';
                $status = ((float) $sample['p95']) <= (float) $b['target'] ? 'MEASURED (PASS)' : 'MEASURED (FAIL)';
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
        $themeSample = $this->themeSample();
        if ($themeSample !== null) {
            $out .= '- Theme build/apply p50/p95/p99 (ms): ' . $themeSample['p50'] . ' / ' . $themeSample['p95'] . ' / ' . $themeSample['p99']
                . ' · route/job: `' . $themeSample['route_or_job'] . '` · samples: ' . $themeSample['samples'] . "\n";
        }
        $webauthnSample = $this->webauthnSample();
        $out .= '- WebAuthn ceremony p50/p95/p99 (ms): ' . $webauthnSample['p50'] . ' / ' . $webauthnSample['p95'] . ' / ' . $webauthnSample['p99']
            . ' · route/job: `' . $webauthnSample['route_or_job'] . '` · samples: ' . $webauthnSample['samples'] . "\n";
        $propagationSample = $this->propagationSample();
        if ($propagationSample !== null) {
            $out .= '- Assignment-change propagation p50/p95/p99 (ms): ' . $propagationSample['p50'] . ' / ' . $propagationSample['p95'] . ' / ' . $propagationSample['p99']
                . ' · route/job: `' . $propagationSample['route_or_job'] . '` · iterations: ' . $propagationSample['samples']
                . ' · stale-after-revoke: ' . $propagationSample['anomalies'] . "\n";
        }
        $simulatorSample = $this->simulatorSample();
        if ($simulatorSample !== null) {
            $out .= '- Simulator duration p50/p95/p99 (ms): ' . $simulatorSample['p50'] . ' / ' . $simulatorSample['p95'] . ' / ' . $simulatorSample['p99']
                . ' · route/job: `' . $simulatorSample['route_or_job'] . '` · iterations: ' . $simulatorSample['samples'] . "\n";
        }
        $out .= '- Queries: ' . $env['query_count'] . ' · query time (ms): ' . $env['query_time_ms']
             . ' · peak mem (bytes): ' . $env['peak_memory_bytes'] . ' · error rate: ' . $env['error_rate'] . "\n\n";
        $out .= "## Budgets vs D11 targets (ADR 0004 D11)\n\n";
        $out .= "| Budget | Metric | Target | Measured | Status |\n";
        $out .= "|---|---|---|---|---|\n";
        foreach ($this->rows() as $r) {
            $out .= '| `' . $r['key'] . '` | ' . $r['metric'] . ' | ' . $r['target'] . ' | ' . $r['measured'] . ' | ' . $r['status'] . " |\n";
        }
        if ($this->includeLifecycleSamples) {
            $out .= "\n## Measured-only metrics (§11.3, no D11 gate)\n\n";
            $out .= "PHASE_5_PLAN §11.3 requires \"assignment-change propagation\" and \"simulator\n";
            $out .= "duration\" to be measured; ADR 0004 D11 sets no gate value for either, so both\n";
            $out .= "carry status `MEASURED (no D11 target)` (no PASS/FAIL). Both are measured fresh\n";
            $out .= "on every `verify:phase5-budgets` run — the samplers build their own fixtures —\n";
            $out .= "so run-to-run variance is expected and carries no gate consequence, and the rows\n";
            $out .= "survive regeneration (they are emitted by the generator, not hand-appended).\n\n";
            $out .= "- `role_assignment.change_propagation_p95`: per iteration creates a fresh\n";
            $out .= "  site-scope assignment, then times `RoleAssignmentService::revoke()` immediately\n";
            $out .= "  followed by `CapabilityResolver::can()` for the revoked subject. Decisions read\n";
            $out .= "  the live `role_assignments` table and `revoke()` calls `invalidate()` in-request,\n";
            $out .= "  so propagation is structurally immediate; `stale-after-revoke` is a correctness\n";
            $out .= "  sentinel (expected 0).\n";
            $out .= "- `permission_simulator.duration_p95`: times `PermissionSimulatorService::simulate()`\n";
            $out .= "  cycling the F9 fixture's users/boards.\n";
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
