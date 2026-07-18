<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
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
        return $this->view('admin/api_tokens', $this->container->get(ApiTokenService::class)->pageModel());
    }

    /** @param array<string,string> $params */
    public function mint(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $outcome = $this->container->get(ApiTokenService::class)->mintPage(
            $admin,
            (string) $request->post('current_password', ''),
            $request->str('name'),
            (array) $request->post('scopes', []),
            $request->str('expires_in_days'),
            $request->str('idempotency_key') ?: null,
        );
        // The plaintext remains in the direct response only; a replay returns
        // the service-owned 409 model with no secret.
        return $this->view('admin/api_tokens', $outcome['model'], $outcome['status']);
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
