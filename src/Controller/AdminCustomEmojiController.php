<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Service\AdminDashboardService;
use App\Service\CustomEmojiService;

final class AdminCustomEmojiController extends Controller
{
    /** @param array<string,string> $params */
    public function create(Request $request, array $params): Response
    {
        $this->requireEmojiAdmin();
        $admin = $this->requireAdmin();
        try {
            $result = $this->container->get(CustomEmojiService::class)->create($admin, $request->allInput());
        } catch (ValidationException $e) {
            // Anti-draft-loss: re-render the dashboard (where the form lives)
            // with the errors + typed input instead of a flash redirect.
            return $this->view(
                'admin/dashboard',
                $this->container->get(AdminDashboardService::class)->dashboardModel([
                    'emoji_errors' => $e->errors,
                    'emoji_old' => $e->old,
                ]),
                422,
            );
        }
        return $this->redirectWithFlash('/admin', $result['replaced']
            ? 'Custom emoji replaced — :' . $result['shortcode'] . ': already existed.'
            : 'Custom emoji saved.');
    }

    /** @param array<string,string> $params */
    public function enable(Request $request, array $params): Response
    {
        $this->requireEmojiAdmin();
        $this->container->get(CustomEmojiService::class)->setEnabled($this->requireAdmin(), (string) ($params['shortcode'] ?? ''), true);
        return $this->redirectWithFlash('/admin', 'Custom emoji enabled.');
    }

    /** @param array<string,string> $params */
    public function disable(Request $request, array $params): Response
    {
        $this->requireEmojiAdmin();
        $this->container->get(CustomEmojiService::class)->setEnabled($this->requireAdmin(), (string) ($params['shortcode'] ?? ''), false);
        return $this->redirectWithFlash('/admin', 'Custom emoji disabled.');
    }

    private function requireEmojiAdmin(): void
    {
        $this->requireAdmin();
        if (!$this->container->get(FeatureFlags::class)->enabled('custom_emoji')) {
            throw new NotFoundException('Not found.');
        }
    }
}
