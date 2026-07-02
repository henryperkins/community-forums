<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Service\Registry\RegistryCatalogService;

/**
 * Deploy-dark, read-only staff catalogue browse. Lifecycle actions arrive in
 * a later increment; this controller deliberately registers no POST actions.
 */
final class AdminPackagesController extends Controller
{
    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('package_registry')) {
            throw new NotFoundException();
        }
    }

    private function noindex(Response $response): Response
    {
        return $response->header('X-Robots-Tag', 'noindex');
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        return $this->noindex($this->view('admin/packages', [
            'data' => $this->container->get(RegistryCatalogService::class)->overview(),
        ]));
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        $detail = $this->container->get(RegistryCatalogService::class)->detail((int) ($params['id'] ?? 0));
        if ($detail === null) {
            throw new NotFoundException('Package not found.');
        }

        return $this->noindex($this->view('admin/package_detail', $detail));
    }
}
