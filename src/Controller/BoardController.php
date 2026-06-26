<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\SettingRepository;
use App\Repository\ThreadRepository;
use App\Repository\ThreadUserRepository;
use App\Security\BoardPolicy;
use App\Security\WriteGate;

/**
 * A board's paginated thread inbox. Resolves renamed slugs via
 * board_slug_history (301), enforcing the read gate before redirecting so a
 * private board's existence is never revealed.
 */
final class BoardController extends Controller
{
    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $slug = $params['slug'] ?? '';
        $boards = $this->container->get(BoardRepository::class);
        $policy = $this->container->get(BoardPolicy::class);
        $user = $this->currentUser();

        $members = $this->container->get(BoardMemberRepository::class);
        $isMemberOf = fn (array $b): bool => $user !== null && $members->isMember((int) $b['id'], $user->id());

        $board = $boards->findBySlug($slug);
        if ($board === null) {
            // Maybe a renamed slug → 301 to current (only if readable).
            $current = $boards->currentSlugForOld($slug);
            if ($current !== null && $policy->canRead($current, $user, $isMemberOf($current))) {
                return $this->redirect('/c/' . $current['slug'], 301);
            }
            throw new NotFoundException('Board not found.');
        }

        $isMember = $isMemberOf($board);
        if (!$policy->canRead($board, $user, $isMember)) {
            throw new NotFoundException('Board not found.');
        }

        $perPage = (int) $this->config()->get('pagination.threads_per_page', 20);
        $threadRepo = $this->container->get(ThreadRepository::class);
        $total = $threadRepo->countByBoard((int) $board['id']);
        $page = $this->pageNumber($request, $total, $perPage);
        $threads = $threadRepo->listByBoard((int) $board['id'], $perPage, ($page - 1) * $perPage);

        // Annotate unread state for the signed-in reader (P2-01).
        if ($user !== null && $this->container->get(FeatureFlags::class)->enabled('engagement') && $threads !== []) {
            $cutover = $this->container->get(SettingRepository::class)
                ->getString('engagement_cutover_at', ThreadUserRepository::NO_CUTOVER);
            $ids = array_map(static fn (array $t): int => (int) $t['id'], $threads);
            $unread = $this->container->get(ThreadUserRepository::class)->unreadFlags($user->id(), $ids, $cutover);
            foreach ($threads as &$t) {
                $t['is_unread'] = $unread[(int) $t['id']] ?? false;
            }
            unset($t);
        }

        $canPost = $user !== null
            && $this->container->get(WriteGate::class)->canWrite($user)
            && $policy->canPost($board, $user, $isMember);

        return $this->view('board', [
            'board' => $board,
            'threads' => $threads,
            'page' => $page,
            'total' => $total,
            'per_page' => $perPage,
            'can_post' => $canPost,
        ]);
    }

    private function pageNumber(Request $request, int $total, int $perPage): int
    {
        $pages = max(1, (int) ceil($total / $perPage));
        return min($pages, max(1, $request->int('page', 1)));
    }
}
