<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Phase5Budgets;
use PHPUnit\Framework\TestCase;

final class Phase5BudgetsTest extends TestCase
{
    private const STATISTICS = ['p50', 'p95', 'p99', 'max'];
    private const PHASES = ['foundation', 'inc1', 'inc2', 'inc3', 'inc4', 'inc5', 'inc7', 'inc8', 'inc9', 'gate_b', 'staged-enablement'];

    public function test_catalogue_encodes_all_d11_budgets_plus_theme_build_apply_derivative(): void
    {
        self::assertCount(12, Phase5Budgets::all(), 'ADR 0004 D11 lists 11 numeric budgets plus the Inc 4 theme build/apply measurement under the declarative-package umbrella');
    }

    public function test_key_targets_match_adr_0004_d11(): void
    {
        self::assertSame(5, Phase5Budgets::target('resolver.p95'), 'resolver p95 = 5ms');
        self::assertSame(500, Phase5Budgets::target('invitation.redemption_p95'));
        self::assertSame(5000, Phase5Budgets::target('webhook.delivery_timeout'));
        self::assertSame(250, Phase5Budgets::target('registry.signature_verify_p95'));
        self::assertSame(86400, Phase5Budgets::target('registry.snapshot_freshness'));
        self::assertSame(2000, Phase5Budgets::target('registry.fetch_p95'));
        self::assertSame(10000, Phase5Budgets::target('package.install_update_p95'));
        self::assertSame(10000, Phase5Budgets::target('theme.build_apply_p95'));
        self::assertSame(2000, Phase5Budgets::target('webauthn.ceremony_p95'));
        self::assertSame(2000, Phase5Budgets::target('oidc.discovery_p95_cached'));
        self::assertSame(5000, Phase5Budgets::target('oidc.discovery_p95_cold'));
        self::assertSame(2000, Phase5Budgets::target('sandbox.walltime_default'));
    }

    public function test_every_budget_has_a_valid_shape(): void
    {
        foreach (Phase5Budgets::all() as $key => $b) {
            self::assertIsInt($b['target'], "$key target");
            self::assertGreaterThan(0, $b['target'], "$key target > 0");
            self::assertContains($b['statistic'], self::STATISTICS, "$key statistic");
            self::assertContains($b['measurable_at'], self::PHASES, "$key measurable_at");
            self::assertNotSame('', trim($b['unit']), "$key unit");
            self::assertNotSame('', trim($b['metric']), "$key metric");
        }
    }

    public function test_resolver_budget_is_measurable_at_foundation(): void
    {
        // Foundation measures the legacy-authority baseline the resolver must beat.
        self::assertSame('foundation', Phase5Budgets::get('resolver.p95')['measurable_at']);
    }
}
