<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Service\ModerationService;

/**
 * Inline content moderation invoked from the thread view: pin/unpin,
 * lock/unlock, move, and post restore. Authorization is board-scoped and
 * enforced in ModerationService (admin anywhere, or assigned board moderator);
 * the controller only requires a logged-in user. Soft-deleting a post lives in
 * PostController::delete.
 */
final class ModerationController extends Controller
{
    /** @param array<string,string> $params */
    public function pin(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $result = $this->container->get(ModerationService::class)->togglePin($user, (int) ($params['id'] ?? 0));
        return $this->redirectWithFlash($this->threadUrl($result['thread']), $result['pinned'] ? 'Thread pinned.' : 'Thread unpinned.');
    }

    /** @param array<string,string> $params */
    public function lock(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $result = $this->container->get(ModerationService::class)->toggleLock($user, (int) ($params['id'] ?? 0));
        return $this->redirectWithFlash($this->threadUrl($result['thread']), $result['locked'] ? 'Thread locked.' : 'Thread unlocked.');
    }

    /** @param array<string,string> $params */
    public function move(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        $destBoardId = (int) $request->int('board_id', 0);

        try {
            $result = $this->container->get(ModerationService::class)->moveThread($user, $threadId, $destBoardId);
        } catch (ValidationException $e) {
            $thread = $this->container->get(\App\Repository\ThreadRepository::class)->find($threadId);
            $url = $thread !== null ? '/t/' . $threadId . '-' . $thread['slug'] : '/';
            return $this->redirectWithFlash($url, $e->first());
        }

        return $this->redirectWithFlash($this->threadUrl($result['thread']), $result['moved'] ? 'Thread moved.' : 'Thread already in that board.');
    }

    /** @param array<string,string> $params post id */
    public function restorePost(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $postId = (int) ($params['id'] ?? 0);
        $post = $this->container->get(ModerationService::class)->restorePost($user, $postId, $request->str('reason'));
        return $this->redirectWithFlash('/t/' . (int) $post['thread_id'] . '-' . $post['thread_slug'] . '#p' . $postId, 'Post restored.');
    }

    /** Reveal the author of an anonymous post (scoped + audited). @param array<string,string> $params post id */
    public function reveal(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $postId = (int) ($params['id'] ?? 0);
        $r = $this->container->get(ModerationService::class)->revealAuthor($user, $postId);
        return $this->redirectWithFlash(
            '/t/' . $r['thread_id'] . '-' . $r['thread_slug'] . '#p' . $r['post_id'],
            'Author of this anonymous post: ' . $r['username'] . ' (this reveal has been logged).',
        );
    }

    /** @param array<string,mixed> $thread */
    private function threadUrl(array $thread): string
    {
        return '/t/' . (int) $thread['id'] . '-' . $thread['slug'];
    }
}
