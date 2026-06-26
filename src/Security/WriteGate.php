<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\ForbiddenException;
use App\Domain\User;

/**
 * Centralized account-state write authorization. Every write path (create
 * thread, reply, edit/delete own, settings, password, admin/mod actions) runs
 * through assertCanWrite() so suspended and banned accounts are blocked on the
 * server regardless of the UI — including via stale sessions. State beats role:
 * a suspended/banned admin is denied too.
 *
 * Phase 1 only READS users.status / suspended_until (set out-of-band via
 * seed/DB/fixtures); there is no in-app action that sets these.
 */
final class WriteGate
{
    public function assertCanWrite(User $user): void
    {
        if ($user->isBanned()) {
            throw new ForbiddenException('Your account is banned and cannot perform this action.');
        }
        if (!$user->isActive()) {
            throw new ForbiddenException('Your account is suspended and cannot perform this action.');
        }
    }

    public function canWrite(User $user): bool
    {
        return !$user->isBanned() && $user->isActive();
    }
}
