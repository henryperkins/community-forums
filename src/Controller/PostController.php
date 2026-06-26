<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\BoardRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Security\BoardPolicy;
use App\Service\ModerationService;
use App\Service\PostingService;

/**
 * Thread/post writes: create thread, reply, edit own, soft-delete (owner self
 * or admin-any). All go through PostingService/ModerationService which enforce
 * the write gate, sanitisation, and transactional counters. PRG on success.
 */
final class PostController extends Controller
{
    /** @param array<string,string> $params */
    public function createThread(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $posting = $this->container->get(PostingService::class);

        try {
            $result = $posting->createThread($user, $request->allInput() + ['ip' => $request->ip()]);
        } catch (ValidationException $e) {
            return $this->view('compose', [
                'errors' => $e->errors,
                'old' => $e->old,
                'boards' => $this->postableBoards(),
                'selected_board' => (int) $request->int('board_id', 0),
            ], 422);
        }

        return $this->redirect('/t/' . $result['thread_id'] . '-' . $result['slug']);
    }

    /** @param array<string,string> $params */
    public function reply(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        $posting = $this->container->get(PostingService::class);

        try {
            $postId = $posting->reply($user, $threadId, $request->allInput() + ['ip' => $request->ip()]);
        } catch (ValidationException $e) {
            $thread = $this->container->get(ThreadRepository::class)->findWithBoard($threadId);
            if ($thread === null) {
                throw new NotFoundException('Thread not found.');
            }
            return (new ThreadController($this->container))->renderThread($request, $thread, [
                'reply_errors' => $e->errors,
                'reply_old' => $e->old,
            ])->withStatus(422);
        }

        $thread = $this->container->get(ThreadRepository::class)->find($threadId);
        $slug = $thread !== null ? $thread['slug'] : '';
        return $this->redirect('/t/' . $threadId . '-' . $slug . '#p' . $postId);
    }

    /** @param array<string,string> $params */
    public function edit(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $postId = (int) ($params['id'] ?? 0);
        $posting = $this->container->get(PostingService::class);

        try {
            $post = $posting->editOwnPost($user, $postId, $request->allInput());
        } catch (ValidationException $e) {
            $post = $this->container->get(PostRepository::class)->findWithContext($postId);
            if ($post === null) {
                throw new NotFoundException('Post not found.');
            }
            return $this->redirectWithFlash($this->threadUrl($post) . '#p' . $postId, $e->first());
        }

        return $this->redirect($this->threadUrl($post) . '#p' . $postId);
    }

    /** @param array<string,string> $params */
    public function delete(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $postId = (int) ($params['id'] ?? 0);

        $post = $this->container->get(PostRepository::class)->findWithContext($postId);
        if ($post === null || (int) $post['is_deleted'] === 1) {
            throw new NotFoundException('Post not found.');
        }
        $threadUrl = $this->threadUrl($post);

        if ($user->owns((int) $post['user_id'])) {
            $this->container->get(PostingService::class)->deleteOwnPost($user, $postId);
            return $this->redirectWithFlash($threadUrl, 'Your post was deleted.');
        }

        $moderation = $this->container->get(ModerationService::class);
        if ($moderation->canModerate($user, (int) $post['board_id'])) {
            try {
                $moderation->deletePost($user, $postId, $request->str('reason'));
            } catch (ValidationException $e) {
                return $this->redirectWithFlash($threadUrl . '#p' . $postId, $e->first());
            }
            return $this->redirectWithFlash($threadUrl, 'Post removed.');
        }

        throw new ForbiddenException('You can only delete your own posts.');
    }

    /** @param array<string,mixed> $post post row from findWithContext */
    private function threadUrl(array $post): string
    {
        return '/t/' . (int) $post['thread_id'] . '-' . $post['thread_slug'];
    }

    /** @return array<int,array<string,mixed>> boards the current user may post in */
    private function postableBoards(): array
    {
        $user = $this->currentUser();
        $policy = $this->container->get(BoardPolicy::class);
        $boards = $this->container->get(BoardRepository::class)->allOrdered();
        return array_values(array_filter(
            $boards,
            fn (array $b): bool => $user !== null && $policy->canPost($b, $user) && $policy->isListed($b, $user),
        ));
    }
}
