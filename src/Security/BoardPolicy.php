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
 * $isMember, so this stays a pure policy. Posting additionally enforces the
 * board's `post_min_role` floor (P2-08): its role vocabulary is shared with
 * `users.role` (DECISIONS §4), so an "admin-only announcements" board sets
 * post_min_role='admin'. Account state (suspended/banned) is enforced by the
 * write gate elsewhere.
 *
 * @phpstan-param array{visibility?:string,post_min_role?:string} $board
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

    /**
     * Whether $user may create a thread or reply in $board: they must be able
     * to read it AND meet the board's minimum posting role. Roles are
     * cumulative (admin ⊇ moderator ⊇ user), matching User::isModerator/isAdmin.
     *
     * @param array<string,mixed> $board
     */
    public function canPost(array $board, User $user, bool $isMember): bool
    {
        if (!$this->canRead($board, $user, $isMember)) {
            return false;
        }
        return match ((string) ($board['post_min_role'] ?? 'user')) {
            'admin' => $user->isAdmin(),
            'moderator' => $user->isModerator(),
            default => true, // 'user' — any reader may post (write gate applies elsewhere)
        };
    }
}
