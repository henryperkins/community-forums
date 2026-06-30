<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\ServerExtensionRepository;
use App\Service\Extension\ExtensionSandbox;

final class AdminExtensionController extends Controller
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        if (!$this->container->get(FeatureFlags::class)->enabled('server_extensions')) {
            throw new NotFoundException('Not found.');
        }
        $repo = $this->container->get(ServerExtensionRepository::class);
        return $this->view('admin/extensions', [
            'probe' => $this->container->get(ExtensionSandbox::class)->probe(),
            'handlers' => $repo->handlers(),
            'runs' => $repo->recentRuns(25),
        ]);
    }
}
