<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\BadgeRepository;
use App\Repository\BoardRepository;
use App\Service\BadgeRuleService;

final class AdminBadgeRuleController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $this->requireEnabled();
        $this->requireAdmin();

        return $this->view('admin/badge_rules', [
            'rules' => $this->container->get(BadgeRuleService::class)->rules(),
            'badges' => $this->container->get(BadgeRepository::class)->all(),
            'boards' => $this->container->get(BoardRepository::class)->allOrdered(),
            'errors' => [],
            'old' => [],
        ]);
    }

    public function create(Request $request, array $params): Response
    {
        $this->requireEnabled();
        $admin = $this->requireAdmin();
        try {
            $this->container->get(BadgeRuleService::class)->create($admin, $request->allInput());
        } catch (ValidationException $e) {
            return $this->view('admin/badge_rules', [
                'rules' => $this->container->get(BadgeRuleService::class)->rules(),
                'badges' => $this->container->get(BadgeRepository::class)->all(),
                'boards' => $this->container->get(BoardRepository::class)->allOrdered(),
                'errors' => $e->errors,
                'old' => $e->old,
            ], 422);
        }

        return $this->redirectWithFlash('/admin/badge-rules', 'Badge rule created.');
    }

    public function preview(Request $request, array $params): Response
    {
        $this->requireEnabled();
        $this->requireAdmin();
        $preview = $this->container->get(BadgeRuleService::class)->preview((int) ($params['id'] ?? 0));

        return $this->view('admin/badge_rule_preview', $preview);
    }

    public function enable(Request $request, array $params): Response
    {
        $this->requireEnabled();
        $this->container->get(BadgeRuleService::class)->enable($this->requireAdmin(), (int) ($params['id'] ?? 0));
        return $this->redirectWithFlash('/admin/badge-rules', 'Badge rule enabled.');
    }

    public function disable(Request $request, array $params): Response
    {
        $this->requireEnabled();
        $this->container->get(BadgeRuleService::class)->disable($this->requireAdmin(), (int) ($params['id'] ?? 0));
        return $this->redirectWithFlash('/admin/badge-rules', 'Badge rule disabled.');
    }

    public function confirmBackfill(Request $request, array $params): Response
    {
        $this->requireEnabled();
        return $this->confirmationView((int) ($params['id'] ?? 0), 'backfill');
    }

    public function confirmRevoke(Request $request, array $params): Response
    {
        $this->requireEnabled();
        return $this->confirmationView((int) ($params['id'] ?? 0), 'revoke');
    }

    public function backfill(Request $request, array $params): Response
    {
        $this->requireEnabled();
        $admin = $this->requireAdmin();
        $ruleId = (int) ($params['id'] ?? 0);
        try {
            $count = $this->container->get(BadgeRuleService::class)->backfill($admin, $ruleId, $request->allInput());
        } catch (ValidationException $e) {
            return $this->confirmationView($ruleId, 'backfill', $e->errors, $e->old, 422);
        }
        return $this->redirectWithFlash('/admin/badge-rules', 'Badge rule backfilled ' . $count . ' awards.');
    }

    public function revoke(Request $request, array $params): Response
    {
        $this->requireEnabled();
        $admin = $this->requireAdmin();
        $ruleId = (int) ($params['id'] ?? 0);
        try {
            $count = $this->container->get(BadgeRuleService::class)->revoke($admin, $ruleId, $request->allInput());
        } catch (ValidationException $e) {
            return $this->confirmationView($ruleId, 'revoke', $e->errors, $e->old, 422);
        }
        return $this->redirectWithFlash('/admin/badge-rules', 'Badge rule revoked ' . $count . ' awards.');
    }

    /** @param array<string,string> $errors @param array<string,mixed> $old */
    private function confirmationView(int $ruleId, string $action, array $errors = [], array $old = [], int $status = 200): Response
    {
        $impact = $this->container->get(BadgeRuleService::class)->confirmation($this->requireAdmin(), $ruleId, $action);
        return $this->view('admin/badge_rule_confirm', $impact + ['errors' => $errors, 'old' => $old], $status);
    }

    private function requireEnabled(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('badge_rules')) {
            throw new NotFoundException('Not found.');
        }
    }
}
