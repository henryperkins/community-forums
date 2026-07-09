<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Security\Packages\PermissionDiff;
use App\Service\Packages\PackageIntegrationService;
use App\Service\Packages\PackageSettingsService;
use App\Service\Packages\PackageUpdateService;
use App\Service\Registry\RegistryCatalogService;

/** Staff catalogue browse (flag-gated by package_registry); lifecycle POSTs live in the paired controller. */
final class AdminPackagesController extends Controller
{
    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('package_registry')) {
            throw new NotFoundException();
        }
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        return $this->noindex($this->view('admin/packages', [
            'data' => $this->container->get(RegistryCatalogService::class)->overview(),
        ]));
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        $detail = $this->container->get(RegistryCatalogService::class)->detail((int) ($params['id'] ?? 0));
        if ($detail === null) {
            throw new NotFoundException('Package not found.');
        }

        $installed = $detail['installed'];
        $detail['rollback_targets'] = $installed !== null
            ? $this->container->get(PackageUpdateService::class)->rollbackTargets((int) $installed['id'])
            : [];
        $detail['permission_labels'] = $this->permissionRows($detail['installed_permissions']);

        if ($installed !== null
            && in_array((string) ($detail['package']['type'] ?? ''), ['remote_app', 'automation'], true)) {
            $installedId = (int) $installed['id'];
            $detail['integration'] = $this->container->get(PackageIntegrationService::class)->overview($installedId);
            $detail['settings_describe'] = $this->container->get(PackageSettingsService::class)->describe($installedId);
        }

        return $this->noindex($this->view('admin/package_detail', $detail));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function permissionRows(array $rows): array
    {
        return array_map(
            static function (array $row): array {
                $key = (string) ($row['permission_key'] ?? '');
                $label = PermissionDiff::describe((string) $row['kind'], $key);

                return $row + [
                    'permission_key' => $key,
                    'risk_class' => $row['risk_class'] ?? $label['risk'],
                    'label' => $label['label'],
                ];
            },
            $rows,
        );
    }
}
