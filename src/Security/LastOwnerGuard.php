<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\UserRepository;

/**
 * The shared "≥1 active recoverable owner" invariant (decision #27, Foundation
 * F5). Consulted by every owner-loss path: role revoke/demote (Inc 6), passkey
 * removal (Inc 7), sole-provider unlink (Inc 8), invitation (Inc 9), and the
 * account-lifecycle delete/deactivate path (wired now, Task 7).
 *
 * Parity-safe: while `protected_owners` is unseeded (Foundation/dark, or a fresh
 * install pre-setup) it defers to the legacy last-active-admin rule so live
 * behavior is identical to today. Once the owner set is populated it enforces
 * the owner invariant directly.
 *
 * Wiring status (Foundation): the account-lifecycle deactivate/delete-request
 * path consults this guard now (behind `capabilities`). The other four paths
 * call it when their subsystems land — role revoke/demote (Increment 6, the
 * resolver's role_assignments), passkey removal (Increment 7), sole-provider
 * unlink (Increment 8, alongside OAuthService::unlink's existing login-method
 * guard), and invitations (Increment 9). Each is a one-line
 * `$guard->assertNotLastOwner($user, $field)` at its mutation site.
 */
final class LastOwnerGuard
{
    public function __construct(
        private ProtectedOwnerRepository $owners,
        private UserRepository $users,
    ) {
    }

    /**
     * @param string $field the form field to attach the error to (so callers can
     *                       re-render 422 with the anti-draft-loss pattern).
     * @throws ValidationException when the action would remove the last owner.
     */
    public function assertNotLastOwner(User $user, string $field = 'account'): void
    {
        if (!$this->owners->hasAnyActiveOwner()) {
            // Legacy parity: block only when this is the last active admin.
            if ($user->isAdmin() && $this->users->activeAdminCountExcluding($user->id()) === 0) {
                throw new ValidationException([$field => 'Add another active admin before removing the last one.']);
            }
            return;
        }

        if ($this->owners->isActiveOwner($user->id())
            && $this->owners->activeOwnerCountExcluding($user->id()) === 0) {
            throw new ValidationException([$field => 'Designate another site owner before removing the last one.']);
        }
    }

    /**
     * Transactional mutation guard. Callers must already be inside the same
     * transaction that performs the owner-loss mutation.
     */
    public function assertNotLastOwnerForUpdate(User $user, string $field = 'account'): void
    {
        $activeOwnerIds = $this->owners->activeOwnerIdsForUpdate();
        if ($activeOwnerIds === []) {
            // Legacy parity, but with the active-admin rows locked so two admins
            // cannot concurrently remove themselves and strand the install.
            if ($user->isAdmin() && $this->users->activeAdminCountExcludingForUpdate($user->id()) === 0) {
                throw new ValidationException([$field => 'Add another active admin before removing the last one.']);
            }
            return;
        }

        $isOwner = false;
        $otherOwners = 0;
        foreach ($activeOwnerIds as $ownerId) {
            if ($ownerId === $user->id()) {
                $isOwner = true;
            } else {
                $otherOwners++;
            }
        }

        if ($isOwner && $otherOwners === 0) {
            throw new ValidationException([$field => 'Designate another site owner before removing the last one.']);
        }
    }
}
