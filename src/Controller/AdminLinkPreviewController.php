<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Service\LinkPreviewAdminService;
use App\Service\LinkPreviewService;

final class AdminLinkPreviewController extends Controller
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requirePreviewOps();
        $status = (string) $request->query('status', '');

        return $this->noindex($this->view('admin/link_previews', [
            'preview' => $this->admin()->dashboard($status === '' ? null : $status),
            'errors' => [],
            'old' => [],
        ]));
    }

    /** @param array<string,string> $params */
    public function saveSettings(Request $request, array $params): Response
    {
        $admin = $this->requirePreviewOps();
        try {
            $this->admin()->saveSettings($admin, $request->allInput());
        } catch (ValidationException $e) {
            // Anti-draft-loss: re-render the console carrying the operator's
            // typed allowlist rather than redirecting and dropping it.
            return $this->view('admin/link_previews', [
                'preview' => $this->admin()->dashboard(null),
                'errors' => $e->errors,
                'old' => $request->allInput(),
            ], 422);
        }

        return $this->redirectWithFlash('/admin/link-previews', 'Link preview settings saved.');
    }

    /** @param array<string,string> $params */
    public function setBoard(Request $request, array $params): Response
    {
        $admin = $this->requirePreviewOps();
        $message = $this->admin()->setBoardOptIn(
            $admin,
            (int) ($params['id'] ?? 0),
            (string) $request->post('enabled', '0') === '1',
        );

        return $this->redirectWithFlash($this->back($request), $message);
    }

    /** @param array<string,string> $params */
    public function refresh(Request $request, array $params): Response
    {
        $admin = $this->requirePreviewOps();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(LinkPreviewService::class)->refresh($id);
        } catch (ValidationException $e) {
            return $this->redirectWithFlash($this->back($request), $e->first());
        }
        $this->admin()->auditPreviewAction($admin, 'link_preview_refresh', $id);

        return $this->redirectWithFlash($this->back($request), 'Preview queued for refresh.');
    }

    /** @param array<string,string> $params */
    public function purge(Request $request, array $params): Response
    {
        $admin = $this->requirePreviewOps();
        $id = (int) ($params['id'] ?? 0);
        // Audited before the wipe so the row still carries the URL being purged.
        $this->admin()->auditPreviewAction($admin, 'link_preview_purge', $id);
        $this->container->get(LinkPreviewService::class)->purge($id);

        return $this->redirectWithFlash($this->back($request), 'Preview metadata purged.');
    }

    private function admin(): LinkPreviewAdminService
    {
        return $this->container->get(LinkPreviewAdminService::class);
    }

    private function requirePreviewOps(): \App\Domain\User
    {
        $admin = $this->requireAdmin();
        if (!$this->container->get(FeatureFlags::class)->enabled('link_previews')) {
            throw new NotFoundException('Not found.');
        }
        return $admin;
    }

    private function back(Request $request): string
    {
        $return = (string) $request->post('return', '/admin/link-previews');
        return preg_match('#^/(?![/\\\\])#', $return) === 1 ? $return : '/admin/link-previews';
    }
}
