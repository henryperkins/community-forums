<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\User;
use App\Repository\NotificationRepository;

/** One page/count model, shared by JSON, HTML and the lazy shell badge. */
final class NotificationReadService
{
    private array $counts = [];

    public function __construct(
        private NotificationRepository $notifications,
        private NotificationVisibilityService $visibility,
        private ?ThreadReadService $threads = null,
    ) {
    }

    public function page(User $viewer, bool $unreadOnly = false, ?int $beforeId = null, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $rows = $this->notifications->pageForScope($this->visibility->scope($viewer), $unreadOnly, $beforeId, $limit + 1);
        $more = count($rows) > $limit;
        if ($more) {
            array_pop($rows);
        }
        return ['items' => $rows, 'unread' => $this->unreadCount($viewer),
            'next_before' => $more ? (int) $rows[array_key_last($rows)]['id'] : null,
            'unread_only' => $unreadOnly, 'before' => $beforeId];
    }

    public function unreadCount(User $viewer): int
    {
        return $this->counts[$viewer->id()] ??= $this->notifications->unreadCountForScope($this->visibility->scope($viewer));
    }

    public function open(User $viewer, int $notificationId): array
    {
        if ($this->notifications->findOwned($viewer->id(), $notificationId) === null) {
            throw new \App\Core\NotFoundException('Notification not found.');
        }
        $scope = $this->visibility->scope($viewer, true);
        $n = $this->notifications->findForScope($scope, $notificationId);
        $url = null;
        $acknowledge = false;
        if ($n !== null) {
            if ($n['thread_id'] !== null && in_array($n['type'], ['reply', 'new_thread', 'new_post', 'mention', 'reaction', 'solved', 'mod'], true)) {
                try {
                    $thread = $this->threads?->loadForUser($viewer, (int) $n['thread_id']);
                    if ($thread !== null) {
                        $url = '/t/' . (int) $thread['id'] . '-' . $thread['slug'];
                        if ($n['post_id'] !== null) { $url .= '#p' . (int) $n['post_id']; }
                    }
                } catch (\App\Core\NotFoundException) { /* stale target */ }
            } elseif ($n['type'] === 'dm' && $n['conversation_id'] !== null) {
                // The shared predicate enforces historical membership and its bounds.
                $url = '/messages/' . (int) $n['conversation_id'];
            } elseif ($n['type'] === 'mod' && $n['conversation_id'] !== null) {
                $url = '/mod/reports'; // scoped REPORT_HANDLE authority already checked
            } elseif ($n['type'] === 'mod' && $n['post_id'] === null) {
                if (!empty($scope['features']['appeals'])) { $url = '/appeals'; }
                else { $acknowledge = true; }
            } elseif ($n['type'] === 'follow' && !empty($n['actor_username'])) {
                $url = '/u/' . rawurlencode($n['actor_username']);
            } elseif ($n['type'] === 'badge') {
                $url = '/u/' . rawurlencode($viewer->username());
            } elseif ($n['type'] === 'announcement') { $url = '/'; }
        }
        if ($url === null && !$acknowledge) { return ['outcome' => 'unavailable', 'url' => null]; }
        $this->notifications->markRead($viewer->id(), $notificationId);
        unset($this->counts[$viewer->id()]);
        return ['outcome' => $acknowledge ? 'acknowledged' : 'opened', 'url' => $url];
    }

    /** Shared strict query contract for both history entry points. */
    public static function query(array $query): array
    {
        $filter = ($query['filter'] ?? null) === 'unread' ? 'unread' : 'all';
        $raw = $query['before'] ?? null;
        $before = is_scalar($raw) ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        return ['filter' => $filter, 'before' => $before === false ? null : $before];
    }

    public static function historyUrl(string $path, array $query): string
    {
        $parsed = self::query($query);
        $params = $path === '/' ? ['pane' => 'notices'] : [];
        $params['filter'] = $parsed['filter'];
        if ($parsed['before'] !== null) { $params['before'] = $parsed['before']; }
        return $path . '?' . http_build_query($params);
    }

    public static function safeReturn(mixed $value): string
    {
        if (!is_string($value) || preg_match('/[\\\\\x00-\x20]/', $value)) { return '/notifications'; }
        $parts = parse_url($value);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['fragment'])) { return '/notifications'; }
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);
        if (!in_array($path, ['/', '/notifications'], true)
            || array_diff(array_keys($query), $path === '/' ? ['pane', 'filter', 'before'] : ['filter', 'before']) !== []
            || ($path === '/' && ($query['pane'] ?? null) !== 'notices')
            || (isset($query['filter']) && !in_array($query['filter'], ['all', 'unread'], true))
            || (isset($query['before']) && self::query($query)['before'] === null)) { return '/notifications'; }
        if ($query === []) { return '/notifications'; }
        $params = $path === '/' ? ['pane' => 'notices'] : [];
        if (isset($query['filter'])) { $params['filter'] = $query['filter']; }
        if (isset($query['before'])) { $params['before'] = (int) $query['before']; }
        return $path . '?' . http_build_query($params);
    }
}
