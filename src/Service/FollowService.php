<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BlockRepository;
use App\Repository\FollowRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;

/**
 * Follow/unfollow (COMMUNITY §4, P2-09). Following is a one-directional social
 * edge that grants no permissions. It is block-aware (a blocked pair cannot
 * follow either way) and account-state gated; a new follow notifies the target
 * through the normal notification controls.
 */
final class FollowService
{
    public function __construct(
        private FollowRepository $follows,
        private UserRepository $users,
        private BlockRepository $blocks,
        private WriteGate $writeGate,
        private NotificationService $notifications,
    ) {
    }

    /** @return bool the resulting follow state (true = now following) */
    public function follow(User $actor, int $targetId): bool
    {
        $this->writeGate->assertCanWrite($actor);

        if ($targetId === $actor->id()) {
            throw new ValidationException(['follow' => 'You cannot follow yourself.']);
        }
        if ($this->users->find($targetId) === null) {
            throw new NotFoundException('That member could not be found.');
        }
        if ($this->blocks->blockedEitherWay($actor->id(), $targetId)) {
            // Don't reveal which direction the block runs.
            throw new ValidationException(['follow' => 'You cannot follow this member.']);
        }

        if ($this->follows->follow($actor->id(), $targetId)) {
            $this->notifications->notifyFollow($actor->id(), $targetId);
        }
        return true;
    }

    public function unfollow(User $actor, int $targetId): bool
    {
        // Unfollowing is always permitted (it removes contact), even when the
        // account is write-gated — it cannot create or escalate anything.
        $this->follows->unfollow($actor->id(), $targetId);
        return false;
    }

    /** Toggle for the no-JS Follow button. @return bool resulting state */
    public function toggle(User $actor, int $targetId): bool
    {
        if ($this->follows->isFollowing($actor->id(), $targetId)) {
            return $this->unfollow($actor, $targetId);
        }
        return $this->follow($actor, $targetId);
    }
}
