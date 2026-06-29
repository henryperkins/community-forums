<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Hook\FirstPartyHookRegistry;
use App\Repository\BoardModeratorRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;

/**
 * User moderation records (P2-08): warnings, staff notes, site suspensions/bans,
 * and lifts. The `bans` table is the system-of-record/history; users.status +
 * suspended_until is the denormalised fast-path the WriteGate reads. Warnings and
 * notes are staff actions (admin or any board moderator); suspend/ban/lift are
 * site-level (admin only) and never target the actor or another admin. Every
 * action writes an immutable moderation_log row.
 */
final class UserModerationService
{
    public function __construct(
        private Database $db,
        private UserRepository $users,
        private ModerationLogRepository $log,
        private WriteGate $writeGate,
        private BoardModeratorRepository $boardMods,
        private ?FirstPartyHookRegistry $hooks = null,
    ) {
    }

    public function warn(User $actor, int $subjectId, string $reason, ?int $boardId = null): void
    {
        $this->assertStaff($actor);
        $reason = $this->requireReason($reason);
        $subject = $this->requireSubject($subjectId);

        $this->db->transaction(function () use ($actor, $subject, $reason, $boardId): void {
            $this->db->run(
                'INSERT INTO warnings (user_id, issued_by, board_id, reason, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
                [(int) $subject['id'], $actor->id(), $boardId, $reason],
            );
            $this->audit($actor, 'warn', (int) $subject['id'], $reason);
        });
    }

    public function addNote(User $actor, int $subjectId, string $body): void
    {
        $this->assertStaff($actor);
        $body = trim($body);
        if ($body === '') {
            throw new ValidationException(['body' => 'A note cannot be empty.']);
        }
        $subject = $this->requireSubject($subjectId);
        $this->db->run(
            'INSERT INTO user_notes (subject_user_id, author_id, body, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [(int) $subject['id'], $actor->id(), $body],
        );
    }

    public function suspend(User $actor, int $subjectId, ?string $until, string $reason): void
    {
        $this->assertAdmin($actor);
        $reason = $this->requireReason($reason);
        $subject = $this->requireGovernable($actor, $subjectId);

        $this->db->transaction(function () use ($actor, $subject, $until, $reason): void {
            $this->users->setStatus((int) $subject['id'], 'suspended', $until);
            $this->db->run(
                "INSERT INTO bans (user_id, scope, type, reason, created_by, created_at, expires_at)
                 VALUES (?, 'site', 'full', ?, ?, UTC_TIMESTAMP(), ?)",
                [(int) $subject['id'], $reason, $actor->id(), $until],
            );
            $this->audit($actor, 'suspend', (int) $subject['id'], $reason);
        });
    }

    public function ban(User $actor, int $subjectId, string $reason): void
    {
        $this->assertAdmin($actor);
        $reason = $this->requireReason($reason);
        $subject = $this->requireGovernable($actor, $subjectId);

        $banId = $this->db->transaction(function () use ($actor, $subject, $reason): int {
            $this->users->setStatus((int) $subject['id'], 'banned', null);
            $banId = $this->db->insert(
                "INSERT INTO bans (user_id, scope, type, reason, created_by, created_at, expires_at)
                 VALUES (?, 'site', 'full', ?, ?, UTC_TIMESTAMP(), NULL)",
                [(int) $subject['id'], $reason, $actor->id()],
            );
            $this->audit($actor, 'ban', (int) $subject['id'], $reason);
            return $banId;
        });
        $this->hooks?->emit('member.banned', [
            'user_id' => (int) $subject['id'],
            'ban_id' => $banId,
            'actor_id' => $actor->id(),
        ], 'user:' . (int) $subject['id'] . ':banned:' . $banId);
    }

    public function lift(User $actor, int $subjectId): void
    {
        $this->assertAdmin($actor);
        $subject = $this->requireSubject($subjectId);

        $this->db->transaction(function () use ($actor, $subject): void {
            $this->users->setStatus((int) $subject['id'], 'active', null);
            $this->db->run(
                'UPDATE bans SET lifted_at = UTC_TIMESTAMP(), lifted_by = ? WHERE user_id = ? AND lifted_at IS NULL',
                [$actor->id(), (int) $subject['id']],
            );
            $this->audit($actor, 'lift', (int) $subject['id'], null);
        });
    }

    // ---- guards -----------------------------------------------------------

    private function assertStaff(User $actor): void
    {
        $this->writeGate->assertCanWrite($actor);
        if (!$actor->isAdmin() && $this->boardMods->boardsFor($actor->id()) === []) {
            throw new ForbiddenException('Staff access required.');
        }
    }

    private function assertAdmin(User $actor): void
    {
        $this->writeGate->assertCanWrite($actor);
        if (!$actor->isAdmin()) {
            throw new ForbiddenException('Administrator access required.');
        }
    }

    /** @return array<string,mixed> */
    private function requireSubject(int $subjectId): array
    {
        $subject = $this->users->find($subjectId);
        if ($subject === null) {
            throw new NotFoundException('User not found.');
        }
        return $subject;
    }

    /** @return array<string,mixed> a subject that is not the actor and not another admin */
    private function requireGovernable(User $actor, int $subjectId): array
    {
        $subject = $this->requireSubject($subjectId);
        if ((int) $subject['id'] === $actor->id()) {
            throw new ValidationException(['user' => 'You cannot moderate your own account.']);
        }
        if (($subject['role'] ?? 'user') === 'admin') {
            throw new ForbiddenException('Administrators cannot be suspended or banned here.');
        }
        return $subject;
    }

    private function requireReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new ValidationException(['reason' => 'A reason is required.']);
        }
        return $reason;
    }

    private function audit(User $actor, string $action, int $subjectId, ?string $reason): void
    {
        $this->log->log([
            'actor_id' => $actor->id(),
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $subjectId,
            'reason' => $reason,
        ]);
    }
}
