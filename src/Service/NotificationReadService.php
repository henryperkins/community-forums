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
            'unread_only' => $unreadOnly];
    }

    public function unreadCount(User $viewer): int
    {
        return $this->counts[$viewer->id()] ??= $this->notifications->unreadCountForScope($this->visibility->scope($viewer));
    }
}
