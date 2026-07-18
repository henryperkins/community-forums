<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Domain\User;
use App\Repository\PostRepository;
use App\Service\ReportService;

/**
 * Post reporting + the scoped reports queue (P2-08). Any member can report a
 * post; the queue and triage actions are board-scoped in ReportService (a
 * moderator sees only their boards, an admin sees all).
 */
final class ReportController extends Controller
{
    private const PER_PAGE = 50;

    /** @param array<string,string> $params post id */
    public function report(Request $request, array $params): Response
    {
        $user = $this->requireModeration();
        $postId = (int) ($params['id'] ?? 0);
        $reasonCode = (string) $request->post('reason_code', '');
        $notify = $request->post('notify_reporter') !== null;

        $this->container->get(ReportService::class)
            ->submitPostReport($user, $postId, $reasonCode, (string) $request->str('reason'), $notify);

        $post = $this->container->get(PostRepository::class)->findWithContext($postId);
        $url = $post !== null ? '/t/' . (int) $post['thread_id'] . '-' . $post['thread_slug'] . '#p' . $postId : '/';
        return $this->redirectWithFlash($url, 'Thanks — our moderators will review this.');
    }

    public function queue(Request $request): Response
    {
        $user = $this->requireModeration();
        // Scope resolution, filter allowlisting, rows + real total, and the
        // boards select all live in ReportService::queueModel (spec §4).
        $model = $this->container->get(ReportService::class)->queueModel($user, [
            'status' => $request->str('status'),
            'reason_code' => $request->str('reason_code'),
            'board_id' => $request->int('board_id', 0),
        ], max(0, $request->int('page', 0)), self::PER_PAGE);

        return $this->view('mod/reports', $model);
    }

    /** @param array<string,string> $params report id */
    public function claim(Request $request, array $params): Response
    {
        $user = $this->requireModeration();
        $this->container->get(ReportService::class)->claim($user, (int) ($params['id'] ?? 0));
        return $this->redirectWithFlash('/mod/reports', 'Report claimed.');
    }

    /** @param array<string,string> $params report id */
    public function resolve(Request $request, array $params): Response
    {
        $user = $this->requireModeration();
        $this->container->get(ReportService::class)->resolve($user, (int) ($params['id'] ?? 0));
        return $this->redirectWithFlash('/mod/reports', 'Report resolved.');
    }

    /** @param array<string,string> $params report id */
    public function dismiss(Request $request, array $params): Response
    {
        $user = $this->requireModeration();
        $this->container->get(ReportService::class)->dismiss($user, (int) ($params['id'] ?? 0));
        return $this->redirectWithFlash('/mod/reports', 'Report dismissed.');
    }

    private function requireModeration(): User
    {
        $this->requireModerationQueue();
        return $this->requireUser();
    }
}
