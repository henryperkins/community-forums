<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Config;
use App\Repository\BoardModeratorRepository;
use App\Repository\CapabilityRepository;
use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Repository\SettingRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\RoleService;
use Tests\Support\TestCase;

final class AppEnforcementCutoverTest extends TestCase
{
    public function test_enforce_mode_keeps_admin_moderation_working_end_to_end(): void
    {
        $admin = $this->makeAdmin();
        $board = $this->makeBoard($this->makeCategory());
        $author = $this->makeUser();
        $t = $this->makeThread($board, $author);
        $this->withCapabilitiesEnforced();

        $this->actingAs($admin);
        $response = $this->post('/mod/t/' . $t['thread_id'] . '/lock');
        $this->assertRedirect($response); // admin still locks under enforcement
    }

    public function test_enforce_mode_denies_plain_member_moderation(): void
    {
        // A live admin must already exist or every request (including this
        // POST) is bounced to /setup by App::process()'s first-run gate
        // (SetupService::isInitialized() === adminCount() > 0), independent of
        // the moderation check this test targets. The sibling test above
        // satisfies this incidentally via makeAdmin(); this one must too.
        $this->makeAdmin();
        $member = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $author = $this->makeUser();
        $t = $this->makeThread($board, $author);
        $this->withCapabilitiesEnforced();

        $this->actingAs($member);
        $response = $this->post('/mod/t/' . $t['thread_id'] . '/lock');
        $this->assertStatus(403, $response);
    }

    public function test_lock_only_custom_role_can_lock_but_not_delete_tm_granularity(): void
    {
        $admin = $this->makeAdmin();
        $deputy = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $author = $this->makeUser();
        $t = $this->makeThread($board, $author);

        // Custom role holding ONLY core.thread.lock, assigned at this board.
        $roleId = $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.thread.lock'], (int) $board['id']);
        self::assertGreaterThan(0, $roleId);
        $this->withCapabilitiesEnforced();

        $this->actingAs($deputy);
        $this->assertRedirect($this->post('/mod/t/' . $t['thread_id'] . '/lock'));  // granted key works
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? ORDER BY id LIMIT 1', [$t['thread_id']]);
        $this->assertStatus(403, $this->post('/posts/' . $postId . '/delete'));      // ungranted key refuses
    }

    /**
     * Phase 5 Inc 6 Task 4b: the thread-view moderation toolbar used to gate every
     * button behind one coarse can_moderate_board flag (core.post.delete_any), so a
     * custom role holding only core.thread.lock was authorized to POST
     * /mod/t/{id}/lock (proven by the sibling test above) but the Lock control never
     * rendered — an invisible-but-authorized control is unusable in this
     * server-rendered, no-API-client app. Each control must render off its own
     * capability key instead.
     */
    public function test_lock_only_deputy_sees_lock_control_but_not_delete_or_pin_in_thread_ui(): void
    {
        $admin = $this->makeAdmin();
        $deputy = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $author = $this->makeUser();
        $t = $this->makeThread($board, $author);
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? ORDER BY id LIMIT 1', [$t['thread_id']]);

        // Custom role holding ONLY core.thread.lock, assigned at this board.
        $roleId = $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.thread.lock'], (int) $board['id']);
        self::assertGreaterThan(0, $roleId);
        $this->withCapabilitiesEnforced();

        $this->actingAs($deputy);
        $page = $this->get('/t/' . $t['thread_id'] . '-' . $t['slug']);

        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'action="/mod/t/' . $t['thread_id'] . '/lock"');
        $this->assertDontSeeText($page, 'action="/mod/t/' . $t['thread_id'] . '/pin"');
        $this->assertDontSeeText($page, 'action="/posts/' . $postId . '/delete"');
    }

    /**
     * No-regression pin (Task 4b requirement #4): a legacy admin must still see
     * every control exactly as before the per-button split. Under legacy/shadow
     * mode, AuthorityGate::allows() ignores the capability key entirely (see
     * src/Security/AuthorityGate.php) — every new per-action flag collapses back
     * to the same coarse boolean an admin has always evaluated true for.
     * Complements AppThreadUxAuditTest::test_board_moderator_sees_pin_lock_and_remove_controls,
     * which exercises the identical canModerate() OR-branch via an assigned
     * board_moderators row instead of the global admin role.
     */
    public function test_admin_thread_page_still_shows_pin_lock_and_delete_controls(): void
    {
        $admin = $this->makeAdmin();
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $t = $this->makeThread($board, $author);
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? ORDER BY id LIMIT 1', [$t['thread_id']]);

        $this->actingAs($admin);
        $page = $this->get('/t/' . $t['thread_id'] . '-' . $t['slug']);

        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'action="/mod/t/' . $t['thread_id'] . '/pin"');
        $this->assertSeeText($page, 'action="/mod/t/' . $t['thread_id'] . '/lock"');
        $this->assertSeeText($page, 'action="/posts/' . $postId . '/delete"');
    }

    /**
     * Phase 5 Inc 6 Task 5 — parity pin (spec §8 parity evidence). A plain
     * member is refused by the post_min_role floor identically whether the
     * legacy BoardPolicy::canPost() decides directly or AuthorityGate decides
     * via the resolver under enforce. The legacy status is measured first (not
     * assumed) so this assertion is pinned to a real, observed value.
     */
    public function test_posting_floor_enforced_via_resolver(): void
    {
        // A live admin must exist or every request (including this POST) is
        // bounced to /setup by App::process()'s first-run gate, independent of
        // the floor check this test targets (see sibling tests above).
        $this->makeAdmin();
        $member = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['post_min_role' => 'moderator']);
        $this->actingAs($member);

        // Legacy (unenforced) refusal — observed, not assumed.
        $legacy = $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'Nope', 'body' => 'Denied by floor.']);
        self::assertSame(403, $legacy->status(), 'Observed legacy refusal status — encode this value below if it ever changes.');

        // Same request, same session, now under CAPABILITIES_MODE=enforce.
        $this->withCapabilitiesEnforced();
        $response = $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'Nope', 'body' => 'Denied by floor.']);
        self::assertSame(403, $response->status()); // pin: enforced status === legacy status
    }

    /**
     * Phase 5 Inc 6 Task 6 — parity pin (spec §8 parity evidence), dual-path.
     * The route is POST /posts/{id}/accept (SolvedController::accept), gated by
     * the `community` flag (grep confirms no separate `mark_solved` flag exists
     * — FeatureFlags.php's `community` entry's own comment lists "solved").
     * Accepting the OPENING post always throws a ValidationException regardless
     * of authorization (SolvedAnswerService::mark, "opening post cannot be the
     * accepted answer") which the controller also turns into a redirect, so a
     * naive test using the thread's first post would "pass" for the wrong
     * reason; a genuine reply post is used instead (mirrors
     * AppBadgeSolvedTest::test_accept_answer_awards_bonus_badge_and_notifies).
     * Covers BOTH dual-path halves: the OP accepting on their own thread
     * (owner branch) and a real board moderator accepting on a thread they
     * don't own (moderator branch); a stranger is refused either way. Passes
     * before AND after the SolvedAnswerService::authorize() swap by
     * construction — the swap must not move this needle.
     */
    public function test_dual_path_solved_still_works_for_op_and_board_mod_under_enforce(): void
    {
        $this->makeAdmin(); // satisfy the first-run setup gate
        $board = $this->makeBoard($this->makeCategory());
        $op = $this->makeUser();
        $mod = $this->makeUser();
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);
        $bystander = $this->makeUser();
        $answerer = $this->makeUser();

        $opThread = $this->makeThread($board, $op);
        $opReplyId = $this->posting()->reply($this->userEntity($answerer), $opThread['thread_id'], ['body' => 'An answer for the OP.']);
        $strangersThread = $this->makeThread($board, $this->makeUser());
        $modReplyId = $this->posting()->reply($this->userEntity($answerer), $strangersThread['thread_id'], ['body' => 'An answer for the mod.']);
        $this->withCapabilitiesEnforced(['community' => true]);

        // OP path allowed (dual-path owner branch).
        $this->actingAs($op);
        $this->assertRedirect($this->post('/posts/' . $opReplyId . '/accept'));

        // Board moderator path allowed on a thread they do not own (dual-path moderator branch).
        $this->actingAs($mod);
        $this->assertRedirect($this->post('/posts/' . $modReplyId . '/accept'));

        // A bystander (neither OP nor moderator) is refused on either thread.
        $this->actingAs($bystander);
        $this->assertStatus(403, $this->post('/posts/' . $opReplyId . '/accept'));
    }

    /**
     * Phase 5 Inc 6 Task 6 — attempted discriminating test for the dual-path
     * swap. See task-6-report.md "Discriminating test" section for the full
     * empirical finding: `CapabilityRules::DUAL_PATH_BOARD_AUTHORITY` (an
     * explicit allowlist restricting the non-owner/"board-wide" half of a
     * dual-path decision to `system.moderator`/`system.admin` ROLE-kind grants
     * only — never a custom role, even one holding the exact matching
     * capability key) means a deputy's board-scoped grant of ONLY
     * `core.thread.mark_solved` can authorize the OWNER path (their own
     * threads) but never the moderator path (someone else's thread) — by the
     * same non-broadening design captured in
     * docs/phase5/capability-taxonomy.md §6 ("board-wide use comes only
     * through a board-scoped moderator assignment"). This assertion is
     * therefore a NEGATIVE pin, not a red→green proof: 403 before the swap
     * (legacy `authorize()` has no such grant concept) AND 403 after (the
     * resolver enforces the identical non-broadening rule). Confirmed by
     * running this test against both the pre-swap and post-swap tree.
     */
    public function test_deputy_with_mark_solved_only_grant_cannot_accept_strangers_thread_under_enforce(): void
    {
        $admin = $this->makeAdmin();
        $deputy = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $stranger = $this->makeUser();
        $answerer = $this->makeUser();
        $t = $this->makeThread($board, $stranger);
        $replyId = $this->posting()->reply($this->userEntity($answerer), $t['thread_id'], ['body' => 'An answer.']);

        $roleId = $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.thread.mark_solved'], (int) $board['id']);
        self::assertGreaterThan(0, $roleId);
        $this->withCapabilitiesEnforced(['community' => true]);

        $this->actingAs($deputy);
        $this->assertStatus(403, $this->post('/posts/' . $replyId . '/accept'));
    }

    /**
     * Phase 5 Inc 6 Task 7 — ADR-0016 tightening (capability-taxonomy.md §7
     * quirk #5). Legacy `ApprovalController::queue()` gates on bare
     * `isModerator()` with no `WriteGate`, so a suspended global moderator can
     * still VIEW the pending queue in production even though every pending
     * ACTION (approve/reject) already runs through `canModerate()` and is
     * state-blocked. Under `CAPABILITIES_MODE=enforce`, `AuthorityGate`
     * consults the resolver, and `CapabilityRules::decide()` applies "state
     * beats role" uniformly (denies any non-state-exempt capability whenever
     * `!actorCanWrite`) — so this pin FAILS on the pre-swap tree (legacy still
     * renders the queue for a suspended mod) and PASSES once
     * `ApprovalController::queue()` routes through the gate. Owner decision
     * recorded in capability-taxonomy.md §7 #5: accept the (safer) tightening
     * rather than state-exempt the view paths.
     */
    public function test_suspended_global_moderator_loses_pending_views_under_enforce_adr_0016(): void
    {
        // A live admin must already exist or every request (including this
        // GET) is bounced to /setup by App::process()'s first-run gate,
        // independent of the pending-view check this test targets (see the
        // sibling tests above).
        $this->makeAdmin();
        $mod = $this->makeUser(['role' => 'moderator', 'status' => 'suspended',
            'suspended_until' => gmdate('Y-m-d H:i:s', time() + 86400)]);
        $this->withCapabilitiesEnforced(['anti_abuse' => true]);

        $this->actingAs($mod);
        $this->assertStatus(403, $this->get('/mod/approvals')); // approved tightening: state beats role
    }

    /**
     * Shadow-mode sibling: outside `CAPABILITIES_MODE=enforce` the legacy
     * closure alone decides the outcome (`AuthorityGate::allows()` shadow
     * branch) — the resolver only ever runs for mismatch telemetry — so the
     * suspended-global-moderator quirk documented in capability-taxonomy.md §7
     * #5 is preserved exactly as it behaves in production today.
     */
    public function test_suspended_global_moderator_keeps_pending_view_in_shadow_mode(): void
    {
        $this->makeAdmin(); // satisfy the first-run setup gate
        $mod = $this->makeUser(['role' => 'moderator', 'status' => 'suspended',
            'suspended_until' => gmdate('Y-m-d H:i:s', time() + 86400)]);
        (new SettingRepository($this->db))->set('features', ['capabilities' => true, 'anti_abuse' => true]);
        // NOTE: no app rebuild — CAPABILITIES_MODE stays shadow (the default config).

        $this->actingAs($mod);
        $response = $this->get('/mod/approvals');
        self::assertNotSame(403, $response->status()); // legacy quirk preserved outside enforce
    }

    /**
     * Phase 5 Inc 6 Task 8 — board-roster POST command endpoints cut over to
     * service-layer capability authorization. Before this task both actions
     * are gated only by AdminController::requireAdmin(), so a deputy holding
     * ONLY core.board.assign_moderators dies at the controller with 403
     * before AdminService is ever reached — a genuine RED. After the swap the
     * controller merely requires a logged-in user and AdminService itself
     * asserts the capability (state-first; legacy closure stays admin-only,
     * so legacy/shadow behavior is unchanged).
     */
    public function test_board_roster_assign_moderators_key_can_assign_board_moderator_under_enforce(): void
    {
        $admin = $this->makeAdmin();
        $deputy = $this->makeUser();
        $target = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.board.assign_moderators'], (int) $board['id']);
        $this->withCapabilitiesEnforced();

        $this->actingAs($deputy);
        $this->assertRedirect($this->post('/admin/boards/' . $board['id'] . '/moderators', ['username' => $target['username']]));
        self::assertTrue((new \App\Repository\BoardModeratorRepository($this->db))->isModerator((int) $board['id'], (int) $target['id']));
    }

    /**
     * Sibling of the above for core.board.manage_members on a private board
     * (the capability that matters most there — public boards need no
     * membership row for read access).
     */
    public function test_board_roster_manage_members_key_can_add_member_under_enforce(): void
    {
        $admin = $this->makeAdmin();
        $deputy = $this->makeUser();
        $target = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.board.manage_members'], (int) $board['id']);
        $this->withCapabilitiesEnforced();

        $this->actingAs($deputy);
        $this->assertRedirect($this->post('/admin/boards/' . $board['id'] . '/members', ['username' => $target['username']]));
        self::assertTrue((new \App\Repository\BoardMemberRepository($this->db))->isMember((int) $board['id'], (int) $target['id']));
    }

    /**
     * Negative control: a custom role holding an unrelated capability
     * (core.thread.lock) assigned at the same board must NOT satisfy
     * core.board.assign_moderators. 403 both before this task's swap (dies at
     * requireAdmin()) and after (the resolver denies — no qualifying grant).
     */
    public function test_unrelated_board_role_cannot_change_rosters_under_enforce(): void
    {
        $admin = $this->makeAdmin();
        $deputy = $this->makeUser();
        $target = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.thread.lock'], (int) $board['id']);
        $this->withCapabilitiesEnforced();

        $this->actingAs($deputy);
        $this->assertStatus(403, $this->post('/admin/boards/' . $board['id'] . '/moderators', ['username' => $target['username']]));
    }

    /**
     * Phase 5 Inc 6 Task 8 follow-up (info-disclosure). Switching the four
     * roster POST actions from requireAdmin() to requireUser() left their
     * ValidationException branch re-rendering the admin-only admin/board_edit
     * template. A capability-holding NON-ADMIN deputy who trips a validation
     * error (here: an unknown username) must NOT be shown that admin surface —
     * they are redirected off the admin console instead, with the error in the
     * flash. Marker: templates/admin/board_edit.php's "Admin mode" pill.
     */
    public function test_non_admin_roster_deputy_validation_error_redirects_off_admin_console_no_disclosure(): void
    {
        $admin = $this->makeAdmin();
        $deputy = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory()); // public → deputy can read it
        $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.board.assign_moderators'], (int) $board['id']);
        $this->withCapabilitiesEnforced();

        $this->actingAs($deputy);
        $response = $this->post('/admin/boards/' . $board['id'] . '/moderators', ['username' => 'ghost-nobody']);

        // A redirect, NOT a 200 that renders the admin board-edit console.
        $this->assertRedirect($response);
        $this->assertDontSeeText($response, 'Admin mode');
        $this->assertDontSeeText($response, 'Assignment mode');
        self::assertStringStartsNotWith('/admin', (string) $response->getHeader('location'));
        // Landed on the board's own page (public → readable), carrying the error in the flash.
        self::assertSame('/c/' . $board['slug'], $response->getHeader('location'));
    }

    /**
     * Follow-up (broken-flow): on SUCCESS the roster actions used to redirect
     * every actor to /admin/boards/{id}/edit — still requireAdmin() — so a
     * successful non-admin deputy's browser followed straight into a 403. The
     * deputy must be sent somewhere they can actually see instead.
     */
    public function test_non_admin_roster_deputy_success_does_not_redirect_into_admin_path(): void
    {
        $admin = $this->makeAdmin();
        $deputy = $this->makeUser();
        $target = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.board.assign_moderators'], (int) $board['id']);
        $this->withCapabilitiesEnforced();

        $this->actingAs($deputy);
        $response = $this->post('/admin/boards/' . $board['id'] . '/moderators', ['username' => $target['username']]);

        $this->assertRedirect($response);
        self::assertStringStartsNotWith('/admin', (string) $response->getHeader('location'));
        self::assertSame('/c/' . $board['slug'], $response->getHeader('location'));
        // The command still took effect.
        self::assertTrue((new \App\Repository\BoardModeratorRepository($this->db))->isModerator((int) $board['id'], (int) $target['id']));
    }

    /**
     * Follow-up (fallback): a manage_members deputy on a PRIVATE board is not
     * themselves a board member, so /c/{slug} would 404 for them. They must
     * fall back to '/', which any signed-in user can see — never the admin
     * console, never a 404 dead-end.
     */
    public function test_non_admin_manage_members_deputy_on_private_board_exits_to_home(): void
    {
        $admin = $this->makeAdmin();
        $deputy = $this->makeUser();
        $target = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.board.manage_members'], (int) $board['id']);
        $this->withCapabilitiesEnforced();

        $this->actingAs($deputy);
        $response = $this->post('/admin/boards/' . $board['id'] . '/members', ['username' => $target['username']]);

        $this->assertRedirect($response);
        self::assertSame('/', $response->getHeader('location'));
        self::assertTrue((new \App\Repository\BoardMemberRepository($this->db))->isMember((int) $board['id'], (int) $target['id']));
    }

    /**
     * Phase 5 Inc 6 Task 14 — acceptance pin (TM-PE-08): editing a role's
     * capability set propagates per-key to every assignee on their very next
     * request, with no cache to invalidate and no re-login required. Each
     * App::handle() call builds a fresh per-request container, so the
     * resolver it hands to AuthorityGate reads role_capabilities live — the
     * admin's update (which also bumps roles.version) is visible to the
     * deputy's next dispatch through the same already-built $this->app.
     * Deliberately per-KEY, not per-role: pin is removed from the role while
     * lock is kept, and only pin is denied afterward — proves the resolver
     * re-evaluates the capability set rather than caching a stale "deputy can
     * moderate this board" boolean.
     */
    public function test_capability_removed_from_role_denies_all_assignees_tm_pe_08(): void
    {
        $admin = $this->makeAdmin();
        $deputy = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $author = $this->makeUser();
        $t = $this->makeThread($board, $author);
        $roleId = $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.thread.pin', 'core.thread.lock'], (int) $board['id']);
        $this->withCapabilitiesEnforced();

        $this->actingAs($deputy);
        $this->assertRedirect($this->post('/mod/t/' . $t['thread_id'] . '/pin'));    // holds the key (pin toggles, no side effects on posting)

        // Admin edits the role: pin removed, lock kept.
        $this->actingAs($admin);
        $this->assertRedirect($this->post('/admin/roles/' . $roleId, [
            'name' => 'Deputy', 'description' => '', 'capabilities' => ['core.thread.lock'],
            'current_password' => 'password123',
        ]));

        // Next direct request: every assignee is denied the removed key.
        $this->actingAs($deputy);
        $this->assertStatus(403, $this->post('/mod/t/' . $t['thread_id'] . '/pin'));
        // The kept key still works — propagation is per-key, not per-role.
        $this->assertRedirect($this->post('/mod/t/' . $t['thread_id'] . '/lock'));
    }

    /** @param array<string,mixed> $adminRow @param array<string,mixed> $subjectRow @param list<string> $keys */
    private function makeCustomRoleWithAssignment(array $adminRow, array $subjectRow, array $keys, int $boardId): int
    {
        $service = new RoleService(
            $this->db,
            new RoleRepository($this->db),
            new RoleCapabilityRepository($this->db),
            new CapabilityRepository($this->db),
            new RoleAssignmentRepository($this->db),
            new RoleAssignmentHistoryRepository($this->db),
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
        );
        $roleId = $service->create($this->userEntity($adminRow), 'password123', 'Deputy ' . bin2hex(random_bytes(3)), null, $keys);
        (new RoleAssignmentRepository($this->db))->create([
            'subject_id' => (int) $subjectRow['id'],
            'role_id' => $roleId,
            'scope_type' => 'board',
            'scope_id' => $boardId,
            'grantor_id' => (int) $adminRow['id'],
        ]);
        return $roleId;
    }
}
