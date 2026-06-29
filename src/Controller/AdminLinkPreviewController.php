<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Service\LinkPreviewService;

final class AdminLinkPreviewController extends Controller
{
    /** @param array<string,string> $params */
    public function refresh(Request $request, array $params): Response
    {
        $this->requirePreviewOps();
        $this->container->get(LinkPreviewService::class)->refresh((int) ($params['id'] ?? 0));
        return $this->redirectWithFlash($this->back($request), 'Preview queued for refresh.');
    }

    /** @param array<string,string> $params */
    public function purge(Request $request, array $params): Response
    {
        $this->requirePreviewOps();
        $this->container->get(LinkPreviewService::class)->purge((int) ($params['id'] ?? 0));
        return $this->redirectWithFlash($this->back($request), 'Preview metadata purged.');
    }

    private function requirePreviewOps(): void
    {
        $this->requireAdmin();
        if (!$this->container->get(FeatureFlags::class)->enabled('link_previews')) {
            throw new NotFoundException('Not found.');
        }
    }

    private function back(Request $request): string
    {
        $return = (string) $request->post('return', '/admin');
        return preg_match('#^/(?![/\\\\])#', $return) === 1 ? $return : '/admin';
    }
}
