<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Security\ApiScopes;
use App\Service\ApiTokenService;

final class AdminApiTokenController extends Controller
{
    private function gate(): void
    {
        if (!$this->container->get(\App\Core\FeatureFlags::class)->enabled('api_tokens')) {
            throw new NotFoundException();
        }
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();
        return $this->view('admin/api_tokens', [
            'tokens' => $this->container->get(ApiTokenService::class)->list(),
            'scopes_catalogue' => ApiScopes::all(),
            'errors' => [],
            'old' => [],
            'new_token' => null,
        ]);
    }

    /** @param array<string,string> $params */
    public function mint(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $service = $this->container->get(ApiTokenService::class);
        $days = $request->str('expires_in_days');
        try {
            $result = $service->mint(
                $admin,
                (string) $request->post('current_password', ''),
                $request->str('name'),
                (array) $request->post('scopes', []),
                $days === '' ? null : (int) $days,
            );
            // One-time plaintext: render DIRECTLY (not via the cookie-backed Flash, which
            // would leak the token into a Set-Cookie header). A later GET has no new_token,
            // so the secret is shown exactly once. A reload re-POSTs (mints again) — an
            // accepted minor wart; the alternative (cookie flash) leaks the secret.
            return $this->view('admin/api_tokens', [
                'tokens' => $service->list(),
                'scopes_catalogue' => ApiScopes::all(),
                'errors' => [],
                'old' => [],
                'new_token' => $result['token'],
            ]);
        } catch (ValidationException $e) {
            return $this->view('admin/api_tokens', [
                'tokens' => $service->list(),
                'scopes_catalogue' => ApiScopes::all(),
                'errors' => $e->errors,
                'old' => $e->old + ['name' => $request->str('name')],
                'new_token' => null,
            ], 422);
        }
    }

    /** @param array<string,string> $params */
    public function revoke(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $this->container->get(ApiTokenService::class)->revoke($admin, (int) ($params['id'] ?? 0));
        return $this->redirectWithFlash('/admin/api-tokens', 'API token revoked.');
    }
}
