<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\User;

/**
 * Board read/list/post gates:
 *  - public  → readable + listed by everyone
 *  - hidden  → readable by direct link, never listed
 *  - private → admin OR an explicit board member (P2-08 `board_members`)
 *
 * Membership is resolved by the caller (board_members lookup) and passed as
 * $isMember, so this stays a pure policy. post_min_role enforcement is a later
 * refinement; any user who can read a board may post in it (subject to the
 * account-state write gate elsewhere).
 *
 * @phpstan-param array{visibility?:string} $board
 */
final class BoardPolicy
{
    /** @param array<string,mixed> $board */
    public function canRead(array $board, ?User $user, bool $isMember): bool
    {
        $visibility = (string) ($board['visibility'] ?? 'public');
        if ($visibility === 'private') {
            return ($user?->isAdmin() ?? false) || $isMember;
        }
        return true;
    }

    /** @param array<string,mixed> $board */
    public function isListed(array $board, ?User $user, bool $isMember): bool
    {
        $visibility = (string) ($board['visibility'] ?? 'public');
        return match ($visibility) {
            'hidden' => false,
            'private' => ($user?->isAdmin() ?? false) || $isMember,
            default => true,
        };
    }

    /** @param array<string,mixed> $board */
    public function canPost(array $board, User $user, bool $isMember): bool
    {
        return $this->canRead($board, $user, $isMember);
    }
}
