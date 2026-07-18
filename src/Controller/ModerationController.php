<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Repository\PostRepository;
use App\Service\ModerationService;
use App\Service\PreferenceService;
use App\Service\ThreadReadService;
use App\Service\ThreadSplitMergeService;

/**
 * Inline content moderation invoked from the thread view: pin/unpin,
 * lock/unlock, move, split/merge, and post restore. Authorization is
 * board-scoped and enforced in ModerationService (admin anywhere, or assigned
 * board moderator); the controller only requires a logged-in user. A failed
 * move/split/merge re-renders the thread view at 422 with the typed input
 * preserved (anti-draft-loss — same renderThread mechanism the reply and edit
 * forms use) instead of a flash redirect that drops it. Soft-deleting a post
 * lives in PostController::delete.
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
            return $this->rerenderThread($request, $threadId, [
                'move_error' => $e->first(),
                'move_selected' => $destBoardId,
            ]);
        }

        return $this->redirectWithFlash($this->threadUrl($result['thread']), $result['moved'] ? 'Thread moved.' : 'Thread already in that board.');
    }

    /** @param array<string,string> $params */
    public function split(Request $request, array $params): Response
    {
        $this->requireSplitMerge();
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        $postIds = $this->postIds($request->post('post_ids', ''));
        try {
            $thread = $this->container->get(ThreadSplitMergeService::class)->split(
                $user,
                $threadId,
                $postIds,
                $request->str('title'),
            );
        } catch (ValidationException $e) {
            return $this->rerenderThread($request, $threadId, [
                'restructure_error' => $e->first(),
                'restructure_context' => 'split',
                'restructure_old' => [
                    'title' => $request->str('title'),
                    'post_ids' => $postIds,
                ],
            ]);
        }

        return $this->redirectWithFlash($this->threadUrl($thread), 'Thread split.');
    }

    /** @param array<string,string> $params */
    public function merge(Request $request, array $params): Response
    {
        $this->requireSplitMerge();
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        try {
            $thread = $this->container->get(ThreadSplitMergeService::class)->merge(
                $user,
                $threadId,
                (int) $request->int('target_thread_id', 0),
            );
        } catch (ValidationException $e) {
            return $this->rerenderThread($request, $threadId, [
                'restructure_error' => $e->first(),
                'restructure_context' => 'merge',
                'restructure_old' => [
                    'target_thread_id' => $request->str('target_thread_id'),
                ],
            ]);
        }

        return $this->redirectWithFlash($this->threadUrl($thread), 'Thread merged.');
    }

    /** @param array<string,string> $params post id */
    public function restorePost(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $postId = (int) ($params['id'] ?? 0);
        $post = $this->container->get(ModerationService::class)->restorePost($user, $postId, $request->str('reason'));
        $page = $this->container->get(PostRepository::class)->pageOfPost(
            (int) $post['thread_id'],
            $postId,
            $this->container->get(PreferenceService::class)->postsPerPage($user->id()),
            includeDeleted: true,
        );
        $location = '/t/' . (int) $post['thread_id'] . '-' . $post['thread_slug']
            . ($page > 1 ? '?page=' . $page : '') . '#p' . $postId;

        return $this->redirectWithFlash($location, 'Post restored.');
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

    /**
     * Re-render the thread view at 422 with moderation-form context preserved
     * (same mechanism as PostController's reply/edit failure re-renders).
     * Loads through the shared read gate: the 422 must never show an actor a
     * thread the equivalent GET would 404 (spec §1).
     *
     * @param array<string,mixed> $extra
     */
    private function rerenderThread(Request $request, int $threadId, array $extra): Response
    {
        $thread = $this->container->get(ThreadReadService::class)->loadForUser($this->currentUser(), $threadId);
        return (new ThreadController($this->container))
            ->renderThread($request, $thread, $extra)
            ->withStatus(422);
    }

    /** @param array<string,mixed> $thread */
    private function threadUrl(array $thread): string
    {
        return '/t/' . (int) $thread['id'] . '-' . $thread['slug'];
    }

    private function requireSplitMerge(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('split_merge')) {
            throw new NotFoundException('Not found.');
        }
    }

    /** @return list<int> */
    private function postIds(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_map('intval', $raw));
        }
        return array_values(array_map('intval', preg_split('/[,\s]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) ?: []));
    }
}
