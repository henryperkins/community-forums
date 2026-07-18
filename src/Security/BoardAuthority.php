<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\User;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;

/**
 * Per-board moderation authority, extracted from ModerationService (PR #44
 * remediation, spec §1) so the thread read gate can consult it without a
 * container cycle: BoardAuthority ← ThreadReadService ← ModerationService.
 * The predicate bodies moved verbatim — same AuthorityGate fallback, same
 * capability keys, same gate-site strings, so legacy/shadow telemetry and
 * enforce-mode decisions are unchanged.
 */
final class BoardAuthority
{
    public function __construct(
        private WriteGate $writeGate,
        private BoardModeratorRepository $boardMods,
        private BoardRepository $boards,
        private ?AuthorityGate $authority = null,
    ) {
    }

    private function gate(): AuthorityGate
    {
        return $this->authority ?? AuthorityGate::legacy();
    }

    /** Non-throwing capability check (admin anywhere, or assigned board moderator). */
    public function canModerate(User $user, int $boardId, string $capability = Cap::POST_DELETE_ANY): bool
    {
        return $this->gate()->allows(
            fn (): bool => $this->writeGate->canWrite($user)
                && ($user->isAdmin() || $this->boardMods->isModerator($boardId, $user->id())),
            $user,
            $capability,
            ['board_id' => $boardId],
            'ModerationService::canModerate',
        );
    }

    /**
     * Queue discovery (Inc 6 follow-up): the boards on which $user holds
     * $capability, asked through the gate per board so every mode answers
     * consistently — legacy/shadow reproduce admin-or-assigned exactly,
     * while enforce lets a custom deputy's board- or site-scoped grant
     * surface its rows. Returns null for site-wide authority (no row
     * filter), [] for none.
     *
     * @return ?list<int>
     */
    public function moderableBoardIds(User $user, string $capability): ?array
    {
        if ($this->gate()->allows(
            fn (): bool => $user->isAdmin(),
            $user,
            $capability,
            [], // site probe: board-scoped grants deliberately do not qualify
            'ModerationService::moderableBoardIds',
        )) {
            return null;
        }

        $ids = [];
        foreach ($this->boards->allOrdered() as $board) {
            $boardId = (int) $board['id'];
            if ($this->canModerate($user, $boardId, $capability)) {
                $ids[] = $boardId;
            }
        }

        return $ids;
    }

    /**
     * Raw board-moderator assignment, deliberately WITHOUT WriteGate: reading
     * must not consume write state — "a suspended admin can read but not
     * write", and likewise a suspended assigned moderator keeps read access
     * to the boards they moderate (spec §1 readability decision).
     */
    public function isAssigned(User $user, int $boardId): bool
    {
        return $this->boardMods->isModerator($boardId, $user->id());
    }
}
