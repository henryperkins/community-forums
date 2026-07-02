<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\InstalledPackageRepository;
use App\Repository\PackageRepository;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PermissionDiff;
use App\Security\Registry\RegistryVerificationException;
use App\Service\Packages\PackageLifecycleService;
use App\Service\Packages\PackageUpdateService;
use App\Service\Registry\RegistryCatalogService;

/**
 * Server-rendered package lifecycle surface. {id} is always packages.id; local
 * install rows are resolved server-side so operators cannot target another row.
 */
final class AdminPackageLifecycleController extends Controller
{
    /** @param array<string,string> $params */
    public function plan(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $releaseId = $this->releaseId($request);

        try {
            $plan = $this->lifecycle()->plan($admin, $packageId, $releaseId);
            return $this->noindex($this->view('admin/package_plan', [
                'plan' => $plan,
                'errors' => [],
            ]));
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, ['plan' => $this->policyMessage($e)], 422);
        }
    }

    /** @param array<string,string> $params */
    public function install(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $releaseId = $this->releaseId($request);

        try {
            $this->lifecycle()->install($admin, (string) $request->post('current_password', ''), $packageId, $releaseId);
            return $this->noindex($this->redirect('/admin/packages/' . $packageId . '/consent'));
        } catch (ValidationException $e) {
            return $this->planView($admin, $packageId, $releaseId, $e->errors);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, ['install' => $this->policyMessage($e)], 422);
        }
    }

    /** @param array<string,string> $params */
    public function consentForm(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        return $this->consentView($admin, (int) ($params['id'] ?? 0), [], 200);
    }

    /** @param array<string,string> $params */
    public function consent(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireInstallRow($packageId);

        try {
            if ($install['staged_release_id'] !== null) {
                $this->updates()->applyStaged($admin, (string) $request->post('current_password', ''), (int) $install['id']);
            } else {
                $this->lifecycle()->consent($admin, (string) $request->post('current_password', ''), (int) $install['id']);
            }
            return $this->noindex($this->redirect('/admin/packages/' . $packageId));
        } catch (ValidationException $e) {
            return $this->consentView($admin, $packageId, $e->errors, 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->consentView($admin, $packageId, ['consent' => $this->policyMessage($e)], 422);
        }
    }

    /** @param array<string,string> $params */
    public function enable(Request $request, array $params): Response
    {
        return $this->passwordAction(
            $request,
            $params,
            'enable',
            fn (User $admin, array $install, string $password): mixed =>
                $this->lifecycle()->enable($admin, $password, (int) $install['id']),
        );
    }

    /** @param array<string,string> $params */
    public function disable(Request $request, array $params): Response
    {
        return $this->simpleAction(
            $request,
            $params,
            'disable',
            fn (User $admin, array $install): mixed =>
                $this->lifecycle()->disable($admin, (int) $install['id']),
        );
    }

    /** @param array<string,string> $params */
    public function pin(Request $request, array $params): Response
    {
        $pinned = $request->str('pinned') === '1';

        return $this->simpleAction(
            $request,
            $params,
            'pin',
            fn (User $admin, array $install): mixed =>
                $this->lifecycle()->setPinned($admin, (int) $install['id'], $pinned),
        );
    }

    /** @param array<string,string> $params */
    public function updatePolicy(Request $request, array $params): Response
    {
        $policy = $request->str('policy');

        return $this->simpleAction(
            $request,
            $params,
            'update_policy',
            fn (User $admin, array $install): mixed =>
                $this->lifecycle()->setUpdatePolicy($admin, (int) $install['id'], $policy),
        );
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireInstallRow($packageId);

        try {
            $result = $this->updates()->update(
                $admin,
                (string) $request->post('current_password', ''),
                (int) $install['id'],
                $this->releaseId($request),
            );
            $target = $result['status'] === 'staged'
                ? '/admin/packages/' . $packageId . '/consent'
                : '/admin/packages/' . $packageId;

            return $this->noindex($this->redirect($target));
        } catch (ValidationException $e) {
            return $this->detailView($packageId, $e->errors, 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, ['update' => $this->policyMessage($e)], 422);
        }
    }

    /** @param array<string,string> $params */
    public function cancelUpdate(Request $request, array $params): Response
    {
        return $this->simpleAction(
            $request,
            $params,
            'update_cancel',
            fn (User $admin, array $install): mixed =>
                $this->updates()->cancelStaged($admin, (int) $install['id']),
        );
    }

    /** @param array<string,string> $params */
    public function rollback(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireInstallRow($packageId);

        try {
            $result = $this->updates()->rollback(
                $admin,
                (string) $request->post('current_password', ''),
                (int) $install['id'],
                (int) $request->str('release_id'),
            );
            $target = $result['status'] === 'staged'
                ? '/admin/packages/' . $packageId . '/consent'
                : '/admin/packages/' . $packageId;

            return $this->noindex($this->redirect($target));
        } catch (ValidationException $e) {
            return $this->detailView($packageId, $e->errors, 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, ['rollback' => $this->policyMessage($e)], 422);
        }
    }

    /** @param array<string,string> $params */
    public function uninstall(Request $request, array $params): Response
    {
        return $this->passwordAction(
            $request,
            $params,
            'uninstall',
            fn (User $admin, array $install, string $password): mixed =>
                $this->lifecycle()->uninstall($admin, $password, (int) $install['id']),
        );
    }

    /** @param array<string,string> $params */
    public function export(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireInstallRow($packageId);

        try {
            $export = $this->lifecycle()->export($admin, (int) $install['id']);
            return $this->noindex(
                Response::json($export)
                    ->header('Content-Disposition', 'attachment; filename="package-export-' . $packageId . '.json"'),
            );
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, ['export' => $this->policyMessage($e)], 422);
        }
    }

    /** @param array<string,string> $params */
    public function reverify(Request $request, array $params): Response
    {
        return $this->simpleAction(
            $request,
            $params,
            'reverify',
            fn (User $admin, array $install): mixed =>
                $this->lifecycle()->reverify($admin, (int) $install['id']),
        );
    }

    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('package_registry')) {
            throw new NotFoundException();
        }
    }

    private function noindex(Response $response): Response
    {
        return $response->header('X-Robots-Tag', 'noindex');
    }

    private function lifecycle(): PackageLifecycleService
    {
        return $this->container->get(PackageLifecycleService::class);
    }

    private function updates(): PackageUpdateService
    {
        return $this->container->get(PackageUpdateService::class);
    }

    private function releaseId(Request $request): ?int
    {
        $releaseId = $request->str('release_id');

        return $releaseId !== '' ? (int) $releaseId : null;
    }

    /** @return array<string,mixed> */
    private function requireInstallRow(int $packageId): array
    {
        if ($this->container->get(PackageRepository::class)->find($packageId) === null) {
            throw new NotFoundException('Package not found.');
        }

        $install = $this->container->get(InstalledPackageRepository::class)->findByPackage($packageId);
        if ($install === null) {
            throw new NotFoundException('Package is not installed.');
        }

        return $install;
    }

    /**
     * @param array<string,string> $params
     * @param callable(User,array<string,mixed>,string):mixed $call
     */
    private function passwordAction(Request $request, array $params, string $errorKey, callable $call): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireInstallRow($packageId);

        try {
            $call($admin, $install, (string) $request->post('current_password', ''));
            return $this->noindex($this->redirect('/admin/packages/' . $packageId));
        } catch (ValidationException $e) {
            return $this->detailView($packageId, $e->errors, 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, [$errorKey => $this->policyMessage($e)], 422);
        }
    }

    /**
     * @param array<string,string> $params
     * @param callable(User,array<string,mixed>):mixed $call
     */
    private function simpleAction(Request $request, array $params, string $errorKey, callable $call): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireInstallRow($packageId);

        try {
            $call($admin, $install);
            return $this->noindex($this->redirect('/admin/packages/' . $packageId));
        } catch (ValidationException $e) {
            return $this->detailView($packageId, $e->errors, 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, [$errorKey => $this->policyMessage($e)], 422);
        }
    }

    /** @param array<string,string> $errors */
    private function detailView(int $packageId, array $errors, int $status): Response
    {
        $detail = $this->container->get(RegistryCatalogService::class)->detail($packageId);
        if ($detail === null) {
            throw new NotFoundException('Package not found.');
        }

        return $this->noindex($this->view('admin/package_detail', $this->withDetailExtras($detail) + [
            'errors' => $errors,
        ], $status));
    }

    /** @param array<string,string> $errors */
    private function planView(User $admin, int $packageId, ?int $releaseId, array $errors): Response
    {
        $plan = $this->lifecycle()->plan($admin, $packageId, $releaseId);

        return $this->noindex($this->view('admin/package_plan', [
            'plan' => $plan,
            'errors' => $errors,
        ], 422));
    }

    /** @param array<string,string> $errors */
    private function consentView(User $admin, int $packageId, array $errors, int $status): Response
    {
        $detail = $this->container->get(RegistryCatalogService::class)->detail($packageId);
        if ($detail === null) {
            throw new NotFoundException('Package not found.');
        }
        if ($detail['installed'] === null) {
            throw new NotFoundException('Package is not installed.');
        }

        $install = $detail['installed'];
        $stagedPlan = null;
        if ($install['staged_release_id'] !== null) {
            $stagedPlan = $this->updates()->updatePlan($admin, (int) $install['id'], (int) $install['staged_release_id']);
        }

        return $this->noindex($this->view('admin/package_consent', [
            'detail' => $this->withDetailExtras($detail),
            'install' => $install,
            'pending_permissions' => $this->permissionRows(array_values(array_filter(
                $detail['installed_permissions'],
                static fn (array $row): bool => (int) $row['granted'] === 0,
            ))),
            'staged_plan' => $stagedPlan,
            'errors' => $errors,
        ], $status));
    }

    /**
     * @param array<string,mixed> $detail
     * @return array<string,mixed>
     */
    private function withDetailExtras(array $detail): array
    {
        $installed = $detail['installed'] ?? null;
        $detail['rollback_targets'] = $installed !== null
            ? $this->updates()->rollbackTargets((int) $installed['id'])
            : [];
        $detail['permission_labels'] = $this->permissionRows($detail['installed_permissions'] ?? []);

        return $detail;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function permissionRows(array $rows): array
    {
        return array_map(
            static function (array $row): array {
                $key = (string) ($row['permission_key'] ?? $row['key'] ?? '');
                $kind = (string) ($row['kind'] ?? '');
                $label = PermissionDiff::describe($kind, $key);

                return $row + [
                    'permission_key' => $key,
                    'risk_class' => $row['risk_class'] ?? $label['risk'],
                    'label' => $label['label'],
                ];
            },
            $rows,
        );
    }

    private function policyMessage(PackagePolicyException | RegistryVerificationException $e): string
    {
        return (string) $e->code . ': ' . $e->getMessage();
    }
}
