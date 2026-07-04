<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\BoardModeratorRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Service\ModerationService;
use App\Service\PostingService;

/**
 * Approval queue for content held by anti-abuse rules or board approval (P3-05).
 * Board-scoped like the reports queue: an admin sees everything, a scoped
 * moderator sees only their boards. Releasing runs the deferred counters/fan-out;
 * rejecting soft-deletes. Every decision is audited with the acting moderator.
 */
final class ApprovalController extends Controller
{
    public function queue(Request $request): Response
    {
        $user = $this->requireUser();
        if (!$user->isModerator()) {
            throw new ForbiddenException('Moderator access required.');
        }
        $boardIds = $user->isAdmin() ? null : $this->container->get(BoardModeratorRepository::class)->boardsFor($user->id());

        return $this->view('mod/approvals', [
            'pending_threads' => $this->container->get(ThreadRepository::class)->listPending($boardIds, 100),
            'pending_posts' => $this->container->get(PostRepository::class)->listPending($boardIds, 100),
        ]);
    }

    /** @param array<string,string> $params */
    public function approveThread(Request $request, array $params): Response
    {
        return $this->actThread($params, approve: true);
    }

    /** @param array<string,string> $params */
    public function rejectThread(Request $request, array $params): Response
    {
        return $this->actThread($params, approve: false);
    }

    /** @param array<string,string> $params */
    public function approvePost(Request $request, array $params): Response
    {
        return $this->actPost($params, approve: true);
    }

    /** @param array<string,string> $params */
    public function rejectPost(Request $request, array $params): Response
    {
        return $this->actPost($params, approve: false);
    }

    /** @param array<string,string> $params */
    private function actThread(array $params, bool $approve): Response
    {
        $mod = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        $thread = $this->container->get(ThreadRepository::class)->find($threadId);
        if ($thread === null) {
            throw new NotFoundException('Thread not found.');
        }
        $this->assertCanModerate($mod, (int) $thread['board_id']);

        $posting = $this->container->get(PostingService::class);
        $ok = $approve ? $posting->approvePendingThread($threadId) : $posting->rejectPendingThread($threadId, $mod->id());
        if ($ok) {
            $this->audit($mod->id(), $approve ? 'approve' : 'reject', 'thread', $threadId);
        }
        $message = $ok
            ? ($approve ? 'Topic approved and published.' : 'Topic rejected.')
            : 'Topic was already handled.';
        return $this->redirectWithFlash('/mod/approvals', $message);
    }

    /** @param array<string,string> $params */
    private function actPost(array $params, bool $approve): Response
    {
        $mod = $this->requireUser();
        $postId = (int) ($params['id'] ?? 0);
        $post = $this->container->get(PostRepository::class)->findWithContext($postId);
        if ($post === null) {
            throw new NotFoundException('Post not found.');
        }
        $this->assertCanModerate($mod, (int) $post['board_id']);

        $posting = $this->container->get(PostingService::class);
        $ok = $approve ? $posting->approvePendingPost($postId) : $posting->rejectPendingPost($postId, $mod->id());
        if ($ok) {
            $this->audit($mod->id(), $approve ? 'approve' : 'reject', 'post', $postId);
        }
        $message = $ok
            ? ($approve ? 'Reply approved and published.' : 'Reply rejected.')
            : 'Reply was already handled.';
        return $this->redirectWithFlash('/mod/approvals', $message);
    }

    private function assertCanModerate(\App\Domain\User $mod, int $boardId): void
    {
        if (!$this->container->get(ModerationService::class)->canModerate($mod, $boardId, 'core.content.approve')) {
            throw new ForbiddenException('You do not moderate that board.');
        }
    }

    private function audit(int $actorId, string $action, string $targetType, int $targetId): void
    {
        $this->container->get(ModerationLogRepository::class)->log([
            'actor_id' => $actorId,
            'action' => $action . '_pending',
            'target_type' => $targetType,
            'target_id' => $targetId,
        ]);
    }
}
