<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Domain\User;
use App\Repository\NotificationRepository;
use App\Service\NotificationReadService;

/**
 * The notification bell + list (P2-03). Short-poll JSON endpoint for the unread
 * count/recent items, a no-JS list page, and mark-read/all/clear. Deep links are
 * re-checked against the board read gate at click time so a notification can
 * never become a path into content the recipient has lost access to.
 */
final class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->requireNotifications();
        $query = NotificationReadService::query(['filter' => $request->query('filter'), 'before' => $request->query('before')]);
        $page = $this->container->get(NotificationReadService::class)->page($user, $query['filter'] === 'unread', $query['before']);
        $page['items'] = array_map(static fn (array $row): array => \App\Support\NotificationPresenter::item($row, $user), $page['items']);
        return $this->view('notifications', [
            'notification_page' => $page,
            'notification_return' => NotificationReadService::historyUrl('/notifications', $query),
            'notifications' => $page['items'],
            'unread_count' => $page['unread'],
        ]);
    }

    /** Short-poll JSON for the bell (unread count + last few items). */
    public function bell(Request $request): Response
    {
        $user = $this->requireNotifications();
        $page = $this->container->get(NotificationReadService::class)->page($user, false, null, 10);
        $items = array_map(function (array $n): array {
            return [
                'id' => (int) $n['id'],
                'type' => $n['type'],
                'actor' => $n['actor_display_name'] ?: $n['actor_username'],
                'thread_title' => $n['thread_title'],
                'is_read' => (int) $n['is_read'] === 1,
                'created_at' => $n['created_at'],
            ];
        }, $page['items']);

        return Response::json([
            'unread' => $page['unread'],
            'dm_unread' => $this->container->get(FeatureFlags::class)->enabled('dms')
                ? $this->container->get(\App\Repository\ConversationRepository::class)->unreadConversationCount(
                    $user->id(),
                ) : 0,
            'items' => $items,
        ]);
    }

    /** @param array<string,string> $params */
    public function read(Request $request, array $params): Response
    {
        $user = $this->requireNotifications();
        $id = (int) ($params['id'] ?? 0);
        $result = $this->container->get(NotificationReadService::class)->open($user, $id);
        if ($result['url'] !== null) { return $this->redirect($result['url']); }
        return $this->redirectWithFlash($this->returnTo($request), $result['outcome'] === 'acknowledged'
            ? 'Notification acknowledged.' : 'That content is no longer available.');
    }

    public function readAll(Request $request): Response
    {
        $user = $this->requireNotifications();
        $this->container->get(NotificationRepository::class)->markAllRead($user->id());
        return $this->redirectWithFlash($this->returnTo($request), 'All notifications marked read.');
    }

    public function clear(Request $request): Response
    {
        $user = $this->requireNotifications();
        $this->container->get(NotificationRepository::class)->clear($user->id());
        return $this->redirectWithFlash($this->returnTo($request), 'Notifications cleared.');
    }

    private function returnTo(Request $request): string
    {
        return NotificationReadService::safeReturn($request->post('return'));
    }

    private function requireNotifications(): User
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('notifications')) {
            throw new NotFoundException('Not found.');
        }
        return $this->requireUser();
    }

}
