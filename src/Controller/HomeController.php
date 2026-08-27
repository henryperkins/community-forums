<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\Request;
use App\Core\Response;
use App\Repository\BlockRepository;
use App\Repository\FollowRepository;
use App\Repository\NotificationRepository;
use App\Repository\TagRepository;
use App\Service\NavigationService;
use App\Service\PreferenceService;

/**
 * Home: the category/board index (pane 1 + 2 of the three-pane shell). Hidden
 * boards are not listed; private boards appear only for an admin.
 */
final class HomeController extends Controller
{
    private const PANES = ['boards', 'tags', 'notices', 'connections'];
    private const SORTS = ['category', 'active', 'newest', 'unanswered', 'top', 'solved'];
    private const PEEKS = [0, 3, 5];

    /** @param array<string,string> $params */
    public function privacy(Request $request, array $params): Response
    {
        return $this->view('privacy');
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $user = $this->currentUser();
        $flags = $this->container->get(FeatureFlags::class);
        $availablePanes = [
            'boards' => true,
            'tags' => $flags->enabled('tags'),
            'notices' => $flags->enabled('notifications'),
            'connections' => $flags->enabled('community'),
        ];

        $rawPane = $request->query('pane');
        $pane = is_string($rawPane) && in_array($rawPane, self::PANES, true) ? $rawPane : 'boards';
        if (empty($availablePanes[$pane])) {
            $pane = 'boards';
        }

        $preferences = $this->container->get(PreferenceService::class);
        $schemaDefaults = $preferences->memberSurfaceDefaults();
        $remembered = $user !== null
            ? $preferences->memberSurfaces($user->id())
            : $schemaDefaults;

        $rawSort = $request->query('sort');
        $sort = $rawSort === null
            ? (string) $remembered['directory_sort']
            : (is_string($rawSort) && in_array($rawSort, self::SORTS, true)
                ? $rawSort
                : (string) $schemaDefaults['directory_sort']);

        $rawPeek = $request->query('peek');
        if ($rawPeek === null) {
            $peek = (int) $remembered['directory_peek'];
        } else {
            $peekValue = is_scalar($rawPeek) && preg_match('/^(0|3|5)$/', (string) $rawPeek) === 1
                ? (int) $rawPeek
                : (int) $schemaDefaults['directory_peek'];
            $peek = in_array($peekValue, self::PEEKS, true) ? $peekValue : (int) $schemaDefaults['directory_peek'];
        }

        $directoryGroups = [];
        $totals = ['boards' => 0, 'topics' => 0, 'posts' => 0];
        if ($pane === 'boards') {
            $directoryGroups = $this->container->get(NavigationService::class)->directory($user, $sort, $peek);
            $seen = [];
            foreach ($directoryGroups as $group) {
                foreach ($group['boards'] as $board) {
                    $boardId = (int) $board['id'];
                    if (isset($seen[$boardId])) {
                        continue;
                    }
                    $seen[$boardId] = true;
                    $totals['boards']++;
                    $totals['topics'] += (int) ($board['thread_count'] ?? 0);
                    $totals['posts'] += (int) ($board['post_count'] ?? 0);
                }
            }
        }

        $tags = [];
        if ($pane === 'tags') {
            $tags = $this->container->get(TagRepository::class)->catalogForViewer(
                $user?->id() ?? 0,
                $user?->isAdmin() ?? false,
            );
        }

        $notifications = [];
        $notificationUnread = 0;
        if ($pane === 'notices' && $user !== null) {
            $notificationRepo = $this->container->get(NotificationRepository::class);
            $notifications = $notificationRepo->recent($user->id(), 30);
            $notificationUnread = $notificationRepo->unreadCount($user->id());
        }

        $connectionMode = $request->query('connection') === 'following' ? 'following' : 'followers';
        $connections = [];
        $followerCount = 0;
        $followingCount = 0;
        if ($pane === 'connections' && $user !== null) {
            $followRepo = $this->container->get(FollowRepository::class);
            $followerCount = $followRepo->followerCount($user->id());
            $followingCount = $followRepo->followingCount($user->id());
            $connections = $connectionMode === 'following'
                ? $followRepo->listFollowing($user->id(), 100)
                : $followRepo->listFollowers($user->id(), 100);

            $candidateIds = array_values(array_map(static fn (array $person): int => (int) $person['id'], $connections));
            $blocked = $this->container->get(BlockRepository::class)->blockedMap($user->id(), $candidateIds);
            $connections = array_values(array_filter(
                $connections,
                static fn (array $person): bool => !isset($blocked[(int) $person['id']]),
            ));
        }

        return $this->view('home', [
            'pane' => $pane,
            'available_panes' => $availablePanes,
            'directory_sort' => $sort,
            'directory_peek' => $peek,
            'directory_groups' => $directoryGroups,
            'directory_totals' => $totals,
            'tags' => $tags,
            'notifications' => $notifications,
            'notification_unread' => $notificationUnread,
            'connection_mode' => $connectionMode,
            'connections' => $connections,
            'follower_count' => $followerCount,
            'following_count' => $followingCount,
        ]);
    }
}
