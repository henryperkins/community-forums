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
 * The personal Community Inbox. Phase 4 extends the Phase 2 filters with
 * deterministic For You, workflow status, assignment, watching, mentions,
 * replies, and snooze views, all under the same board read gate.
 */
final class InboxController extends Controller
{
    private const FILTERS = [
        'for_you',
        'unread',
        'mentions',
        'replies',
        'watching',
        'needs_answer',
        'assigned',
        'decisions',
        'solved',
        'snoozed',
        'starred',
        'mine',
        'active',
        'newest',
        'unanswered',
    ];

    private const WORKFLOW_FILTERS = [
        'needs_answer',
        'assigned',
        'decisions',
        'solved',
        'snoozed',
    ];

    public function index(Request $request): Response
    {
        $flags = $this->container->get(FeatureFlags::class);
        if (!$flags->enabled('engagement')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();
        $workflowEnabled = $flags->enabled('topic_workflow');
        $mentionsEnabled = $flags->enabled('mentions');
        $filters = self::FILTERS;
        if (!$workflowEnabled) {
            $filters = array_values(array_diff($filters, self::WORKFLOW_FILTERS));
        }
        if (!$mentionsEnabled) {
            $filters = array_values(array_diff($filters, ['mentions']));
        }
        $filter = (string) $request->query('filter', 'for_you');
        if (!in_array($filter, $filters, true)) {
            $filter = 'unread';
        }

        $repo = $this->container->get(ThreadUserRepository::class);
        $cutover = $this->cutover();
        $isAdmin = $user->isAdmin();

        $perPage = (int) $this->config()->get('pagination.threads_per_page', 20);
        $total = $repo->countInbox($user->id(), $filter, $isAdmin, $cutover, $workflowEnabled, $mentionsEnabled);
        $pages = max(1, (int) ceil(max(1, $total) / $perPage));
        $page = min($pages, max(1, $request->int('page', 1)));

        $threads = $repo->inbox(
            $user->id(),
            $filter,
            $isAdmin,
            $cutover,
            $perPage,
            ($page - 1) * $perPage,
            $workflowEnabled,
            $mentionsEnabled,
        );
        $unreadCount = $repo->unreadCount($user->id(), $isAdmin, $cutover, $workflowEnabled);

        return $this->view('inbox', [
            'filter' => $filter,
            'filters' => $filters,
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
