<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\Telemetry;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Security\CapabilityResolver;
use App\Security\EnforcedCapabilities;
use App\Security\ReauthGate;
use App\Security\WriteGate;

/**
 * P5-09 scoped-assignment lifecycle. Custom roles only (built-in authority is
 * legacy-managed via the board-moderator/member tools); grant/renew reauth
 * (high-impact / re-broadening), revoke stays fast (narrowing-only, emergency
 * speed). The grantor ceiling (TM-PE-02) is the anti-privilege-escalation
 * guard: every capability the role carries must resolve `allowed` for the
 * grantor at the target scope, so a board-scoped deputy is mathematically
 * unable to mint SITE scope (their board-scoped grants fail `scopeSatisfies`
 * against a site target — CapabilityRules fails closed with no board in ctx).
 * Expiry is enforced decision-time by CapabilityRules; this service only
 * validates the window shape at write time.
 */
final class RoleAssignmentService
{
    public function __construct(
        private Database $db,
        private RoleRepository $roles,
        private RoleCapabilityRepository $roleCapabilities,
        private RoleAssignmentRepository $assignments,
        private RoleAssignmentHistoryRepository $history,
        private UserRepository $users,
        private BoardRepository $boards,
        private CategoryRepository $categories,
        private CapabilityResolver $resolver,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $modLog,
        private ?Telemetry $telemetry = null,
    ) {
    }

    public function grant(
        User $admin,
        string $currentPassword,
        int $roleId,
        string $username,
        string $scopeType,
        ?int $scopeId,
        ?string $startsAt,
        ?string $endsAt,
        ?string $reason,
    ): int {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        $role = $this->roles->find($roleId);
        if ($role === null || ($role['kind'] ?? '') !== 'custom') {
            throw new ValidationException(['role' => 'Only custom roles can be assigned here; built-in authority is managed by the board-moderator and member tools.']);
        }
        $this->assertRoleOnlyContainsEnforcedCapabilities($roleId);

        $subject = $this->users->findByUsername($username);
        if ($subject === null) {
            throw new ValidationException(['username' => 'No such member.']);
        }
        $subjectId = (int) $subject['id'];

        [$scopeType, $scopeId] = $this->validateScope($scopeType, $scopeId);
        [$startsAt, $endsAt] = $this->validateWindow($startsAt, $endsAt);
        $this->assertGrantorCeiling($admin, $roleId, $scopeType, $scopeId);

        return (int) $this->db->transaction(function () use ($admin, $subjectId, $roleId, $scopeType, $scopeId, $startsAt, $endsAt, $reason): int {
            // Refuse a second identical (subject, role, scope) grant — a
            // double-clicked form would otherwise mint a twin that the resolver's
            // allow-if-any-grant union keeps honoring after the first is revoked,
            // so the admin believes authority was withdrawn when it was not
            // (review S3). Mirrors AdminService::addMember's already-a-member
            // guard. (A concurrent double-submit across two connections can still
            // race this read; a DB partial-unique guarantee is a follow-up.)
            if ($this->assignments->findActiveDuplicate($subjectId, $roleId, $scopeType, $scopeId) !== null) {
                throw new ValidationException(['username' => 'That member already holds this role at this scope; revoke or renew the existing assignment instead.']);
            }
            $id = $this->assignments->create([
                'subject_id' => $subjectId,
                'role_id' => $roleId,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'grantor_id' => $admin->id(),
                'reason' => $reason,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
            $this->logHistory('grant', $id, $admin, $subjectId, $roleId, $scopeType, $scopeId, null, [
                'starts_at' => $startsAt, 'ends_at' => $endsAt, 'reason' => $reason,
            ], $reason);
            $this->audit($admin, 'assign_role', $subjectId, [
                'assignment_id' => $id, 'role_id' => $roleId, 'scope_type' => $scopeType, 'scope_id' => $scopeId,
            ]);
            $this->telemetry?->emit('role_assignment.granted', [
                'assignment_id' => $id, 'role_id' => $roleId, 'scope_type' => $scopeType, 'actor_id' => $admin->id(),
            ]);
            $this->resolver->invalidate();

            return $id;
        });
    }

    public function revoke(User $admin, int $assignmentId, ?string $reason): void
    {
        $this->writeGate->assertCanWrite($admin);

        $this->db->transaction(function () use ($admin, $assignmentId, $reason): void {
            $row = $this->assignments->findForUpdate($assignmentId);
            if ($row === null || $row['revoked_at'] !== null) {
                throw new ValidationException(['assignment' => 'This assignment is not active.']);
            }
            $this->assignments->revoke($assignmentId, $admin->id());
            $this->logHistory(
                'revoke',
                $assignmentId,
                $admin,
                (int) $row['subject_id'],
                (int) $row['role_id'],
                (string) $row['scope_type'],
                $row['scope_id'] === null ? null : (int) $row['scope_id'],
                $row,
                ['reason' => $reason],
                $reason,
            );
            $this->audit($admin, 'revoke_role', (int) $row['subject_id'], [
                'assignment_id' => $assignmentId, 'role_id' => (int) $row['role_id'],
            ]);
            $this->telemetry?->emit('role_assignment.revoked', [
                'assignment_id' => $assignmentId, 'role_id' => (int) $row['role_id'], 'actor_id' => $admin->id(),
            ]);
            $this->resolver->invalidate();
        });
    }

    public function renew(User $admin, string $currentPassword, int $assignmentId, string $endsAt): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        [, $endsAt] = $this->validateWindow(null, $endsAt);
        if ($endsAt === null) {
            throw new ValidationException(['ends_at' => 'A renewal needs a new expiry.']);
        }

        $this->db->transaction(function () use ($admin, $assignmentId, $endsAt): void {
            $row = $this->assignments->findForUpdate($assignmentId);
            if ($row === null || $row['revoked_at'] !== null) {
                throw new ValidationException(['assignment' => 'Revoked assignments cannot be renewed; create a new grant.']);
            }
            // Cross-check the new expiry against the row's OWN start (validateWindow
            // only saw the expiry and the current clock). Renewing a scheduled
            // assignment to an expiry before its start would mint an ends<=starts
            // window CapabilityRules::windowValid() can never satisfy — a grant that
            // silently never activates, a shape grant() itself rejects (review S2).
            // Both are normalized 'Y-m-d H:i:s' UTC strings, so the compare is lexical.
            if ($row['starts_at'] !== null && $endsAt <= (string) $row['starts_at']) {
                throw new ValidationException(['ends_at' => 'The expiry must be after the assignment start.']);
            }
            $this->assignments->updateEndsAt($assignmentId, $endsAt);
            $this->logHistory(
                'renew',
                $assignmentId,
                $admin,
                (int) $row['subject_id'],
                (int) $row['role_id'],
                (string) $row['scope_type'],
                $row['scope_id'] === null ? null : (int) $row['scope_id'],
                $row,
                ['ends_at' => $endsAt],
            );
            $this->audit($admin, 'renew_role', (int) $row['subject_id'], [
                'assignment_id' => $assignmentId, 'ends_at' => $endsAt,
            ]);
            $this->telemetry?->emit('role_assignment.renewed', [
                'assignment_id' => $assignmentId, 'role_id' => (int) $row['role_id'], 'actor_id' => $admin->id(),
            ]);
            $this->resolver->invalidate();
        });
    }

    /** @return list<array<string,mixed>> rows + computed status of active|scheduled|expired|revoked */
    public function listForRole(int $roleId): array
    {
        $now = gmdate('Y-m-d H:i:s');

        return array_map(static function (array $row) use ($now): array {
            $row['status'] = $row['revoked_at'] !== null ? 'revoked'
                : (($row['starts_at'] !== null && $row['starts_at'] > $now) ? 'scheduled'
                : (($row['ends_at'] !== null && $row['ends_at'] <= $now) ? 'expired' : 'active'));

            return $row;
        }, $this->assignments->listForRole($roleId));
    }

    /** @return array{0:string,1:?int} */
    private function validateScope(string $scopeType, ?int $scopeId): array
    {
        if (!in_array($scopeType, ['site', 'category', 'board'], true)) {
            throw new ValidationException(['scope_type' => 'Scope must be site, category, or board.']);
        }
        if ($scopeType === 'site') {
            return ['site', null];
        }
        if ($scopeId === null || $scopeId <= 0) {
            throw new ValidationException(['scope_id' => 'Pick the target ' . $scopeType . '.']);
        }
        $exists = $scopeType === 'board' ? $this->boards->find($scopeId) !== null : $this->categories->find($scopeId) !== null;
        if (!$exists) {
            throw new ValidationException(['scope_id' => 'No such ' . $scopeType . '.']);
        }

        return [$scopeType, $scopeId];
    }

    /**
     * @return array{0:?string,1:?string} validated UTC datetimes, normalized to
     *     'Y-m-d H:i:s'.
     *
     * Accepts full-precision timestamps (e.g. a re-submitted/service-to-service
     * value from `gmdate('Y-m-d H:i:s', ...)`) as well as the two admin-form
     * conventions used elsewhere in this codebase for the same "UTC instant"
     * shape (PermissionSimulatorService::simulate(): 'Y-m-d H:i' / 'Y-m-d\TH:i').
     * `createFromFormat` alone treats a calendar-invalid date (e.g. day 30 of
     * February) as a rollover rather than a rejection, so — mirroring
     * UserModerationService::validateSuspendUntil's getLastErrors() check — a
     * format is only accepted when it reports zero warnings/errors.
     */
    private function validateWindow(?string $startsAt, ?string $endsAt): array
    {
        $parse = static function (?string $value, string $field): ?string {
            if ($value === null || trim($value) === '') {
                return null;
            }
            $value = trim($value);
            $tz = new \DateTimeZone('UTC');
            foreach (['!Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i'] as $format) {
                $dt = \DateTimeImmutable::createFromFormat($format, $value, $tz);
                $errors = \DateTimeImmutable::getLastErrors();
                $clean = !is_array($errors) || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
                if ($dt !== false && $clean) {
                    return $dt->format('Y-m-d H:i:s');
                }
            }
            throw new ValidationException([$field => 'Use the format YYYY-MM-DD HH:MM (UTC), optionally with seconds.']);
        };

        $s = $parse($startsAt, 'starts_at');
        $e = $parse($endsAt, 'ends_at');
        if ($e !== null && $e <= gmdate('Y-m-d H:i:s')) {
            throw new ValidationException(['ends_at' => 'The expiry must be in the future.']);
        }
        if ($s !== null && $e !== null && $e <= $s) {
            throw new ValidationException(['ends_at' => 'The expiry must be after the start.']);
        }

        return [$s, $e];
    }

    /**
     * TM-PE-02 anti-privilege-escalation guard. A grantor may only mint an
     * assignment whose role's ENTIRE capability set they themselves hold,
     * resolved at the exact target scope being granted — not merely "somewhere".
     * A board-scoped grantor's grants fail `CapabilityRules::scopeSatisfies`
     * against a site-shaped target (no board in context => only a genuine
     * site-wide grant satisfies), so they cannot mint SITE (or a different
     * board's) scope. Writes a `role_assignment_denied` audit row on the FIRST
     * failing capability and stops (fail-closed, no partial evaluation).
     */
    private function assertGrantorCeiling(User $grantor, int $roleId, string $scopeType, ?int $scopeId): void
    {
        $target = match ($scopeType) {
            'board' => ['board_id' => (int) $scopeId],
            'category' => ['category_id' => (int) $scopeId],
            default => [],
        };
        foreach ($this->roleCapabilities->keysForRole($roleId) as $key) {
            if (!$this->resolver->can($grantor, $key, $target)->allowed) {
                $this->audit($grantor, 'role_assignment_denied', $grantor->id(), [
                    'role_id' => $roleId, 'capability' => $key, 'scope_type' => $scopeType, 'scope_id' => $scopeId,
                ]);
                throw new ValidationException(['scope_type' => 'You do not hold every capability in this role at that scope.']);
            }
        }
    }

    /**
     * Re-checked at grant time (not just at role-definition time in RoleService)
     * because a custom role row can predate Task 9's EnforcedCapabilities clamp
     * — a grant must never mint authority for a key with no live route
     * enforcement, regardless of when the role was defined.
     */
    private function assertRoleOnlyContainsEnforcedCapabilities(int $roleId): void
    {
        foreach ($this->roleCapabilities->keysForRole($roleId) as $key) {
            if (!EnforcedCapabilities::has($key)) {
                throw new ValidationException(['capabilities' => "'" . $key . "' is not yet enforceable; it can be assigned once its routes cut over to the resolver."]);
            }
        }
    }

    /** @param array<string,mixed>|null $before @param array<string,mixed> $after */
    private function logHistory(string $event, int $assignmentId, User $actor, ?int $subjectId, int $roleId, string $scopeType, ?int $scopeId, ?array $before, array $after, ?string $reason = null): void
    {
        $this->history->log([
            'assignment_id' => $assignmentId,
            'event' => $event,
            'actor_id' => $actor->id(),
            'subject_type' => 'user',
            'subject_id' => $subjectId,
            'role_id' => $roleId,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'before' => $before,
            'after' => $after,
            // The dedicated reason column, distinct from the value inside
            // after_json, so audit queries can read it without JSON extraction
            // (review V9; renew carries no reason and passes null).
            'reason' => $reason,
        ]);
    }

    /** @param array<string,mixed> $detail */
    private function audit(User $actor, string $action, int $targetId, array $detail): void
    {
        $this->modLog->log([
            'actor_id' => $actor->id(),
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $targetId,
            'before' => null,
            'after' => $detail,
        ]);
    }
}
