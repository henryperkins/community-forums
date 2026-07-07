<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Domain\User;
use App\Repository\PostRepository;
use App\Repository\ReportRepository;
use App\Security\Cap;
use App\Service\ModerationService;
use App\Service\ReportService;

/**
 * Post reporting + the scoped reports queue (P2-08). Any member can report a
 * post; the queue and triage actions are board-scoped in ReportService (a
 * moderator sees only their boards, an admin sees all).
 */
final class ReportController extends Controller
{
    private const REASONS = ['spam', 'harassment', 'off_topic', 'nsfw', 'illegal', 'other'];

    /** @param array<string,string> $params post id */
    public function report(Request $request, array $params): Response
    {
        $user = $this->requireModeration();
        $postId = (int) ($params['id'] ?? 0);
        $reasonCode = (string) $request->post('reason_code', '');
        $reasonCode = in_array($reasonCode, self::REASONS, true) ? $reasonCode : null;
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
        // Queue discovery through the gate (core.report.handle — the queue's
        // action key): legacy/shadow reproduce admin-or-assigned exactly;
        // under enforce a custom deputy's grant surfaces their boards.
        $scope = $this->container->get(ModerationService::class)->moderableBoardIds($user, Cap::REPORT_HANDLE);
        if ($scope === []) {
            throw new NotFoundException('Not found.'); // not a handler of anything
        }
        $reports = $this->container->get(ReportRepository::class)->queue($scope === null, $scope ?? [], 100);
        return $this->view('mod/reports', ['reports' => $reports, 'reasons' => self::REASONS]);
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
        if (!$this->container->get(FeatureFlags::class)->enabled('moderation_queue')) {
            throw new NotFoundException('Not found.');
        }
        return $this->requireUser();
    }
}
