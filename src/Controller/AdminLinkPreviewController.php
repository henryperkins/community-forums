<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Service\LinkPreviewAdminService;

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

    /**
     * Row actions are single button presses with no typed input, so the service
     * answers with the flash to show — including when it refuses. There is no
     * form state to preserve, which is what the 422 re-render contract exists
     * for (the allowlist form above does use it).
     *
     * @param array<string,string> $params
     */
    public function refresh(Request $request, array $params): Response
    {
        $admin = $this->requirePreviewOps();
        $message = $this->admin()->refreshPreview($admin, (int) ($params['id'] ?? 0));

        return $this->redirectWithFlash($this->back($request), $message);
    }

    /** @param array<string,string> $params */
    public function purge(Request $request, array $params): Response
    {
        $admin = $this->requirePreviewOps();
        $message = $this->admin()->purgePreview($admin, (int) ($params['id'] ?? 0));

        return $this->redirectWithFlash($this->back($request), $message);
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
