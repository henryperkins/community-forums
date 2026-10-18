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
        return $this->noindex($this->view('admin/link_previews', [
            'preview' => $this->admin()->dashboard(
                $this->status($request->query('status')),
                $this->page($request->query('page')),
            ),
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
            $state = $this->returnState($request);
            return $this->view('admin/link_previews', [
                'preview' => $this->admin()->dashboard($state['status'], $state['page']),
                'errors' => $e->errors,
                'old' => $request->allInput(),
            ], 422);
        }

        return $this->redirectWithFlash($this->back($request), 'Link preview settings saved.');
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
        $state = $this->returnState($request);
        $query = [];
        if ($state['status'] !== null) {
            $query['status'] = $state['status'];
        }
        if ($state['page'] > 1) {
            $query['page'] = $state['page'];
        }

        return '/admin/link-previews' . ($query === [] ? '' : '?' . http_build_query($query));
    }

    /** @return array{status:?string,page:int} */
    private function returnState(Request $request): array
    {
        // Accept only this console's known query fields, never an arbitrary
        // posted URL. Its GET clamps a page that became empty after a row action.
        $return = parse_url($this->localReturn($request, '/admin/link-previews'));
        $query = [];
        if (is_array($return) && ($return['path'] ?? '') === '/admin/link-previews') {
            parse_str((string) ($return['query'] ?? ''), $query);
        }

        return [
            'status' => $this->status($query['status'] ?? null),
            'page' => $this->page($query['page'] ?? null),
        ];
    }

    private function status(mixed $value): ?string
    {
        return is_string($value) && in_array($value, LinkPreviewAdminService::STATUSES, true)
            ? $value
            : null;
    }

    private function page(mixed $value): int
    {
        // Overflowing positive strings saturate at PHP_INT_MAX; dashboard()
        // clamps that value to the last real page before calculating OFFSET.
        return is_string($value) && ctype_digit($value) ? max(1, (int) $value) : 1;
    }
}
