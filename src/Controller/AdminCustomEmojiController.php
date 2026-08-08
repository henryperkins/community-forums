<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Service\CustomEmojiService;

final class AdminCustomEmojiController extends Controller
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireEmojiAdmin();
        return $this->page();
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params): Response
    {
        $admin = $this->requireEmojiAdmin();
        try {
            $result = $this->container->get(CustomEmojiService::class)->create($admin, $request->allInput());
        } catch (ValidationException $e) {
            return $this->page([
                'emoji_errors' => $e->errors,
                'emoji_old' => array_replace($request->allInput(), $e->old),
            ], 422);
        }
        return $this->redirectWithFlash('/admin/custom-emoji', $result['replaced']
            ? 'Custom emoji replaced — :' . $result['shortcode'] . ': already existed.'
            : 'Custom emoji saved.');
    }

    /** @param array<string,string> $params */
    public function enable(Request $request, array $params): Response
    {
        $admin = $this->requireEmojiAdmin();
        $shortcode = (string) ($params['shortcode'] ?? '');
        $this->container->get(CustomEmojiService::class)->setEnabled($admin, $shortcode, true);
        // A catalogue of many rows must not flash a generic outcome; name the row.
        return $this->redirectWithFlash('/admin/custom-emoji', ':' . $shortcode . ': enabled.');
    }

    /** @param array<string,string> $params */
    public function disable(Request $request, array $params): Response
    {
        $admin = $this->requireEmojiAdmin();
        $shortcode = (string) ($params['shortcode'] ?? '');
        $this->container->get(CustomEmojiService::class)->setEnabled($admin, $shortcode, false);
        return $this->redirectWithFlash('/admin/custom-emoji', ':' . $shortcode . ': disabled — it will render as plain text.');
    }

    /** @param array<string,mixed> $overlay */
    private function page(array $overlay = [], int $status = 200): Response
    {
        return $this->view(
            'admin/custom_emoji',
            $this->container->get(CustomEmojiService::class)->pageModel($overlay),
            $status,
        );
    }

    private function requireEmojiAdmin(): \App\Domain\User
    {
        $admin = $this->requireAdmin();
        if (!$this->container->get(FeatureFlags::class)->enabled('custom_emoji')) {
            throw new NotFoundException('Not found.');
        }
        return $admin;
    }
}
