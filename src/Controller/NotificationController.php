<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Domain\User;
use App\Repository\NotificationRepository;
use App\Repository\ThreadRepository;
use App\Security\BoardPolicy;

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
        $repo = $this->container->get(NotificationRepository::class);
        return $this->view('notifications', [
            'notifications' => $repo->recent($user->id(), 30),
            'unread_count' => $repo->unreadCount($user->id()),
        ]);
    }

    /** Short-poll JSON for the bell (unread count + last few items). */
    public function bell(Request $request): Response
    {
        $user = $this->requireNotifications();
        $repo = $this->container->get(NotificationRepository::class);
        $items = array_map(function (array $n): array {
            return [
                'id' => (int) $n['id'],
                'type' => $n['type'],
                'actor' => $n['actor_display_name'] ?: $n['actor_username'],
                'thread_title' => $n['thread_title'],
                'is_read' => (int) $n['is_read'] === 1,
                'created_at' => $n['created_at'],
            ];
        }, $repo->recent($user->id(), 10));

        return Response::json([
            'unread' => $repo->unreadCount($user->id()),
            'items' => $items,
        ]);
    }

    /** @param array<string,string> $params */
    public function read(Request $request, array $params): Response
    {
        $user = $this->requireNotifications();
        $id = (int) ($params['id'] ?? 0);
        $repo = $this->container->get(NotificationRepository::class);
        $repo->markRead($user->id(), $id);

        // Resolve a safe deep link, re-checking access now (not at creation).
        $target = $this->resolveTarget($id, $user->id());
        if ($target === null) {
            return $this->redirectWithFlash('/notifications', 'That content is no longer available.');
        }
        return $this->redirect($target);
    }

    public function readAll(Request $request): Response
    {
        $user = $this->requireNotifications();
        $this->container->get(NotificationRepository::class)->markAllRead($user->id());
        return $this->redirectWithFlash('/notifications', 'All notifications marked read.');
    }

    public function clear(Request $request): Response
    {
        $user = $this->requireNotifications();
        $this->container->get(NotificationRepository::class)->clear($user->id());
        return $this->redirectWithFlash('/notifications', 'Notifications cleared.');
    }

    private function requireNotifications(): User
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('notifications')) {
            throw new NotFoundException('Not found.');
        }
        return $this->requireUser();
    }

    /** Re-check access and build the deep link for a notification the user owns. */
    private function resolveTarget(int $notificationId, int $userId): ?string
    {
        $row = $this->container->get(NotificationRepository::class)->recent($userId, 100);
        $n = null;
        foreach ($row as $candidate) {
            if ((int) $candidate['id'] === $notificationId) {
                $n = $candidate;
                break;
            }
        }
        if ($n === null) {
            return null;
        }

        // Social notifications link to people, not threads.
        if ($n['type'] === 'follow' && ($n['actor_username'] ?? '') !== '') {
            return '/u/' . (string) $n['actor_username'];
        }
        if ($n['type'] === 'badge') {
            $me = $this->currentUser();
            return $me !== null ? '/u/' . $me->username() : '/notifications';
        }

        if ($n['thread_id'] === null) {
            return null;
        }

        $thread = $this->container->get(ThreadRepository::class)->findWithBoard((int) $n['thread_id']);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            return null;
        }
        $me = $this->currentUser();
        $isMember = $me !== null && $this->container->get(\App\Repository\BoardMemberRepository::class)
            ->isMember((int) $thread['board_id'], $me->id());
        if (!$this->container->get(BoardPolicy::class)->canRead(['visibility' => $thread['board_visibility']], $me, $isMember)) {
            return null;
        }
        $url = '/t/' . (int) $thread['id'] . '-' . $thread['slug'];
        if ($n['post_id'] !== null) {
            $url .= '#p' . (int) $n['post_id'];
        }
        return $url;
    }
}
