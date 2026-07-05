<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CapabilityRepository;
use App\Repository\CategoryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\CapabilityResolver;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\LegacyAuthorityProjection;
use App\Service\RoleAssignmentService;
use Tests\Support\TestCase;

/**
 * Increment 6 (P5-09): scoped-assignment lifecycle for custom roles. Grants and
 * renewals reauth (high-impact / re-broadening); revokes stay fast (narrowing
 * only). The grantor ceiling (TM-PE-02) is the anti-privilege-escalation guard:
 * a grantor can only mint an assignment whose role's capabilities they
 * themselves hold at the target scope, so a board-scoped deputy is
 * mathematically unable to mint SITE-scope authority.
 */
final class RoleAssignmentServiceTest extends TestCase
{
    private static int $roleSeq = 0;

    private function service(): RoleAssignmentService
    {
        return new RoleAssignmentService(
            $this->db,
            new RoleRepository($this->db),
            new RoleCapabilityRepository($this->db),
            new RoleAssignmentRepository($this->db),
            new RoleAssignmentHistoryRepository($this->db),
            new UserRepository($this->db),
            new BoardRepository($this->db),
            new CategoryRepository($this->db),
            $this->resolver(),
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            new ModerationLogRepository($this->db),
        );
    }

    /** Mirrors CapabilityResolverTest's builder (Task 1). */
    private function resolver(): CapabilityResolver
    {
        return new CapabilityResolver(
            new RoleCapabilityRepository($this->db),
            new RoleAssignmentRepository($this->db),
            new LegacyAuthorityProjection(new BoardModeratorRepository($this->db)),
            new ProtectedOwnerRepository($this->db),
            new BoardRepository($this->db),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
            new WriteGate(),
        );
    }

    /**
     * Builds a bare custom role directly via the repositories (bypassing
     * RoleService, mirroring RoleServiceTest's fixture shape) so these tests
     * stay focused on the assignment lifecycle rather than role definition.
     *
     * @param list<string> $capabilityKeys
     */
    private function makeRole(array $capabilityKeys): int
    {
        self::$roleSeq++;
        $roles = new RoleRepository($this->db);
        $roleId = $roles->create([
            'role_key' => 'custom.assignment_fixture_' . self::$roleSeq,
            'name' => 'Assignment Fixture Role ' . self::$roleSeq,
            'description' => null,
            'created_by' => null,
        ]);
        $ids = (new CapabilityRepository($this->db))->idsByKeys($capabilityKeys);
        (new RoleCapabilityRepository($this->db))->replaceForRole($roleId, array_values($ids));

        return $roleId;
    }

    public function test_grant_creates_row_history_and_audit(): void
    {
        $admin = $this->userEntity($this->makeAdmin());
        $subject = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $roleId = $this->makeRole(['core.thread.lock']);

        $id = $this->service()->grant($admin, 'password123', $roleId, $subject['username'], 'board', (int) $board['id'], null, null, 'pilot');

        $row = (new RoleAssignmentRepository($this->db))->findForUpdate($id);
        self::assertSame('board', $row['scope_type']);
        self::assertSame((int) $admin->id(), (int) $row['grantor_id']);
        self::assertSame((int) $subject['id'], (int) $row['subject_id']);

        $history = (new RoleAssignmentHistoryRepository($this->db))->forRole($roleId);
        self::assertSame('grant', $history[0]['event']);
        self::assertSame((int) $subject['id'], (int) $history[0]['subject_id']);

        $audit = $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'assign_role' AND target_id = ?",
            [(int) $subject['id']],
        );
        self::assertSame(1, (int) $audit);
    }

    public function test_grant_requires_reauth_scope_id_and_custom_role(): void
    {
        $admin = $this->userEntity($this->makeAdmin());
        $subject = $this->makeUser();
        $roleId = $this->makeRole(['core.thread.lock']);

        try {
            $this->service()->grant($admin, 'wrong', $roleId, $subject['username'], 'site', null, null, null, null);
            self::fail('expected reauth failure');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }

        try {
            $this->service()->grant($admin, 'password123', $roleId, $subject['username'], 'board', null, null, null, null);
            self::fail('expected missing scope_id failure');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('scope_id', $e->errors);
        }

        $systemRoleId = (int) $this->db->fetchValue("SELECT id FROM roles WHERE role_key = 'system.moderator'");
        try {
            $this->service()->grant($admin, 'password123', $systemRoleId, $subject['username'], 'site', null, null, null, null);
            self::fail('expected non-custom-role refusal');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('role', $e->errors);
        }
    }

    public function test_grant_refuses_custom_role_with_unenforced_capability_even_if_row_predates_the_clamp(): void
    {
        $admin = $this->userEntity($this->makeAdmin());
        $subject = $this->makeUser();
        $roles = new RoleRepository($this->db);
        $roleId = $roles->create([
            'role_key' => 'custom.legacy_suspender',
            'name' => 'Legacy Suspender',
            'description' => null,
            'created_by' => $admin->id(),
        ]);
        // Bypasses RoleService deliberately: models a custom role row that was
        // created BEFORE Task 9's EnforcedCapabilities honesty clamp existed.
        $ids = (new CapabilityRepository($this->db))->idsByKeys(['core.user.suspend']);
        (new RoleCapabilityRepository($this->db))->replaceForRole($roleId, array_values($ids));

        try {
            $this->service()->grant($admin, 'password123', $roleId, $subject['username'], 'site', null, null, null, null);
            self::fail('pre-existing custom roles with unenforced keys must not be assignable');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('capabilities', $e->errors);
        }
    }

    public function test_grantor_ceiling_blocks_and_audits_out_of_scope_grants_tm_pe_02(): void
    {
        $admin = $this->userEntity($this->makeAdmin());
        $deputy = $this->makeUser();          // board-scoped grantor
        $pawn = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $roleId = $this->makeRole(['core.thread.lock']);
        (new RoleAssignmentRepository($this->db))->create([
            'subject_id' => (int) $deputy['id'], 'role_id' => $roleId,
            'scope_type' => 'board', 'scope_id' => (int) $board['id'],
        ]);
        $deputyEntity = $this->userEntity($deputy);

        // Board-scoped deputy CANNOT mint SITE scope (E1 fail-closed does the math).
        try {
            $this->service()->grant($deputyEntity, 'password123', $roleId, $pawn['username'], 'site', null, null, null, null);
            self::fail('site-scope grant must refuse');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('scope_type', $e->errors);
        }
        $audit = $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'role_assignment_denied'");
        self::assertSame(1, (int) $audit);

        // Same-or-narrower scope IS grantable by the deputy at their board.
        $ok = $this->service()->grant($deputyEntity, 'password123', $roleId, $pawn['username'], 'board', (int) $board['id'], null, null, null);
        self::assertGreaterThan(0, $ok);
    }

    public function test_grant_refuses_a_duplicate_active_assignment_for_the_same_scope_s3(): void
    {
        $admin = $this->userEntity($this->makeAdmin());
        $subject = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $roleId = $this->makeRole(['core.thread.lock']);

        $first = $this->service()->grant($admin, 'password123', $roleId, $subject['username'], 'board', (int) $board['id'], null, null, null);
        self::assertGreaterThan(0, $first);

        // A second identical (subject, role, scope) grant — e.g. a double-clicked
        // form or a retried POST — must refuse, not mint a twin the resolver's
        // allow-if-any-grant union would keep honoring after the first is revoked
        // (review S3). Mirrors AdminService::addMember's already-a-member guard.
        try {
            $this->service()->grant($admin, 'password123', $roleId, $subject['username'], 'board', (int) $board['id'], null, null, null);
            self::fail('a duplicate active assignment must refuse');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('username', $e->errors);
        }

        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM role_assignments WHERE subject_id = ? AND role_id = ? AND scope_type = 'board' AND scope_id = ?",
            [(int) $subject['id'], $roleId, (int) $board['id']],
        ));

        // Revoking the first frees the slot: a fresh grant then succeeds, and a
        // different scope was never blocked in the first place.
        $this->service()->revoke($admin, $first, null);
        $second = $this->service()->grant($admin, 'password123', $roleId, $subject['username'], 'board', (int) $board['id'], null, null, null);
        self::assertGreaterThan(0, $second);
    }

    public function test_renew_refuses_expiry_before_a_scheduled_assignments_start_s2(): void
    {
        $admin = $this->userEntity($this->makeAdmin());
        $subject = $this->makeUser();
        $roleId = $this->makeRole(['core.thread.lock']);
        // A scheduled assignment: starts well in the future.
        $startsAt = gmdate('Y-m-d H:i:s', time() + 30 * 86400);
        $id = $this->service()->grant($admin, 'password123', $roleId, $subject['username'], 'site', null, $startsAt, null, null);

        // Renewing to an expiry BEFORE the row's own start is a window grant()
        // itself rejects ("expiry must be after the start"); renew must reject it
        // too rather than mint a can-never-activate window with a clean audit
        // trail (review S2). The expiry is still in the future, so it clears the
        // separate future-check — only the cross-check against starts_at catches it.
        $endsBeforeStart = gmdate('Y-m-d H:i:s', time() + 10 * 86400);
        try {
            $this->service()->renew($admin, 'password123', $id, $endsBeforeStart);
            self::fail('renew must refuse an expiry before the start');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('ends_at', $e->errors);
        }
    }

    public function test_grant_and_revoke_record_the_reason_in_the_history_column_v9(): void
    {
        $admin = $this->userEntity($this->makeAdmin());
        $subject = $this->makeUser();
        $roleId = $this->makeRole(['core.thread.lock']);
        $id = $this->service()->grant($admin, 'password123', $roleId, $subject['username'], 'site', null, null, null, 'incident 42 pilot');

        // The dedicated role_assignment_history.reason column (which pre-existing
        // role_edit events populate) must carry the admin-entered reason, not sit
        // NULL with the value buried only inside after_json (review V9).
        self::assertSame('incident 42 pilot', $this->db->fetchValue(
            "SELECT reason FROM role_assignment_history WHERE assignment_id = ? AND event = 'grant'",
            [$id],
        ));

        $this->service()->revoke($admin, $id, 'rotated out');
        self::assertSame('rotated out', $this->db->fetchValue(
            "SELECT reason FROM role_assignment_history WHERE assignment_id = ? AND event = 'revoke'",
            [$id],
        ));
    }

    public function test_revoke_is_fast_and_renew_reauths_and_row_locks_are_deterministic(): void
    {
        $admin = $this->userEntity($this->makeAdmin());
        $subject = $this->makeUser();
        $roleId = $this->makeRole(['core.thread.lock']);
        $id = $this->service()->grant($admin, 'password123', $roleId, $subject['username'], 'site', null, null, gmdate('Y-m-d H:i:s', time() + 3600), null);

        $this->service()->revoke($admin, $id, 'over'); // no password argument — fast path
        $row = (new RoleAssignmentRepository($this->db))->findForUpdate($id);
        self::assertNotNull($row['revoked_at']);

        try {
            $this->service()->renew($admin, 'password123', $id, gmdate('Y-m-d H:i:s', time() + 7200));
            self::fail('renew of a revoked assignment must refuse');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('assignment', $e->errors); // revoked rows refuse renew
        }

        try {
            $this->service()->revoke($admin, $id, null);
            self::fail('double-revoke must refuse');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('assignment', $e->errors); // double-revoke refuses deterministically
        }
    }
}
