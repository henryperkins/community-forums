<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\InstalledPackageRepository;
use App\Repository\PackageRepository;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PermissionDiff;
use App\Security\Registry\RegistryVerificationException;
use App\Service\Packages\PackageIntegrationService;
use App\Service\Packages\PackageSettingsService;
use App\Service\Registry\RegistryCatalogService;

/**
 * No-JS operator surface for animating remote_app / automation installs:
 * per-install settings, package-owned credentials, and defensive pause/export.
 * {id} is packages.id; the install row is resolved server-side so an operator
 * cannot target another row. Deploy-dark behind package_registry: gate() throws
 * NotFoundException (404, never 403/405) before AND after requireAdmin().
 */
final class AdminPackageIntegrationController extends Controller
{
    /** @param array<string,string> $params */
    public function saveSettings(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireIntegrationInstall($packageId);

        $input = $request->allInput();
        unset($input['_token'], $input['current_password']);

        try {
            $this->settings()->save($admin, (string) $request->post('current_password', ''), (int) $install['id'], $input);
            return $this->noindex($this->redirect('/admin/packages/' . $packageId . '#integration'));
        } catch (ValidationException $e) {
            return $this->detailView($packageId, (int) $install['id'], ['errors' => $e->errors, 'old' => $e->old], 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, (int) $install['id'], ['errors' => ['settings' => $this->policyMessage($e)]], 422);
        }
    }

    /** @param array<string,string> $params */
    public function provision(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireIntegrationInstall($packageId);

        try {
            $reveal = $this->integrations()->provisionCredentials($admin, (string) $request->post('current_password', ''), (int) $install['id']);
            return $this->detailView($packageId, (int) $install['id'], ['reveal' => $reveal], 200);
        } catch (ValidationException $e) {
            return $this->detailView($packageId, (int) $install['id'], ['errors' => $e->errors], 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, (int) $install['id'], ['errors' => ['provision' => $this->policyMessage($e)]], 422);
        }
    }

    /** @param array<string,string> $params */
    public function rotateCredential(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireIntegrationInstall($packageId);
        $credentialId = (int) ($params['credentialId'] ?? 0);

        try {
            $reveal = $this->integrations()->rotateCredential($admin, (string) $request->post('current_password', ''), (int) $install['id'], $credentialId);
            return $this->detailView($packageId, (int) $install['id'], ['reveal' => $reveal], 200);
        } catch (ValidationException $e) {
            return $this->detailView($packageId, (int) $install['id'], ['errors' => $e->errors], 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, (int) $install['id'], ['errors' => ['rotate' => $this->policyMessage($e)]], 422);
        }
    }

    /** @param array<string,string> $params */
    public function revokeCredential(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireIntegrationInstall($packageId);
        $credentialId = (int) ($params['credentialId'] ?? 0);

        try {
            $this->integrations()->revokeCredential($admin, (int) $install['id'], $credentialId);
            return $this->noindex($this->redirect('/admin/packages/' . $packageId . '#integration'));
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, (int) $install['id'], ['errors' => ['revoke' => $this->policyMessage($e)]], 422);
        }
    }

    /** Friction-free defensive pause of all package-owned delivery — no reauth. @param array<string,string> $params */
    public function disableIntegration(Request $request, array $params): Response
    {
        $this->gate();
        $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireIntegrationInstall($packageId);

        $this->integrations()->suspendDelivery((int) $install['id'], 'operator paused integration delivery');
        return $this->noindex($this->redirect('/admin/packages/' . $packageId . '#integration'));
    }

    /** @param array<string,string> $params */
    public function exportSettings(Request $request, array $params): Response
    {
        $this->gate();
        $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireIntegrationInstall($packageId);
        $installedId = (int) $install['id'];

        $overview = $this->integrations()->overview($installedId);
        $described = $this->settings()->describe($installedId);
        $export = [
            'exported_at' => gmdate('c'),
            'package_uid' => $overview['settings_summary']['package_uid'] ?? null,
            'type' => $overview['type'],
            'granted_scopes' => $overview['granted_scopes'],
            'granted_events' => $overview['granted_events'],
            'settings' => [
                'values' => $described['values'],
                'has_secret' => $described['has_secret'],
            ],
            'credentials' => $overview['credentials'],
        ];

        return $this->noindex(
            Response::json($export)
                ->header('Content-Disposition', 'attachment; filename="package-integration-' . $packageId . '.json"'),
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

    private function settings(): PackageSettingsService
    {
        return $this->container->get(PackageSettingsService::class);
    }

    private function integrations(): PackageIntegrationService
    {
        return $this->container->get(PackageIntegrationService::class);
    }

    /**
     * Resolve the install for {id} and refuse any non-integration package type
     * (theme/server_extension) as 404 — the integration surface does not exist.
     *
     * @return array<string,mixed>
     */
    private function requireIntegrationInstall(int $packageId): array
    {
        $package = $this->container->get(PackageRepository::class)->find($packageId);
        if ($package === null) {
            throw new NotFoundException('Package not found.');
        }
        $install = $this->container->get(InstalledPackageRepository::class)->findByPackage($packageId);
        if ($install === null || !in_array((string) $package['type'], ['remote_app', 'automation'], true)) {
            throw new NotFoundException('Package has no integration surface.');
        }
        return $install;
    }

    /**
     * Re-render package detail (with the integration panel) at $status, carrying
     * either field errors or a one-time reveal. Mirrors the lifecycle
     * controller's detailView so the anti-draft-loss re-render keeps context.
     *
     * @param array{errors?:array<string,string>, reveal?:array<string,mixed>} $extra
     */
    private function detailView(int $packageId, int $installedId, array $extra, int $status): Response
    {
        $detail = $this->container->get(RegistryCatalogService::class)->detail($packageId);
        if ($detail === null) {
            throw new NotFoundException('Package not found.');
        }
        $detail['permission_labels'] = $this->permissionRows($detail['installed_permissions'] ?? []);
        $detail['integration'] = $this->integrations()->overview($installedId);
        $detail['settings_describe'] = $this->settings()->describe($installedId);

        // Anti-draft-loss: on a settings 422, overlay the operator's just-typed
        // non-secret values (ValidationException::$old) over the DB-loaded values so
        // valid edits submitted alongside an invalid field are not silently reverted.
        // Secret fields are never repopulated (safeOld already excludes them).
        $oldSettings = $extra['old']['settings'] ?? null;
        if (is_array($oldSettings)) {
            foreach ($detail['settings_describe']['fields'] as $field) {
                $key = (string) $field['key'];
                if (empty($field['secret']) && array_key_exists($key, $oldSettings)) {
                    $detail['settings_describe']['values'][$key] = (string) $oldSettings[$key];
                }
            }
        }

        $detail['reveal'] = $extra['reveal'] ?? null;
        $detail['errors'] = $extra['errors'] ?? [];

        return $this->noindex($this->view('admin/package_detail', $detail, $status));
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
                $label = PermissionDiff::describe((string) ($row['kind'] ?? ''), $key);
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
