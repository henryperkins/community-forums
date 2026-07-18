<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Service\AdminSettingsService;

final class AdminSettingsController extends Controller
{
    /** @param array<string,string> $params */
    public function general(Request $request, array $params): Response
    {
        $this->requireAdmin();
        return $this->generalView();
    }

    /** @param array<string,string> $params */
    public function updateSite(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        try {
            $this->container->get(AdminSettingsService::class)
                ->updateSiteName($admin, $request->str('site_name'));
        } catch (ValidationException $e) {
            return $this->generalView([
                'settings_errors' => $e->errors,
                'settings_old' => array_replace($request->allInput(), $e->old),
            ], 422);
        }

        return $this->redirectWithFlash('/admin/settings', 'Site name updated.');
    }

    /** @param array<string,string> $params */
    public function updateRegistration(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        try {
            $this->container->get(AdminSettingsService::class)
                ->updateRegistration($admin, $request->str('registration_mode'));
        } catch (ValidationException $e) {
            return $this->generalView([
                'settings_errors' => $e->errors,
                'settings_old' => array_replace($request->allInput(), $e->old),
            ], 422);
        }

        return $this->redirectWithFlash('/admin/settings', 'Registration settings updated.');
    }

    /**
     * Method-specific tombstone: Router reports a path-only method miss as
     * 405, while stale combined settings forms must fail as a non-endpoint.
     * No submitted setting is read or written here.
     *
     * @param array<string,string> $params
     */
    public function obsoleteCombinedUpdate(Request $request, array $params): Response
    {
        throw new NotFoundException('Not found.');
    }

    /** @param array<string,string> $params */
    public function moderation(Request $request, array $params): Response
    {
        $this->requireAntiAbuseAdmin();
        return $this->moderationView();
    }

    /** @param array<string,string> $params */
    public function updateAntiAbuse(Request $request, array $params): Response
    {
        $admin = $this->requireAntiAbuseAdmin();
        try {
            $this->container->get(AdminSettingsService::class)
                ->updateAntiAbuse($admin, $request->allInput());
        } catch (ValidationException $e) {
            return $this->moderationView([
                'settings_errors' => $e->errors,
                'settings_old' => array_replace($request->allInput(), $e->old),
            ], 422);
        }

        return $this->redirectWithFlash('/admin/moderation', 'Anti-abuse settings updated.');
    }

    /** @param array<string,mixed> $overlay */
    private function generalView(array $overlay = [], int $status = 200): Response
    {
        return $this->view(
            'admin/settings',
            $this->container->get(AdminSettingsService::class)->generalModel($overlay),
            $status,
        );
    }

    /** @param array<string,mixed> $overlay */
    private function moderationView(array $overlay = [], int $status = 200): Response
    {
        return $this->view(
            'admin/moderation',
            $this->container->get(AdminSettingsService::class)->moderationModel($overlay),
            $status,
        );
    }

    private function requireAntiAbuseAdmin(): \App\Domain\User
    {
        $admin = $this->requireAdmin();
        if (!$this->container->get(FeatureFlags::class)->enabled('anti_abuse')) {
            throw new NotFoundException('Not found.');
        }
        return $admin;
    }
}
