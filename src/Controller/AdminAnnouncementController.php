<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Service\AnnouncementService;

/**
 * Admin announcements console (ADMIN §7.4). Compose the site banner and opt into
 * an in-app broadcast, or clear the banner. Flag-gated behind `announcements`;
 * every action requires an admin.
 */
final class AdminAnnouncementController extends Controller
{
    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('announcements')) {
            throw new NotFoundException();
        }
    }

    private function service(): AnnouncementService
    {
        return $this->container->get(AnnouncementService::class);
    }

    /** @param array<string,string> $params */
    public function form(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();
        return $this->view('admin/announcements', $this->service()->consoleModel());
    }

    /** @param array<string,string> $params */
    public function save(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $outcome = $this->service()->saveConsole($admin, $request);
        if ($outcome['model'] !== null) {
            return $this->view('admin/announcements', $outcome['model'], $outcome['status']);
        }
        return $this->redirectWithFlash('/admin/announcements', (string) $outcome['message']);
    }
}
