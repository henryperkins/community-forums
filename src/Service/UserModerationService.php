<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\AttachmentRepository;
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
        private ?AttachmentRepository $attachments = null,
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
        $until = $this->validateSuspendUntil($until);
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

    private function validateSuspendUntil(?string $until): ?string
    {
        $until = trim((string) $until);
        if ($until === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $until, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d H:i:s') !== $until
        ) {
            throw new ValidationException(['until' => 'Use a valid UTC timestamp in YYYY-MM-DD HH:MM:SS format.']);
        }

        return $until;
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

    /**
     * Admin cosmetic-title override (COMMUNITY §8, ADMIN §5.2). Trims and strips
     * control characters; an empty result clears the override (NULL → derived
     * ladder). Caps at 64 chars (users.title VARCHAR(64)). Routes through
     * assertAdmin so a suspended admin is blocked (state beats role). Audits
     * set_title / clear_title with the before/after value.
     */
    public function setTitle(User $actor, int $subjectId, ?string $title): void
    {
        $this->assertAdmin($actor);
        $subject = $this->requireSubject($subjectId);

        $stripped = preg_replace('/[\x00-\x1F\x7F]+/', '', $title ?? '') ?? '';
        $clean = trim($stripped);
        if (mb_strlen($clean) > 64) {
            throw new ValidationException(
                ['title' => 'Title must be 64 characters or fewer.'],
                ['title' => (string) $title],
            );
        }
        $newTitle = $clean === '' ? null : $clean;
        $before = isset($subject['title']) && $subject['title'] !== null ? (string) $subject['title'] : null;

        $this->db->transaction(function () use ($actor, $subjectId, $newTitle, $before): void {
            $this->users->setTitle($subjectId, $newTitle);
            $this->log->log([
                'actor_id' => $actor->id(),
                'action' => $newTitle !== null ? 'set_title' : 'clear_title',
                'target_type' => 'user',
                'target_id' => $subjectId,
                'before' => $before,
                'after' => $newTitle,
            ]);
        });
    }

    public function clearSignature(User $actor, int $subjectId): void
    {
        $this->assertAdmin($actor);
        $subject = $this->requireSubject($subjectId);
        $before = isset($subject['signature']) && $subject['signature'] !== null ? (string) $subject['signature'] : null;

        $this->db->transaction(function () use ($actor, $subjectId, $before): void {
            $this->users->clearSignature($subjectId, $actor->id());
            $this->log->log([
                'actor_id' => $actor->id(),
                'action' => 'clear_signature',
                'target_type' => 'user',
                'target_id' => $subjectId,
                'before' => $before,
                'after' => null,
            ]);
        });
    }

    public function clearAvatar(User $actor, int $subjectId): void
    {
        $this->assertAdmin($actor);
        $subject = $this->requireSubject($subjectId);
        $before = isset($subject['avatar_path']) && $subject['avatar_path'] !== null ? (string) $subject['avatar_path'] : null;

        $this->db->transaction(function () use ($actor, $subjectId, $before): void {
            $this->deleteLocalAvatar($before);
            $this->users->setAvatar($subjectId, null, 'monogram', $actor->id());
            $this->log->log([
                'actor_id' => $actor->id(),
                'action' => 'clear_avatar',
                'target_type' => 'user',
                'target_id' => $subjectId,
                'before' => $before,
                'after' => null,
            ]);
        });
    }

    private function deleteLocalAvatar(?string $path): void
    {
        if ($this->attachments === null || $path === null || preg_match('~^/media/(\d+)$~', $path, $matches) !== 1) {
            return;
        }

        $this->attachments->markDeleted((int) $matches[1]);
    }

    /**
     * Read-side aggregate for the admin record screen (ADMIN §5.2): recent
     * warnings, private staff notes, ban history, and the target's audit trail.
     * Every list is capped so the record view stays bounded.
     *
     * @return array{
     *   warnings:array<int,array<string,mixed>>,
     *   notes:array<int,array<string,mixed>>,
     *   bans:array<int,array<string,mixed>>,
     *   log:array<int,array<string,mixed>>
     * }
     */
    public function history(int $subjectId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        return [
            'warnings' => $this->db->fetchAll(
                'SELECT w.id, w.reason, w.points, w.board_id, w.created_at,
                        u.username AS issued_by_username
                 FROM warnings w
                 LEFT JOIN users u ON u.id = w.issued_by
                 WHERE w.user_id = ?
                 ORDER BY w.id DESC
                 LIMIT ' . $limit,
                [$subjectId],
            ),
            'notes' => $this->db->fetchAll(
                'SELECT n.id, n.body, n.created_at, u.username AS author_username
                 FROM user_notes n
                 LEFT JOIN users u ON u.id = n.author_id
                 WHERE n.subject_user_id = ?
                 ORDER BY n.id DESC
                 LIMIT ' . $limit,
                [$subjectId],
            ),
            'bans' => $this->db->fetchAll(
                'SELECT b.id, b.scope, b.type, b.reason, b.created_at, b.expires_at, b.lifted_at,
                        c.username AS created_by_username, l.username AS lifted_by_username
                 FROM bans b
                 LEFT JOIN users c ON c.id = b.created_by
                 LEFT JOIN users l ON l.id = b.lifted_by
                 WHERE b.user_id = ?
                 ORDER BY b.id DESC
                 LIMIT ' . $limit,
                [$subjectId],
            ),
            'log' => $this->log->recentForTarget('user', $subjectId, $limit),
        ];
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
