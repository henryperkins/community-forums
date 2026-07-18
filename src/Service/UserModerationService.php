<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use App\Core\Database;
use App\Core\DuplicateSubmissionException;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\AttachmentRepository;
use App\Hook\FirstPartyHookRegistry;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\IdempotencyRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use App\Security\AuthorityGate;
use App\Security\BoardAuthority;
use App\Security\Cap;
use App\Security\CapabilityResolver;
use App\Security\LastOwnerGuard;
use App\Security\ReauthGate;
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
    private ?AuditQueryService $auditQueryService = null;

    public function __construct(
        private Database $db,
        private UserRepository $users,
        private ModerationLogRepository $log,
        private WriteGate $writeGate,
        private BoardModeratorRepository $boardMods,
        private ?AttachmentRepository $attachments = null,
        private ?FirstPartyHookRegistry $hooks = null,
        private ?AuthorityGate $authority = null,
        private ?LastOwnerGuard $ownerGuard = null,
        private ?ProtectedOwnerRepository $owners = null,
        private ?SessionRepository $sessions = null,
        private ?ReauthGate $reauth = null,
        private ?CapabilityResolver $resolver = null,
        private ?BoardRepository $boards = null,
        private ?IdempotencyRepository $idempotency = null,
        private ?BoardAuthority $boardAuthority = null,
    ) {
    }

    private function gate(): AuthorityGate
    {
        return $this->authority ?? AuthorityGate::legacy();
    }

    public function warn(User $actor, int $subjectId, string $reason, ?int $boardId = null, ?string $idempotencyKey = null): void
    {
        $this->assertStaff($actor);
        $reason = $this->requireReason($reason);
        $subject = $this->requireSubject($subjectId);
        if ((int) $subject['id'] === $actor->id()) {
            throw new ValidationException(['reason' => 'You cannot warn your own account.']);
        }

        if (!$actor->isAdmin()) {
            $overlap = $this->moderatorOverlap($actor, $subjectId);
            if ($overlap === []) {
                // Byte-identical to the missing-subject 404 (spec §2): an
                // out-of-scope subject does not exist for this actor.
                throw new NotFoundException('User not found.');
            }
            // Board attribution is required and revalidated server-side —
            // assigned to the actor AND participated-in by the subject (that
            // conjunction IS overlap membership). One uniform message for
            // every miss, so the field is not a board-existence oracle.
            if ($boardId === null || !in_array($boardId, $overlap, true)) {
                throw new ValidationException(
                    ['board_id' => 'Choose a board you moderate where this member has participated.'],
                );
            }
            $this->assertCanWarnBoard($actor, $boardId);
        } elseif ($boardId !== null && $this->boards !== null && $this->boards->find($boardId) === null) {
            throw new ValidationException(['board_id' => 'Choose a valid board.']);
        }

        // Idempotent submit (composer seam, P3-03): a duplicate replays the
        // original outcome instead of stacking warnings.
        $key = $this->idempotency?->hash($idempotencyKey);
        if ($key !== null) {
            $existing = $this->idempotency->findWithContext($actor->id(), $key);
            if ($existing !== null && $existing['context'] === 'mod_warn') {
                return; // the original warn already committed — nothing to do
            }
        }

        $this->db->transaction(function () use ($actor, $subject, $reason, $boardId, $key): void {
            $warningId = $this->db->insert(
                'INSERT INTO warnings (user_id, issued_by, board_id, reason, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
                [(int) $subject['id'], $actor->id(), $boardId, $reason],
            );
            // Claim the key before the audit row so a concurrent duplicate
            // rolls the whole write back and the caller replays.
            if ($key !== null && !$this->idempotency->record($actor->id(), $key, 'mod_warn', 'warning', $warningId)) {
                throw new DuplicateSubmissionException();
            }
            $this->log->log([
                'actor_id' => $actor->id(),
                'action' => 'warn',
                'target_type' => 'user',
                'target_id' => (int) $subject['id'],
                'reason' => $reason,
                'after' => ['board_id' => $boardId],
            ]);
        });
    }

    public function addNote(User $actor, int $subjectId, string $body): void
    {
        $this->assertStaff($actor);
        // Notes are admin-only (PR #44 review decision, recorded in ADR 0021):
        // globally-scoped user_notes readable by any board moderator was
        // strictly worse than narrowing ADMIN §3.4's "Add mod note" mapping.
        $this->assertAdmin($actor);
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

    /**
     * Actor-aware staff-panel model (spec §2). Admin → identity + the full
     * history + site-wide warn options. Non-admin staff → a board-scoped
     * model for subjects who participated in a board the actor moderates:
     * whitelisted identity keys (no email), the overlap boards, warnings
     * scoped to those boards, and nothing else — notes, bans, and the audit
     * trail are not queried at all. An empty overlap throws the byte-identical
     * missing-subject 404.
     *
     * @return array<string,mixed>
     */
    public function panelFor(User $actor, int $subjectId): array
    {
        $subject = $this->requireSubject($subjectId);

        if ($actor->isAdmin()) {
            $names = [];
            $options = [];
            foreach ($this->boards?->allOrdered() ?? [] as $board) {
                $names[(int) $board['id']] = (string) $board['name'];
                $options[] = ['id' => (int) $board['id'], 'name' => (string) $board['name']];
            }
            return [
                'scope' => 'admin',
                'subject' => $subject,
                'history' => $this->history($subjectId),
                'warn_board_options' => $options,
                'board_names' => $names,
            ];
        }

        $overlap = $this->moderatorOverlap($actor, $subjectId);
        if ($overlap === []) {
            throw new NotFoundException('User not found.');
        }
        $names = [];
        $options = [];
        foreach ($this->boards?->allOrdered() ?? [] as $board) {
            if (!in_array((int) $board['id'], $overlap, true)) {
                continue;
            }
            $names[(int) $board['id']] = (string) $board['name'];
            $options[] = ['id' => (int) $board['id'], 'name' => (string) $board['name']];
        }

        return [
            'scope' => 'moderator',
            'subject' => [
                'id' => (int) $subject['id'],
                'username' => (string) $subject['username'],
                'display_name' => $subject['display_name'],
                'role' => (string) $subject['role'],
                'status' => (string) $subject['status'],
                'suspended_until' => $subject['suspended_until'] ?? null,
                'created_at' => $subject['created_at'] ?? null,
                'last_seen_at' => $subject['last_seen_at'] ?? null,
                'post_count' => (int) ($subject['post_count'] ?? 0),
                'reputation' => (int) ($subject['reputation'] ?? 0),
            ],
            'history' => ['warnings' => $this->scopedWarnings($subjectId, $overlap)],
            'warn_board_options' => $options,
            'board_names' => $names,
        ];
    }

    /**
     * Boards where the subject has authored a thread or post, restricted to
     * $boardIds. Deliberately WITHOUT is_deleted/is_pending filters: the panel
     * exists for accountability, and hidden, held, and deleted content is
     * exactly what gets moderated. Anonymous authorship also counts — the
     * anonymity mask is render-time attribution, not provenance; this reads
     * the stored user_id like every other moderation surface (spec §2).
     *
     * @param list<int> $boardIds
     * @return list<int>
     */
    private function participationBoards(int $subjectId, array $boardIds): array
    {
        if ($boardIds === []) {
            return [];
        }
        $in = implode(',', array_map('intval', $boardIds));
        $found = [];
        foreach ($this->db->fetchAll(
            "SELECT DISTINCT board_id FROM threads WHERE user_id = ? AND board_id IN ($in)",
            [$subjectId],
        ) as $row) {
            $found[(int) $row['board_id']] = true;
        }
        foreach ($this->db->fetchAll(
            "SELECT DISTINCT t.board_id FROM posts p JOIN threads t ON t.id = p.thread_id
             WHERE p.user_id = ? AND t.board_id IN ($in)",
            [$subjectId],
        ) as $row) {
            $found[(int) $row['board_id']] = true;
        }
        return array_keys($found);
    }

    /** @return list<int> the actor's moderated boards where the subject participated */
    private function moderatorOverlap(User $actor, int $subjectId): array
    {
        return $this->participationBoards($subjectId, $this->boardMods->boardsFor($actor->id()));
    }

    /**
     * @param list<int> $boardIds
     * @return array<int,array<string,mixed>>
     */
    private function scopedWarnings(int $subjectId, array $boardIds, int $limit = 10): array
    {
        if ($boardIds === []) {
            return [];
        }
        $in = implode(',', array_map('intval', $boardIds));
        $limit = max(1, min(50, $limit));
        return $this->db->fetchAll(
            "SELECT w.id, w.reason, w.points, w.board_id, w.created_at,
                    u.username AS issued_by_username
             FROM warnings w
             LEFT JOIN users u ON u.id = w.issued_by
             WHERE w.user_id = ? AND w.board_id IN ($in)
             ORDER BY w.id DESC
             LIMIT " . $limit,
            [$subjectId],
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
            // History row: type='post' (read-only) — a suspension keeps login +
            // read access (ADMIN §1.2), unlike a full ban. Enforcement rides
            // users.status; this row is the accountable record.
            $this->db->run(
                "INSERT INTO bans (user_id, scope, type, reason, created_by, created_at, expires_at)
                 VALUES (?, 'site', 'post', ?, ?, UTC_TIMESTAMP(), ?)",
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
     * In-app `users.role` mutation (ADMIN §5.2, TM-PE-07). Reauth-gated like the
     * other high-impact admin actions. Demoting an admin who is the last active
     * protected owner is blocked by LastOwnerGuard inside the same transaction
     * that would perform the demotion — TOCTOU-safe via the guard's FOR UPDATE
     * lock. A demoted owner's protected_owners row is deactivated so it cannot
     * outlive the authority it mirrors; a promotion to admin (re)designates one.
     * Every path revokes the target's sessions and writes one change_role audit
     * row, then invalidates the CapabilityResolver's per-request memo so the
     * mutation is observed within the same request.
     *
     * @param 'user'|'moderator'|'admin' $newRole
     */
    public function changeRole(User $admin, string $currentPassword, int $userId, string $newRole): void
    {
        if (!$admin->isAdmin()) {
            throw new ForbiddenException('Admin access required.');
        }
        if ($this->ownerGuard === null || $this->owners === null || $this->sessions === null || $this->reauth === null || $this->resolver === null) {
            throw new \LogicException('Role-change dependencies are not wired.');
        }
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        if (!in_array($newRole, ['user', 'moderator', 'admin'], true)) {
            throw new ValidationException(['role' => 'Unknown role.']);
        }
        $row = $this->users->find($userId);
        if ($row === null || ($row['status'] ?? '') === 'deleted') {
            throw new ValidationException(['role' => 'No such member.']);
        }
        if (($row['role'] ?? '') === $newRole) {
            throw new ValidationException(['role' => 'The member already has this role.']);
        }
        $target = User::fromRow($row);

        $this->db->transaction(function () use ($admin, $target, $userId, $row, $newRole): void {
            if ($target->isAdmin() && $newRole !== 'admin') {
                $this->ownerGuard->assertNotLastOwnerForUpdate($target, 'role');
                $this->owners->deactivate($userId);
            }
            $this->users->setRole($userId, $newRole);
            if ($newRole === 'admin') {
                $this->owners->designateOrReactivate($userId, $admin->id());
            }
            $this->sessions->revokeAllForUser($userId);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'change_role',
                'target_type' => 'user',
                'target_id' => $userId,
                'before' => ['role' => (string) $row['role']],
                'after' => ['role' => $newRole],
            ]);
        });
        $this->resolver->invalidate();
    }

    /**
     * Gated PII disclosure for the admin record (ADMIN §5.2/§5.5): returns the
     * subject's email plus recently observed session/post IPs, and writes ONE
     * view_pii audit row per disclosure — access is the audited event. The
     * values are returned for a single render, never stored in the view state.
     *
     * @return array{email:string,session_ips:array<int,string>,post_ips:array<int,string>}
     */
    public function revealPii(User $admin, int $subjectId): array
    {
        $this->assertAdmin($admin);
        $subject = $this->requireSubject($subjectId);

        // Five distinct addresses each, most recently OBSERVED first —
        // "recent" is activity time, never VARBINARY byte order (spec §6).
        $sessionIps = [];
        foreach ($this->db->fetchAll(
            'SELECT ip FROM sessions WHERE user_id = ? AND ip IS NOT NULL
             GROUP BY ip ORDER BY MAX(last_seen_at) DESC LIMIT 5',
            [$subjectId],
        ) as $row) {
            $unpacked = @inet_ntop((string) $row['ip']);
            if ($unpacked !== false) {
                $sessionIps[] = $unpacked;
            }
        }
        $postIps = [];
        foreach ($this->db->fetchAll(
            'SELECT ip FROM posts WHERE user_id = ? AND ip IS NOT NULL
             GROUP BY ip ORDER BY MAX(created_at) DESC LIMIT 5',
            [$subjectId],
        ) as $row) {
            $unpacked = @inet_ntop((string) $row['ip']);
            if ($unpacked !== false) {
                $postIps[] = $unpacked;
            }
        }

        $this->log->log([
            'actor_id' => $admin->id(),
            'action' => 'view_pii',
            'target_type' => 'user',
            'target_id' => $subjectId,
            'reason' => 'admin_record_reveal',
        ]);

        return [
            'email' => (string) ($subject['email'] ?? ''),
            'session_ips' => $sessionIps,
            'post_ips' => $postIps,
        ];
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
            'log' => $this->auditQuery()->enrich($this->log->recentForTarget('user', $subjectId, $limit)),
        ];
    }

    /** Lazy enricher for the audit-trail leg (both ctor deps are in hand). */
    private function auditQuery(): AuditQueryService
    {
        return $this->auditQueryService ??= new AuditQueryService($this->log, $this->users);
    }

    /**
     * Directory read model for /admin/users (PR #44 spec §4): rows plus the
     * real filtered total, so has_next is honest on exact page multiples.
     *
     * @param array<string,string> $filters allowlisted by the controller
     * @return array<string,mixed>
     */
    public function directoryModel(array $filters, int $page, int $perPage = 50): array
    {
        $page = max(0, $page);
        $perPage = max(1, min(200, $perPage));
        $total = $this->users->directoryCount($filters);

        return [
            'users' => $this->users->directory($filters + ['limit' => $perPage, 'offset' => $page * $perPage]),
            'filters' => $filters,
            'q' => (string) ($filters['q'] ?? ''),
            'page' => $page,
            'total' => $total,
            'has_next' => ($page + 1) * $perPage < $total,
            'base_query' => array_filter($filters, static fn ($v): bool => $v !== '' && $v !== null),
        ];
    }

    /**
     * Step-1 subjects for the bulk confirmation (ADMIN §5.1/§3.2).
     *
     * @param list<int> $ids
     * @return array<int,array<string,mixed>>
     */
    public function bulkPlan(string $action, array $ids): array
    {
        $ids = $this->normalizeBulkCommand($action, $ids);
        $subjects = [];
        foreach ($ids as $id) {
            $row = $this->users->find((int) $id);
            if ($row !== null) {
                $subjects[] = $row;
            }
        }
        if ($subjects === []) {
            throw new NotFoundException('No matching members.');
        }
        return $subjects;
    }

    /**
     * Step-2 bulk apply — one audited service call per member. Per-member
     * refusals (an admin target, yourself) are skipped and reported; a
     * shared-input failure (empty reason, malformed expiry) on the first
     * member rethrows before anything is written (behavior-preserving move
     * from AdminUserController::bulkApply — PR #44 spec §4 boundary).
     *
     * @param list<int> $ids
     * @return array{done:int, skipped:list<string>}
     */
    public function bulkApply(User $admin, string $action, array $ids, string $reason, ?string $until): array
    {
        $ids = $this->normalizeBulkCommand($action, $ids);
        $done = 0;
        $skipped = [];
        foreach ($ids as $id) {
            try {
                if ($action === 'suspend') {
                    $this->suspend($admin, $id, $until, $reason);
                } else {
                    $this->warn($admin, $id, $reason);
                }
                $done++;
            } catch (ValidationException $e) {
                if ($done === 0 && (isset($e->errors['reason']) || isset($e->errors['until']))) {
                    // Shared-input failure — nothing has been written yet.
                    throw $e;
                }
                $skipped[] = $this->usernameOf($id) . ' (' . $e->first() . ')';
            } catch (ForbiddenException $e) {
                $skipped[] = $this->usernameOf($id) . ' (' . $e->getMessage() . ')';
            }
        }
        return ['done' => $done, 'skipped' => $skipped];
    }

    /** @param list<int> $ids @return list<int> */
    private function normalizeBulkCommand(string $action, array $ids): array
    {
        if (!in_array($action, ['warn', 'suspend'], true)) {
            throw new ValidationException(['bulk_action' => 'Choose a valid bulk action.']);
        }
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0,
        )));
        if ($ids === []) {
            throw new NotFoundException('No matching members.');
        }
        return $ids;
    }

    private function usernameOf(int $id): string
    {
        $row = $this->users->find($id);
        return $row !== null ? '@' . (string) $row['username'] : '#' . $id;
    }

    // ---- guards -----------------------------------------------------------

    private function assertStaff(User $actor): void
    {
        $this->writeGate->assertCanWrite($actor);
        $this->gate()->assert(
            fn (): bool => $actor->isAdmin() || $this->boardMods->boardsFor($actor->id()) !== [],
            $actor,
            Cap::USER_WARN,
            [], // site probe: staff-any — admin OR moderates ≥1 board, site-wide
            'UserModerationService::assertStaff',
            'Staff access required.', // keep the existing message verbatim
        );
    }

    private function assertCanWarnBoard(User $actor, int $boardId): void
    {
        if ($this->boardAuthority !== null) {
            if (!$this->boardAuthority->canModerate($actor, $boardId, Cap::USER_WARN)) {
                throw new ForbiddenException('You do not have permission to warn members for this board.');
            }
            return;
        }

        $this->gate()->assert(
            fn (): bool => $actor->isAdmin() || $this->boardMods->isModerator($boardId, $actor->id()),
            $actor,
            Cap::USER_WARN,
            ['board_id' => $boardId],
            'UserModerationService::warn',
            'You do not have permission to warn members for this board.',
        );
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
