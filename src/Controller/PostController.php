<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\BoardRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Security\AuthorityGate;
use App\Security\BoardPolicy;
use App\Security\Cap;
use App\Service\BadgeService;
use App\Service\ModerationService;
use App\Service\PostingService;
use App\Service\PreferenceService;

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
        $this->container->get(\App\Service\RateLimitService::class)->enforce('post', $request, $user);
        $posting = $this->container->get(PostingService::class);

        try {
            $result = $posting->createThread($user, $request->allInput() + ['ip' => $request->ip()]);
        } catch (ValidationException $e) {
            return $this->view('compose', [
                'errors' => $e->errors,
                'old' => $e->old,
                'boards' => $this->postableBoards(),
                'selected_board' => (int) $request->int('board_id', 0),
                'show_avatars' => (bool) $this->container->get(PreferenceService::class)
                    ->reading($user->id())['show_avatars'],
            ], 422);
        }

        $this->discardServerDraftFor($user, $request->path());
        $this->awardBadges($user->id());

        // A held thread is not yet visible; send the author back to the board with
        // a clear status rather than to an empty (pending) thread page (P3-05).
        if (!empty($result['pending'])) {
            $board = $this->container->get(BoardRepository::class)->find((int) $request->int('board_id', 0));
            $dest = $board !== null ? '/c/' . $board['slug'] : '/';
            return $this->redirectWithFlash($dest, 'Your topic was submitted and is awaiting moderator approval.');
        }
        return $this->redirect('/t/' . $result['thread_id'] . '-' . $result['slug']);
    }

    /** @param array<string,string> $params */
    public function reply(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        $this->container->get(\App\Service\RateLimitService::class)->enforce('post', $request, $user);
        $posting = $this->container->get(PostingService::class);

        $input = $request->allInput() + ['ip' => $request->ip()];
        try {
            $postId = $posting->reply($user, $threadId, $input);
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

        $this->discardServerDraftFor($user, $request->path());
        $this->awardBadges($user->id());

        $thread = $this->container->get(ThreadRepository::class)->find($threadId);
        $slug = $thread !== null ? $thread['slug'] : '';
        // A held reply isn't shown yet; tell the author it awaits approval (P3-05).
        $post = $this->container->get(PostRepository::class)->find($postId);
        if ($post !== null && (int) $post['is_pending'] === 1) {
            return $this->redirectWithFlash('/t/' . $threadId . '-' . $slug, 'Your reply was submitted and is awaiting moderator approval.');
        }
        return $this->redirect($this->postLocation($threadId, $slug, $postId));
    }

    /** Evaluate auto badges for a user after they post (community flag aware). */
    private function awardBadges(int $userId): void
    {
        if ($this->container->get(FeatureFlags::class)->enabled('community')) {
            $this->container->get(BadgeService::class)->evaluateForUser($userId);
        }
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
            $thread = $this->container->get(ThreadRepository::class)->findWithBoard((int) $post['thread_id']);
            if ($thread === null) {
                throw new NotFoundException('Thread not found.');
            }
            // Re-render the thread (on the page containing the post) with this
            // post's edit form re-opened and the rejected text + error preserved,
            // instead of redirecting to the thread and dropping the typed edit —
            // symmetric with the reply re-render (renderThread + reply_old).
            return (new ThreadController($this->container))->renderThread($request, $thread, [
                'edit_post_id' => $postId,
                'edit_old' => (string) $request->post('body', ''),
                'edit_error' => $e->first(),
            ])->withStatus(422);
        }

        return $this->redirect($this->postLocation((int) $post['thread_id'], (string) $post['thread_slug'], $postId));
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
            try {
                $result = $this->container->get(PostingService::class)->deleteOwnPost($user, $postId);
            } catch (ValidationException $e) {
                // Refusing to delete the opening post of a topic others have joined.
                return $this->redirectWithFlash($threadUrl . '#p' . $postId, $e->first());
            }
            // Deleting the opening post retracts the whole topic — send the author
            // back to the board rather than to a now-removed thread.
            if (!empty($result['topic_retracted'])) {
                return $this->redirectWithFlash('/c/' . $post['board_slug'], 'Your topic was deleted.');
            }
            return $this->redirectWithFlash($threadUrl, 'Your post was deleted.');
        }

        $moderation = $this->container->get(ModerationService::class);
        if ($moderation->canModerate($user, (int) $post['board_id'])) {
            try {
                $moderation->deletePost($user, $postId, $request->str('reason'));
            } catch (ValidationException $e) {
                return $this->redirectWithFlash($threadUrl . '#p' . $postId, $e->first());
            }
            // Removing the opening post removes the whole topic — the thread is
            // gone, so send the moderator back to the board, not to a dead URL.
            if ((int) $post['is_op'] === 1) {
                return $this->redirectWithFlash('/c/' . $post['board_slug'], 'Topic removed.');
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
        $gate = $this->container->get(AuthorityGate::class);
        $boards = $this->container->get(BoardRepository::class)->allOrdered();
        $memberBoardIds = $user !== null
            ? array_flip($this->container->get(\App\Repository\BoardMemberRepository::class)->boardIdsFor($user->id()))
            : [];
        if ($user !== null && $gate->mode() !== AuthorityGate::MODE_LEGACY) {
            // Everything the per-board decisions need is already in hand —
            // prime the resolver memos so shadow/enforce does not re-fetch
            // each board row and membership per board (the 422 re-render N+1).
            $resolver = $this->container->get(\App\Security\CapabilityResolver::class);
            $resolver->primeBoards($boards);
            $resolver->primeMembership(
                $user->id(),
                array_keys($memberBoardIds),
                array_map(static fn (array $b): int => (int) $b['id'], $boards),
            );
        }
        return array_values(array_filter(
            $boards,
            fn (array $b): bool => $user !== null
                && $gate->allows(
                    fn (): bool => $policy->canPost($b, $user, isset($memberBoardIds[(int) $b['id']])),
                    $user,
                    Cap::THREAD_CREATE,
                    ['board_id' => (int) $b['id']],
                    'PostController::postableBoards',
                )
                && $policy->isListed($b, $user, isset($memberBoardIds[(int) $b['id']])),
        ));
    }
}
