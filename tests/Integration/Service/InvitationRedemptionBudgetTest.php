<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Service\BaselineMetricsService;
use App\Service\Phase5BudgetReportService;
use Tests\Support\TestCase;

/**
 * D11 `invitation.redemption_p95` (P5-13 / Inc 9, target 500 ms): the sampler
 * measures the full production redemption path — uniform token check, guarded
 * consume, account creation (including the password hash), board grant and
 * audit — and the budget report surfaces it as a MEASURED row when lifecycle
 * samplers are opted in (as `bin/console verify:phase5-budgets` does).
 */
final class InvitationRedemptionBudgetTest extends TestCase
{
    public function test_measure_invitation_redemption_produces_a_p95_sample(): void
    {
        $result = (new BaselineMetricsService($this->db))->measureInvitationRedemption(iterations: 20);

        self::assertSame(20, $result['samples']);
        self::assertSame(0.0, $result['error_rate'], 'every bench redemption must succeed');
        self::assertGreaterThan(0.0, $result['p95']);
        self::assertLessThan(500.0, $result['p95'], 'D11 target sanity on the test fixture');
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

    public function test_budget_report_marks_invitation_redemption_measured(): void
    {
        $report = new Phase5BudgetReportService($this->db, null, null, null, null, null, null, true);
        $rows = [];
        foreach ($report->rows() as $row) {
            $rows[$row['key']] = $row;
        }

        self::assertStringStartsWith('MEASURED', $rows['invitation.redemption_p95']['status']);
        self::assertStringContainsString('ms invite redeem', $rows['invitation.redemption_p95']['measured']);
        self::assertStringContainsString('Invitation redemption p50/p95/p99', $report->render());
    }
}
