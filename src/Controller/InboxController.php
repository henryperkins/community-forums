<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\SettingRepository;
use App\Repository\ThreadUserRepository;

/**
 * The personal Community Inbox (P2-01): server-backed Unread / Starred / Mine /
 * Active / Newest / Unanswered filters. Every query is read-gated by board
 * visibility so a filter never surfaces an inaccessible board or thread.
 */
final class InboxController extends Controller
{
    private const FILTERS = ['unread', 'starred', 'mine', 'active', 'newest', 'unanswered'];

    public function index(Request $request): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('engagement')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();
        $filter = (string) $request->query('filter', 'unread');
        if (!in_array($filter, self::FILTERS, true)) {
            $filter = 'unread';
        }

        $repo = $this->container->get(ThreadUserRepository::class);
        $cutover = $this->cutover();
        $isAdmin = $user->isAdmin();

        $perPage = (int) $this->config()->get('pagination.threads_per_page', 20);
        $total = $repo->countInbox($user->id(), $filter, $isAdmin, $cutover);
        $pages = max(1, (int) ceil(max(1, $total) / $perPage));
        $page = min($pages, max(1, $request->int('page', 1)));

        $threads = $repo->inbox($user->id(), $filter, $isAdmin, $cutover, $perPage, ($page - 1) * $perPage);
        $unreadCount = $repo->unreadCount($user->id(), $isAdmin, $cutover);

        return $this->view('inbox', [
            'filter' => $filter,
            'filters' => self::FILTERS,
            'threads' => $threads,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'unread_count' => $unreadCount,
        ]);
    }

    private function cutover(): string
    {
        return $this->container->get(SettingRepository::class)
            ->getString('engagement_cutover_at', ThreadUserRepository::NO_CUTOVER);
    }
}
