<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\ThreadRepository;
use App\Repository\ThreadUserRepository;
use App\Security\BoardPolicy;
use App\Security\WriteGate;
use App\Service\ReactionService;
use App\Support\Markdown;

/**
 * A thread (conversation): the paginated post stream plus the reply composer or
 * the guest join-bar. Canonical-URL 301s keep /t/{id} and stale slugs tidy.
 */
final class ThreadController extends Controller
{
    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $thread = $this->loadReadableThread($id);

        // Canonicalise the URL (id+slug) for SEO and consistency.
        $expectedSlug = (string) $thread['slug'];
        $givenSlug = $params['slug'] ?? null;
        if ($givenSlug !== $expectedSlug) {
            $query = $request->query('page') !== null ? '?page=' . (int) $request->int('page', 1) : '';
            return $this->redirect('/t/' . $id . '-' . $expectedSlug . $query, 301);
        }

        return $this->renderThread($request, $thread);
    }

    /**
     * Render a thread page. Reused by PostController when a reply fails
     * validation so the typed text is preserved.
     *
     * @param array<string,mixed> $thread thread joined with board (findWithBoard)
     * @param array<string,mixed> $extra reply_errors / reply_old to repopulate
     */
    public function renderThread(Request $request, array $thread, array $extra = []): Response
    {
        $user = $this->currentUser();
        $perPage = (int) $this->config()->get('pagination.posts_per_page', 20);
        $postRepo = $this->container->get(PostRepository::class);
        $markdown = $this->container->get(Markdown::class);

        $total = $postRepo->countByThread((int) $thread['id']);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, $request->int('page', 1)));

        $posts = $postRepo->listByThread((int) $thread['id'], $perPage, ($page - 1) * $perPage);

        $locked = (int) $thread['is_locked'] === 1;
        $canReply = $user !== null
            && $this->container->get(WriteGate::class)->canWrite($user)
            && $this->container->get(BoardPolicy::class)->canPost(['visibility' => $thread['board_visibility']], $user)
            && !$locked;

        // Engagement: grouped reaction counts for the visible posts, the
        // viewer's own reactions, the star state, and advancing the read
        // position to the newest post shown on this page (P2-01/P2-02).
        $engagement = (bool) ($this->container->get(FeatureFlags::class)->enabled('engagement'));
        $postIds = array_map(static fn (array $p): int => (int) $p['id'], $posts);
        $reactionRepo = $this->container->get(ReactionRepository::class);
        $reactionCounts = $engagement ? $reactionRepo->countsForPosts($postIds) : [];
        $myReactions = [];
        $isStarred = false;

        if ($engagement && $user !== null) {
            $myReactions = $reactionRepo->userReactionsForPosts($user->id(), $postIds);
            $tuRepo = $this->container->get(ThreadUserRepository::class);
            $isStarred = $tuRepo->isStarred($user->id(), (int) $thread['id']);
            if ($postIds !== []) {
                $tuRepo->markRead($user->id(), (int) $thread['id'], max($postIds));
            }
        }

        // Subscription state for the subscribe control (P2-03).
        $notificationsOn = (bool) $this->container->get(FeatureFlags::class)->enabled('notifications');
        $subscription = null;
        if ($notificationsOn && $user !== null) {
            $subscription = $this->container->get(SubscriptionRepository::class)
                ->effectiveForThread($user->id(), (int) $thread['id'], (int) $thread['board_id']);
        }

        return $this->view('thread', array_merge([
            'thread' => $thread,
            'posts' => $posts,
            'markdown' => $markdown,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'per_page' => $perPage,
            'can_reply' => $canReply,
            'locked' => $locked,
            'is_admin' => $user?->isAdmin() ?? false,
            'engagement' => $engagement,
            'reaction_counts' => $reactionCounts,
            'my_reactions' => $myReactions,
            'allowed_emoji' => ReactionService::ALLOWED,
            'is_starred' => $isStarred,
            'notifications_on' => $notificationsOn,
            'subscription' => $subscription,
            'reply_errors' => [],
            'reply_old' => [],
        ], $extra));
    }

    /** @return array<string,mixed> */
    private function loadReadableThread(int $id): array
    {
        $thread = $this->container->get(ThreadRepository::class)->findWithBoard($id);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        $policy = $this->container->get(BoardPolicy::class);
        if (!$policy->canRead(['visibility' => $thread['board_visibility']], $this->currentUser())) {
            throw new NotFoundException('Thread not found.');
        }
        return $thread;
    }
}
