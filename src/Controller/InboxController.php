<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\SettingRepository;
use App\Repository\ThreadUserRepository;
use App\Support\InboxView;

/** The personal Community Inbox, with independent scope and order axes. */
final class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        $flags = $this->container->get(FeatureFlags::class);
        if (!$flags->enabled('engagement')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();
        $workflowEnabled = $flags->enabled('topic_workflow');
        $mentionsEnabled = $flags->enabled('mentions');
        $state = InboxView::resolve($request, $workflowEnabled, $mentionsEnabled);
        if ($state['legacy']) {
            return $this->redirect(InboxView::query($state['scope'], $state['order'], $state['page']));
        }

        $repo = $this->container->get(ThreadUserRepository::class);
        $cutover = $this->cutover();
        $isAdmin = $user->isAdmin();

        $perPage = (int) $this->config()->get('pagination.threads_per_page', 20);
        $total = $repo->countInbox(
            $user->id(),
            $state['scope'],
            $state['order'],
            $isAdmin,
            $cutover,
            $workflowEnabled,
            $mentionsEnabled,
        );
        $pages = max(1, (int) ceil(max(1, $total) / $perPage));
        $page = min($pages, $state['page']);

        $threads = $repo->inbox(
            $user->id(),
            $state['scope'],
            $state['order'],
            $isAdmin,
            $cutover,
            $perPage,
            ($page - 1) * $perPage,
            $workflowEnabled,
            $mentionsEnabled,
        );
        $unreadCount = $repo->unreadCount($user->id(), $isAdmin, $cutover, $workflowEnabled);
        $scopeCounts = $repo->countInboxScopes(
            $user->id(),
            $isAdmin,
            $cutover,
            $workflowEnabled,
            $mentionsEnabled,
        );

        return $this->view('inbox', [
            'scope' => $state['scope'],
            'order' => $state['order'],
            'scopes' => $state['scopes'],
            'scope_counts' => $scopeCounts,
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
