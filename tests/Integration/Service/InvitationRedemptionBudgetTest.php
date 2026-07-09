<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Security\PasswordHasher;
use App\Service\BaselineMetricsService;
use App\Service\Phase5BudgetReportService;
use Tests\Support\TestCase;

/**
 * D11 `invitation.redemption_p95` (P5-13 / Inc 9, target 500 ms): the sampler
 * measures the full production redemption path — uniform token check, guarded
 * consume, account creation, board grant, audit — with PRODUCTION-COST
 * Argon2id hashing (both the test bootstrap and `verify:phase5-budgets`
 * weaken the process-wide hasher for fixture work; the bench must undo that
 * inside its timed region and restore it afterwards, or the published number
 * understates production by orders of magnitude). The report row is gated
 * behind its own opt-in so lifecycle-only callers never pay this bench.
 */
final class InvitationRedemptionBudgetTest extends TestCase
{
    public function test_measure_invitation_redemption_uses_and_restores_production_cost_hashing(): void
    {
        $harnessOptions = PasswordHasher::defaultOptions();
        self::assertNotNull($harnessOptions, 'precondition: the bootstrap weakens the hasher');

        $result = (new BaselineMetricsService($this->db))->measureInvitationRedemption(iterations: 5);

        self::assertSame(5, $result['samples']);
        self::assertSame(0.0, $result['error_rate'], 'every bench redemption must succeed');
        self::assertGreaterThan(0.0, $result['p95']);
        self::assertLessThan(500.0, $result['p95'], 'D11 target sanity on the test fixture');
        self::assertStringContainsString('production-cost', (string) $result['data_fixture']);
        self::assertSame($harnessOptions, PasswordHasher::defaultOptions(), 'the process-wide harness override must be restored');
    }

    public function test_sampler_requires_a_caller_owned_rollback_transaction(): void
    {
        $this->pdo->rollBack();
        try {
            (new BaselineMetricsService($this->db))->measureInvitationRedemption(iterations: 1);
            self::fail('expected the sampler to refuse outside a rollback transaction');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('transaction', $e->getMessage());
        } finally {
            $this->pdo->beginTransaction();
        }
    }

    public function test_budget_report_gates_the_invitation_row_behind_its_own_opt_in(): void
    {
        // NOT coupled to the shared lifecycle opt-in: lifecycle-only callers
        // (the pre-existing Inc 6 report tests) must not pay this bench.
        $without = new Phase5BudgetReportService($this->db, null, null, null, null, null, null, false, false);
        $rows = [];
        foreach ($without->rows() as $row) {
            $rows[$row['key']] = $row;
        }
        self::assertStringStartsWith('PENDING', $rows['invitation.redemption_p95']['status']);

        $with = new Phase5BudgetReportService($this->db, null, null, null, null, null, null, false, true);
        $rows = [];
        foreach ($with->rows() as $row) {
            $rows[$row['key']] = $row;
        }
        self::assertStringStartsWith('MEASURED', $rows['invitation.redemption_p95']['status']);
        self::assertStringContainsString('ms invite redeem', $rows['invitation.redemption_p95']['measured']);
        self::assertStringContainsString('Invitation redemption p50/p95/p99', $with->render());
    }
}
