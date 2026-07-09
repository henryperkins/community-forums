<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\InstalledPackageRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageThemeRepository;
use App\Security\Packages\PackagePolicyException;
use App\Service\Packages\ThemeStateService;

/** No-JS operator surface for declarative theme packages. */
final class AdminThemeController extends Controller
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        return $this->indexView();
    }

    /** @param array<string,string> $params */
    public function preview(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $build = $this->themes()->preview($admin, (int) ($params['id'] ?? 0));
            $this->session()->set('theme_preview_build', (int) $build['id']);

            return $this->noindex($this->redirectWithFlash('/admin/themes', 'Previewing this theme in your session only.'));
        } catch (PackagePolicyException $e) {
            return $this->indexView(['theme' => $this->policyMessage($e)], 422);
        }
    }

    /** @param array<string,string> $params */
    public function clearPreview(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();
        $this->session()->forget('theme_preview_build');

        return $this->noindex($this->redirectWithFlash('/admin/themes', 'Theme preview ended.'));
    }

    /** @param array<string,string> $params */
    public function activate(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->themes()->activate($admin, (string) $request->post('current_password', ''), (int) ($params['id'] ?? 0));
            $this->session()->forget('theme_preview_build');

            return $this->noindex($this->redirectWithFlash('/admin/themes', 'Theme activated.'));
        } catch (ValidationException $e) {
            return $this->indexView($e->errors, 422);
        } catch (PackagePolicyException $e) {
            return $this->indexView(['theme' => $this->policyMessage($e)], 422);
        }
    }

    /** @param array<string,string> $params */
    public function rollback(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->themes()->rollback($admin, (string) $request->post('current_password', ''));

            return $this->noindex($this->redirectWithFlash('/admin/themes', 'Rolled back to the last-known-good theme.'));
        } catch (ValidationException $e) {
            return $this->indexView($e->errors, 422);
        } catch (PackagePolicyException $e) {
            return $this->indexView(['theme' => $this->policyMessage($e)], 422);
        }
    }

    /** @param array<string,string> $params */
    public function safeModeForm(Request $request, array $params): Response
    {
        $this->requireAdmin();

        return $this->safeModeView();
    }

    /** @param array<string,string> $params */
    public function safeMode(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();

        try {
            if ($request->str('exit') === '1') {
                $this->themes()->setSafeMode($admin, false, (string) $request->post('current_password', ''));
                return $this->noindex($this->redirectWithFlash('/admin/themes/safe-mode', 'Theme safe mode was exited.'));
            }

            $this->themes()->setSafeMode($admin, true);
            return $this->noindex($this->redirectWithFlash('/admin/themes/safe-mode', 'Theme safe mode is on.'));
        } catch (ValidationException $e) {
            return $this->safeModeView($e->errors, 422);
        }
    }

    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('package_themes')) {
            throw new NotFoundException();
        }
    }

    /** @param array<string,string> $errors */
    private function indexView(array $errors = [], int $status = 200): Response
    {
        return $this->noindex($this->view('admin/themes', $this->themeData() + [
            'errors' => $errors,
        ], $status));
    }

    /** @param array<string,string> $errors */
    private function safeModeView(array $errors = [], int $status = 200): Response
    {
        return $this->noindex($this->view('admin/theme_safe_mode', [
            'safe_mode' => $this->themes()->safeMode(),
            'forced_safe_mode' => (bool) $this->config()->get('theme.safe_mode', false),
            'errors' => $errors,
        ], $status));
    }

    /** @return array<string,mixed> */
    private function themeData(): array
    {
        $repo = $this->themeRepo();
        $state = $repo->state();
        $installs = $repo->themeInstalls();
        foreach ($installs as &$install) {
            $builds = $repo->buildsForInstall((int) $install['id']);
            $install['latest_build'] = $builds[0] ?? null;
        }
        unset($install);

        $previewId = $this->session()->get('theme_preview_build');
        $preview = $this->themes()->previewBuildFor(is_int($previewId) ? $previewId : null);
        if ($previewId !== null && $preview === null) {
            $this->session()->forget('theme_preview_build');
        }

        return [
            'safe_mode' => $this->themes()->safeMode(),
            'state' => $state,
            'active' => $this->buildSummary($state['active_build_id']),
            'lkg' => $this->buildSummary($state['lkg_build_id']),
            'preview' => $preview !== null ? $this->buildSummary((int) $preview['id']) : null,
            'installs' => $installs,
        ];
    }

    /** @return array<string,mixed>|null */
    private function buildSummary(?int $buildId): ?array
    {
        if ($buildId === null) {
            return null;
        }
        $build = $this->themeRepo()->findBuild($buildId);
        if ($build === null) {
            return null;
        }

        $package = $this->container->get(PackageRepository::class)->find((int) $build['package_id']);
        $release = $this->container->get(PackageReleaseRepository::class)->find((int) $build['release_id']);
        $install = $this->container->get(InstalledPackageRepository::class)->find((int) $build['installed_package_id']);

        return $build + [
            'package_uid' => $package !== null ? (string) $package['package_uid'] : '',
            'package_name' => $package !== null ? (string) $package['name'] : '',
            'release_version' => $release !== null ? (string) $release['version'] : '',
            'install_state' => $install !== null ? (string) $install['state'] : '',
        ];
    }

    private function themes(): ThemeStateService
    {
        return $this->container->get(ThemeStateService::class);
    }

    private function themeRepo(): PackageThemeRepository
    {
        return $this->container->get(PackageThemeRepository::class);
    }

    private function policyMessage(PackagePolicyException $e): string
    {
        return (string) $e->code . ': ' . $e->getMessage();
    }
}
