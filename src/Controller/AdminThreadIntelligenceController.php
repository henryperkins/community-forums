<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Service\ThreadIntelligence\ThreadIntelligenceAdminService;

final class AdminThreadIntelligenceController extends Controller
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        return $this->noindex($this->view('admin/thread_intelligence', [
            'dashboard' => $this->container->get(ThreadIntelligenceAdminService::class)->dashboard(),
        ]));
    }

    /** @param array<string,string> $params */
    public function pauseGeneration(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->container->get(ThreadIntelligenceAdminService::class)->setGenerationPaused($admin, true);
        return $this->redirectWithFlash('/admin/thread-intelligence', 'Automatic generation paused.');
    }

    /** @param array<string,string> $params */
    public function resumeGeneration(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->container->get(ThreadIntelligenceAdminService::class)->setGenerationPaused($admin, false);
        return $this->redirectWithFlash('/admin/thread-intelligence', 'Automatic generation resumed.');
    }

    /** @param array<string,string> $params */
    public function retryProvider(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->container->get(ThreadIntelligenceAdminService::class)->retryProviderConfiguration($admin);
        return $this->redirectWithFlash('/admin/thread-intelligence', 'Provider configuration will be retried.');
    }

    /** @param array<string,string> $params */
    public function retryThread(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $result = $this->container->get(ThreadIntelligenceAdminService::class)
            ->retryThread($admin, (int) ($params['id'] ?? 0));
        return $this->redirectWithFlash('/admin/thread-intelligence', $result->message);
    }

    /** @param array<string,string> $params */
    public function reconcileThread(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $result = $this->container->get(ThreadIntelligenceAdminService::class)
            ->reconcileThread($admin, (int) ($params['id'] ?? 0));
        return $this->redirectWithFlash('/admin/thread-intelligence', $result->message);
    }

    /** @param array<string,string> $params */
    public function pauseThread(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->container->get(ThreadIntelligenceAdminService::class)
            ->setThreadPaused($admin, (int) ($params['id'] ?? 0), true);
        return $this->redirectWithFlash('/admin/thread-intelligence', 'Automatic refresh paused for that thread.');
    }

    /** @param array<string,string> $params */
    public function resumeThread(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->container->get(ThreadIntelligenceAdminService::class)
            ->setThreadPaused($admin, (int) ($params['id'] ?? 0), false);
        return $this->redirectWithFlash('/admin/thread-intelligence', 'Automatic refresh resumed for that thread.');
    }
}
