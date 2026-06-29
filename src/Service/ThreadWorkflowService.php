<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\ThreadAssignmentRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;

final class ThreadWorkflowService
{
    /** @var array<string,string> */
    public const STATUSES = [
        'open' => 'Open',
        'needs_answer' => 'Needs answer',
        'solved' => 'Solved',
        'decision_made' => 'Decision made',
        'archived' => 'Archived',
    ];

    public function __construct(
        private Database $db,
        private ThreadRepository $threads,
        private ThreadAssignmentRepository $assignments,
        private UserRepository $users,
        private BoardModeratorRepository $boardModerators,
        private BoardMemberRepository $boardMembers,
        private ModerationLogRepository $log,
        private WriteGate $writeGate,
    ) {
    }

    public function setStatus(User $actor, int $threadId, string $status, ?string $reason = null): void
    {
        $this->writeGate->assertCanWrite($actor);
        if (!isset(self::STATUSES[$status])) {
            throw new ValidationException(['status' => 'Choose a valid topic status.']);
        }
        $thread = $this->threadOrFail($threadId);
        if ((int) ($thread['board_is_archived'] ?? 0) === 1) {
            throw new ForbiddenException('This board is archived and is read-only.');
        }
        $this->authorizeStatus($actor, $thread, $status);

        $previous = (string) ($thread['status'] ?? 'open');
        if ($previous === $status) {
            return;
        }
        $reason = $this->cleanReason($reason);

        $this->db->transaction(function () use ($threadId, $actor, $previous, $status, $reason): void {
            $this->threads->setStatus($threadId, $status, $actor->id());
            $this->threads->addStatusHistory($threadId, $actor->id(), $previous, $status, $reason);
            $this->log->log([
                'actor_id' => $actor->id(),
                'action' => 'thread.status',
                'target_type' => 'thread',
                'target_id' => $threadId,
                'reason' => $reason,
                'before' => ['status' => $previous],
                'after' => ['status' => $status],
            ]);
        });
    }

    public function syncSolvedStatus(User $actor, int $threadId, bool $solved): void
    {
        $thread = $this->threadOrFail($threadId);
        $current = (string) ($thread['status'] ?? 'open');
        if ($solved && $current !== 'solved') {
            $this->setStatus($actor, $threadId, 'solved', 'accepted_answer');
        } elseif (!$solved && $current === 'solved') {
            $this->setStatus($actor, $threadId, 'open', 'accepted_answer_cleared');
        }
    }

    public function assign(User $actor, int $threadId, int $assigneeId, ?string $reason = null): void
    {
        $this->writeGate->assertCanWrite($actor);
        $thread = $this->threadOrFail($threadId);
        if ((int) ($thread['board_is_archived'] ?? 0) === 1) {
            throw new ForbiddenException('This board is archived and is read-only.');
        }
        $assignee = $this->users->find($assigneeId);
        if ($assignee === null) {
            throw new NotFoundException('Assignee not found.');
        }
        $this->authorizeAssignment($actor, $thread, $assignee);
        $reason = $this->cleanReason($reason);

        $current = $this->assignments->current($threadId);
        $previousUserId = $current !== null ? (int) $current['assigned_user_id'] : null;
        if ($previousUserId === $assigneeId) {
            return;
        }
        $action = $previousUserId === null ? 'assign' : 'reassign';

        $this->db->transaction(function () use ($threadId, $assigneeId, $actor, $previousUserId, $action, $reason): void {
            $this->assignments->assign($threadId, $assigneeId, $actor->id());
            $this->assignments->addHistory($threadId, $previousUserId, $assigneeId, $actor->id(), $action, $reason);
            $this->log->log([
                'actor_id' => $actor->id(),
                'action' => 'thread.assignment.' . $action,
                'target_type' => 'thread',
                'target_id' => $threadId,
                'reason' => $reason,
                'before' => ['assigned_user_id' => $previousUserId],
                'after' => ['assigned_user_id' => $assigneeId],
            ]);
        });
    }

    public function unassign(User $actor, int $threadId, ?string $reason = null): void
    {
        $this->writeGate->assertCanWrite($actor);
        $thread = $this->threadOrFail($threadId);
        if ((int) ($thread['board_is_archived'] ?? 0) === 1) {
            throw new ForbiddenException('This board is archived and is read-only.');
        }
        $current = $this->assignments->current($threadId);
        if ($current === null) {
            return;
        }
        $assignedUserId = (int) $current['assigned_user_id'];
        if ($assignedUserId !== $actor->id() && !$this->canStaffAssign($actor, (int) $thread['board_id'])) {
            throw new ForbiddenException('You cannot unassign this topic.');
        }
        $reason = $this->cleanReason($reason);

        $this->db->transaction(function () use ($threadId, $actor, $assignedUserId, $reason): void {
            $this->assignments->unassign($threadId);
            $this->assignments->addHistory($threadId, $assignedUserId, null, $actor->id(), 'unassign', $reason);
            $this->log->log([
                'actor_id' => $actor->id(),
                'action' => 'thread.assignment.unassign',
                'target_type' => 'thread',
                'target_id' => $threadId,
                'reason' => $reason,
                'before' => ['assigned_user_id' => $assignedUserId],
                'after' => ['assigned_user_id' => null],
            ]);
        });
    }

    /** @return array<string,mixed>|null */
    public function currentAssignment(int $threadId): ?array
    {
        return $this->assignments->current($threadId);
    }

    /** @param array<string,mixed> $thread */
    public function canChangeStatus(User $actor, array $thread, string $status): bool
    {
        try {
            $this->authorizeStatus($actor, $thread, $status);
            return true;
        } catch (ForbiddenException) {
            return false;
        }
    }

    /** @param array<string,mixed> $thread */
    public function canSelfAssign(User $actor, array $thread): bool
    {
        $mode = (string) ($thread['assignment_mode'] ?? $thread['board_assignment_mode'] ?? 'off');
        return $mode === 'self' && $this->eligibleAssignee($actor->toArray(), (int) $thread['board_id']);
    }

    /** @param array<string,mixed> $thread */
    public function canStaffAssignThread(User $actor, array $thread): bool
    {
        $mode = (string) ($thread['assignment_mode'] ?? $thread['board_assignment_mode'] ?? 'off');
        return $mode === 'staff' && $this->canStaffAssign($actor, (int) $thread['board_id']);
    }

    /** @return array<string,mixed> */
    private function threadOrFail(int $threadId): array
    {
        $thread = $this->threads->findWithBoard($threadId);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        return $thread;
    }

    /** @param array<string,mixed> $thread */
    private function authorizeStatus(User $actor, array $thread, string $status): void
    {
        $isOp = (int) $thread['user_id'] === $actor->id();
        $isStaff = $this->canStaffAssign($actor, (int) $thread['board_id']);
        $current = (string) ($thread['status'] ?? 'open');
        if (in_array($current, ['decision_made', 'archived'], true) && !$isStaff) {
            throw new ForbiddenException('Only staff can change a staff-set topic status.');
        }
        if ($status === 'decision_made' || $status === 'archived') {
            if (!$isStaff) {
                throw new ForbiddenException('Only staff can set that topic status.');
            }
            return;
        }
        if (!$isOp && !$isStaff) {
            throw new ForbiddenException('Only the topic author or a moderator can change topic status.');
        }
    }

    /** @param array<string,mixed> $thread @param array<string,mixed> $assignee */
    private function authorizeAssignment(User $actor, array $thread, array $assignee): void
    {
        $mode = (string) ($thread['board_assignment_mode'] ?? 'off');
        if ($mode === 'off') {
            throw new ForbiddenException('Assignment is not enabled for this board.');
        }
        $assigneeId = (int) $assignee['id'];
        if (!$this->eligibleAssignee($assignee, (int) $thread['board_id'])) {
            throw new ValidationException(['assignee' => 'That member is not eligible for this board.']);
        }
        if ($mode === 'self' && $actor->id() === $assigneeId) {
            return;
        }
        if ($mode === 'staff' && $this->canStaffAssign($actor, (int) $thread['board_id'])) {
            return;
        }
        throw new ForbiddenException('You cannot assign this topic.');
    }

    private function canStaffAssign(User $actor, int $boardId): bool
    {
        return $actor->isAdmin() || $this->boardModerators->isModerator($boardId, $actor->id());
    }

    /** @param array<string,mixed> $assignee */
    private function eligibleAssignee(array $assignee, int $boardId): bool
    {
        if (($assignee['status'] ?? 'active') !== 'active') {
            return false;
        }
        if (($assignee['role'] ?? 'user') === 'admin') {
            return true;
        }
        $boardVisibility = (string) ($this->db->fetchValue('SELECT visibility FROM boards WHERE id = ?', [$boardId]) ?: 'public');
        if ($boardVisibility !== 'private') {
            return true;
        }
        return $this->boardMembers->isMember($boardId, (int) $assignee['id']);
    }

    private function cleanReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);
        return $reason === '' ? null : mb_substr($reason, 0, 255);
    }
}
