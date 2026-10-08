<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ThreadUserRepository;
use App\Security\WriteGate;
use App\Support\InboxSnooze;

/** Transaction-safe owner of the Inbox sweep verbs. */
final class InboxBulkService
{
    public const ACTIONS = ['read', 'unread', 'star', 'snooze', 'hide', 'restore'];

    public function __construct(
        private Database $db,
        private ThreadUserRepository $threadUsers,
        private ThreadReadService $threadRead,
        private WriteGate $writeGate,
    ) {
    }

    /** @param list<int> $threadIds */
    public function apply(User $user, array $threadIds, string $action, bool $workflowEnabled, string $until = ''): int
    {
        $threadIds = array_values(array_unique(array_filter(
            array_map('intval', $threadIds),
            static fn (int $id): bool => $id > 0,
        )));
        if ($threadIds === [] || count($threadIds) > 100) {
            throw new ValidationException(['selection' => 'Choose between one and 100 topics from this Inbox view.']);
        }
        if (!in_array($action, self::ACTIONS, true)) {
            throw new ValidationException(['action' => 'Choose a valid Inbox action.']);
        }
        $changesSnooze = in_array($action, ['snooze', 'hide', 'restore'], true);
        if ($changesSnooze && !$workflowEnabled) {
            throw new ValidationException(['action' => 'Inbox hiding is not available.']);
        }
        if ($action === 'snooze' && $until !== 'tomorrow') {
            throw new ValidationException(['until' => 'Choose an available snooze time.']);
        }
        if ($action === 'star' || $changesSnooze) {
            $this->writeGate->assertCanWrite($user);
        }

        // Authorize and capture every endpoint before the first mutation. This
        // preserves all-or-nothing behavior even inside the test harness's outer
        // transaction, where a nested callback cannot create a savepoint.
        $threads = [];
        foreach ($threadIds as $threadId) {
            $threads[$threadId] = $this->threadRead->loadForUser($user, $threadId);
        }

        $snooze = $changesSnooze ? InboxSnooze::state(match ($action) {
            'snooze' => $until,
            'hide' => 'manual',
            default => '',
        }) : null;
        $this->db->transaction(function () use ($user, $threads, $action, $snooze): void {
            foreach ($threads as $threadId => $thread) {
                if ($action === 'unread') {
                    $this->threadUsers->markUnread($user->id(), $threadId);
                } elseif ($action === 'star') {
                    $this->threadUsers->setStar($user->id(), $threadId, true);
                } elseif ($snooze !== null) {
                    $this->threadUsers->setSnooze($user->id(), $threadId, $snooze['until'], $snooze['indefinite']);
                } else {
                    $this->markRead($user->id(), $threadId, $thread);
                }
            }
        });

        return count($threads);
    }

    /** @param array<string,mixed> $thread */
    private function markRead(int $userId, int $threadId, array $thread): void
    {
        $lastPostId = (int) ($thread['last_post_id'] ?? 0);
        if ($lastPostId > 0) {
            $this->threadUsers->markRead($userId, $threadId, $lastPostId);
        }
    }
}
