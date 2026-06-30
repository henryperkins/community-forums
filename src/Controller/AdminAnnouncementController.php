<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\SettingRepository;
use App\Service\AnnouncementService;
use App\Service\RateLimitService;

/**
 * Admin announcements console (ADMIN §7.4). Compose the site banner and opt into
 * an in-app broadcast, or clear the banner. Flag-gated behind `announcements`;
 * every action requires an admin. No email channel.
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
        return $this->view('admin/announcements', [
            'announcement' => $this->currentAnnouncement(),
            'errors' => [],
            'old' => [],
        ]);
    }

    /** @param array<string,string> $params */
    public function save(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        // Clearing is a distinct, un-validated, un-throttled action.
        if ($request->str('action') === 'clear') {
            $this->service()->clearBanner($admin);
            return $this->redirectWithFlash('/admin/announcements', 'Announcement cleared.');
        }

        // Throttle publishes per-admin (RateLimitService keys signed-in callers by
        // account); throws HTTP 429 which the kernel renders.
        $this->container->get(RateLimitService::class)->enforce('announce', $request, $admin);

        try {
            $this->service()->setBanner(
                $admin,
                $request->str('message'),
                $request->post('dismissible') !== null,
                $request->post('broadcast') !== null,
                $request->post('broadcast_email') !== null,
            );
        } catch (ValidationException $e) {
            return $this->view('admin/announcements', [
                'announcement' => $this->currentAnnouncement(),
                'errors' => $e->errors,
                'old' => $e->old + [
                    'message' => $request->str('message'),
                    'dismissible' => $request->post('dismissible') !== null,
                    'broadcast' => $request->post('broadcast') !== null,
                    'broadcast_email' => $request->post('broadcast_email') !== null,
                ],
            ], 422);
        }
        return $this->redirectWithFlash('/admin/announcements', 'Announcement published.');
    }

    /** @return array<string,mixed> */
    private function currentAnnouncement(): array
    {
        $current = $this->container->get(SettingRepository::class)->get('site_announcement', []);
        return is_array($current) ? $current : [];
    }
}
