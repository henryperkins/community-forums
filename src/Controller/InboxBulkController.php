<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\SettingRepository;
use App\Repository\ThreadUserRepository;
use App\Service\InboxBulkService;
use App\Support\InboxView;

final class InboxBulkController extends Controller
{
    public function apply(Request $request): Response
    {
        $flags = $this->container->get(FeatureFlags::class);
        if (!$flags->enabled('engagement')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();
        $workflowEnabled = $flags->enabled('topic_workflow');
        $mentionsEnabled = $flags->enabled('mentions');
        $state = InboxView::resolve($request, $workflowEnabled, $mentionsEnabled);
        $return = InboxView::query($state['scope'], $state['order'], $state['page']);

        $repo = $this->container->get(ThreadUserRepository::class);
        $cutover = $this->container->get(SettingRepository::class)
            ->getString('engagement_cutover_at', ThreadUserRepository::NO_CUTOVER);
        $perPage = (int) $this->config()->get('pagination.threads_per_page', 20);
        $total = $repo->countInbox(
            $user->id(),
            $state['scope'],
            $state['order'],
            $user->isAdmin(),
            $cutover,
            $workflowEnabled,
            $mentionsEnabled,
        );
        $pages = max(1, (int) ceil(max(1, $total) / $perPage));
        $page = min($pages, $state['page']);
        $return = InboxView::query($state['scope'], $state['order'], $page);
        $currentRows = $repo->inbox(
            $user->id(),
            $state['scope'],
            $state['order'],
            $user->isAdmin(),
            $cutover,
            $perPage,
            ($page - 1) * $perPage,
            $workflowEnabled,
            $mentionsEnabled,
        );
        $available = array_fill_keys(array_map(static fn (array $row): int => (int) $row['id'], $currentRows), true);
        $submitted = $request->post('thread_ids', []);
        $ids = [];
        if (is_array($submitted)) {
            foreach ($submitted as $value) {
                if (is_int($value) || (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1)) {
                    $ids[] = (int) $value;
                } else {
                    $ids[] = 0;
                }
            }
            $ids = array_values(array_unique($ids));
        }

        try {
            if ($ids === [] || array_filter($ids, static fn (int $id): bool => $id <= 0 || !isset($available[$id])) !== []) {
                throw new ValidationException(['selection' => 'That selection is not available in this Inbox view.']);
            }
            $count = $this->container->get(InboxBulkService::class)->apply(
                $user,
                $ids,
                (string) $request->post('action', ''),
                $workflowEnabled,
                (string) $request->post('until', ''),
            );
        } catch (ValidationException $e) {
            return $this->redirectWithFlash($return, $e->first());
        }

        return $this->redirectWithFlash($return, $count . ($count === 1 ? ' topic updated.' : ' topics updated.'));
    }
}
