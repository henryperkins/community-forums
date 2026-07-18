<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\NotFoundException;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\ThreadRepository;
use App\Security\BoardAuthority;
use App\Security\BoardPolicy;

/**
 * The one thread read gate (PR #44 remediation, spec §1). Every thread render
 * — the public GET, the moderation-failure re-renders, and the reply/edit
 * failure re-renders — loads through here, so no path can echo a title or
 * body the actor could not read, and "exists but unreadable" is
 * indistinguishable from "does not exist" (uniform 404).
 */
final class ThreadReadService
{
    public function __construct(
        private ThreadRepository $threads,
        private BoardPolicy $policy,
        private BoardMemberRepository $members,
        private BoardAuthority $authority,
    ) {
    }

    /**
     * Load a thread (findWithBoard shape) iff $user may read it; 404 otherwise.
     *
     * @return array<string,mixed>
     */
    public function loadForUser(?User $user, int $threadId): array
    {
        $thread = $this->threads->findWithBoard($threadId);
        if ($thread === null) {
            throw new NotFoundException('Thread not found.');
        }
        return $this->assertReadableRows($user, $thread, [
            'id' => (int) $thread['board_id'],
            'visibility' => (string) $thread['board_visibility'],
        ]);
    }

    /**
     * Apply the canonical read gate to caller-supplied current rows. Mutation
     * services use this after locking the thread and board, avoiding a stale
     * non-locking reload between authorization and the write.
     *
     * @param array<string,mixed> $thread
     * @param array<string,mixed> $board
     * @return array<string,mixed>
     */
    public function assertReadableRows(?User $user, array $thread, array $board): array
    {
        if ((int) ($thread['is_deleted'] ?? 0) === 1) {
            throw new NotFoundException('Thread not found.');
        }
        $boardId = (int) ($board['id'] ?? 0);
        if ($boardId <= 0 || (int) ($thread['board_id'] ?? 0) !== $boardId) {
            throw new NotFoundException('Thread not found.');
        }
        $isMember = $user !== null && $this->members->isMember($boardId, $user->id());
        // Spec §1 decision: board-moderator ASSIGNMENT counts as readable in
        // moderation flows — a non-member moderator of a private board must be
        // able to see their own 422 re-render. BoardPolicy stays pure; the
        // assignment is resolved here and passed nowhere else.
        $readable = $this->policy->canRead(['visibility' => (string) ($board['visibility'] ?? 'private')], $user, $isMember)
            || ($user !== null && $this->authority->isAssigned($user, $boardId));
        if (!$readable) {
            throw new NotFoundException('Thread not found.');
        }
        // A held (pending) thread is not public yet: only its author or a
        // moderator of THIS board may load it (mirrors the held-media gate). P3-05.
        // Board-scoped canModerate() (not the site-wide core.content.view_pending
        // key): the legacy projection grants every global moderator a site-scoped
        // view_pending to match the bare isModerator() site probes at /mod/approvals
        // and the held-media view, and a site grant satisfies any board target — so
        // keying this board-scoped view on it would let an unassigned global
        // moderator open held threads they never could pre-cutover (review S1).
        // Deliberately the same WriteGate-consuming predicate as before the
        // PR #44 extraction.
        if ((int) ($thread['is_pending'] ?? 0) === 1) {
            $isAuthor = $user !== null && $user->owns((int) $thread['user_id']);
            $canMod = $user !== null && $this->authority->canModerate($user, $boardId);
            if (!$isAuthor && !$canMod) {
                throw new NotFoundException('Thread not found.');
            }
        }
        return $thread;
    }
}
