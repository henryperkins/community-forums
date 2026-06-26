<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\User;

/**
 * Board read/list/post gates (Phase 1):
 *  - public  → readable + listed by everyone
 *  - hidden  → readable by direct link, never listed
 *  - private → admin-only (read + list)
 *
 * post_min_role enforcement is deferred to Phase 2, so any user who can read a
 * board may post in it (subject to the account-state write gate elsewhere).
 *
 * @phpstan-param array{visibility?:string} $board
 */
final class BoardPolicy
{
    /** @param array<string,mixed> $board */
    public function canRead(array $board, ?User $user): bool
    {
        $visibility = (string) ($board['visibility'] ?? 'public');
        if ($visibility === 'private') {
            return $user?->isAdmin() ?? false;
        }
        return true;
    }

    /** @param array<string,mixed> $board */
    public function isListed(array $board, ?User $user): bool
    {
        $visibility = (string) ($board['visibility'] ?? 'public');
        return match ($visibility) {
            'hidden' => false,
            'private' => $user?->isAdmin() ?? false,
            default => true,
        };
    }

    /** @param array<string,mixed> $board */
    public function canPost(array $board, User $user): bool
    {
        return $this->canRead($board, $user);
    }
}
