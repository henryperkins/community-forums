<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use App\Repository\CapabilityRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Security\Cap;
use Tests\Support\TestCase;

/**
 * Inc 6 follow-up: queue discovery for custom deputies. /mod/approvals and
 * /mod/reports row-scoped (and doored) purely via legacy board_moderators,
 * so a custom role holding the queue's action key could never reach its
 * rows under enforce. Under enforce the actor's grants decide. The 2026-07-17
 * audit (N1) then opened the legacy/shadow approvals door to assigned board
 * moderators — scoped, matching the reports queue — while the admin /
 * global-moderator / plain-member personas keep their pre-cutover behavior.
 */
final class AppModQueueDiscoveryTest extends TestCase
{
    private static int $seq = 0;

    /** @param list<string> $capabilityKeys */
    private function makeRoleHolding(array $capabilityKeys): int
    {
        self::$seq++;
        $roleId = (new RoleRepository($this->db))->create([
            'role_key' => 'custom.queue_fixture_' . self::$seq,
            'name' => 'Queue Fixture Role ' . self::$seq,
            'description' => null,
            'created_by' => null,
        ]);
        $ids = (new CapabilityRepository($this->db))->idsByKeys($capabilityKeys);
        (new RoleCapabilityRepository($this->db))->replaceForRole($roleId, array_values($ids));

        return $roleId;
    }

    private function grantOnBoard(int $userId, int $roleId, int $boardId): void
    {
        (new RoleAssignmentRepository($this->db))->create([
            'subject_id' => $userId,
            'role_id' => $roleId,
            'scope_type' => 'board',
            'scope_id' => $boardId,
        ]);
    }

    private function markPending(int $threadId): void
    {
        $this->db->run('UPDATE threads SET is_pending = 1 WHERE id = ?', [$threadId]);
    }

    public function test_legacy_queue_doors_and_rows_per_persona(): void
    {
        $admin = $this->makeAdmin();
        $globalMod = $this->makeUser(['role' => 'moderator']);
        $deputy = $this->makeUser(['username' => 'assignedmod']);
        $member = $this->makeUser(['username' => 'plainmember']);
        $board = $this->makeBoard($this->makeCategory());
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $deputy['id']);
        $pending = $this->makeThread($board, $admin, 'LegacyPendingProbe');
        $this->markPending((int) $pending['thread_id']);

        // Global moderator: approvals page opens but shows no rows (not
        // assigned anywhere); the reports queue 404s.
        $this->actingAs($globalMod);
        $page = $this->get('/mod/approvals');
        $this->assertStatus(200, $page);
        $this->assertDontSeeText($page, 'LegacyPendingProbe');
        $this->assertStatus(404, $this->get('/mod/reports'));

        // Assigned board moderator (role=user): both queues open, scoped to
        // their boards (approvals parity per the 2026-07-17 audit, N1).
        $this->actingAs($deputy);
        $page = $this->get('/mod/approvals');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'LegacyPendingProbe');
        $this->assertStatus(200, $this->get('/mod/reports'));

        // Plain member: both closed.
        $this->actingAs($member);
        $this->assertStatus(404, $this->get('/mod/approvals')); // uniform posture (round-2 audit, ADR 0023)
        $this->assertStatus(404, $this->get('/mod/reports'));

        // Admin: everything, unscoped.
        $this->actingAs($admin);
        $page = $this->get('/mod/approvals');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'LegacyPendingProbe');
        $this->assertStatus(200, $this->get('/mod/reports'));
    }

    /**
     * Audit 2026-07-17 N1: an assigned board moderator (users.role='user') must
     * reach the approvals queue under the default legacy/shadow door — scoped
     * to their boards, exactly like the reports queue — instead of 403ing at a
     * bare site-role probe while the moderation subnav advertises the tab
     * (ADMIN §3.2, §9.1–9.2).
     */
    public function test_shadow_assigned_board_moderator_reaches_scoped_approvals(): void
    {
        $admin = $this->makeAdmin();
        $deputy = $this->makeUser(['username' => 'shadowdeputy']);
        $cat = $this->makeCategory();
        $scoped = $this->makeBoard($cat);
        $foreign = $this->makeBoard($cat);
        (new BoardModeratorRepository($this->db))->assign((int) $scoped['id'], (int) $deputy['id']);

        $inScope = $this->makeThread($scoped, $admin, 'ShadowScopedPending');
        $outScope = $this->makeThread($foreign, $admin, 'ShadowForeignPending');
        $this->markPending((int) $inScope['thread_id']);
        $this->markPending((int) $outScope['thread_id']);

        $this->actingAs($deputy);
        $page = $this->get('/mod/approvals');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'ShadowScopedPending');
        $this->assertDontSeeText($page, 'ShadowForeignPending');

        // Releasing an in-scope hold works end-to-end for the scoped moderator.
        $this->assertRedirectContains(
            $this->post('/mod/approvals/thread/' . (int) $inScope['thread_id'] . '/approve'),
            '/mod/approvals',
        );
        self::assertSame(0, (int) $this->db->fetchValue(
            'SELECT is_pending FROM threads WHERE id = ?',
            [(int) $inScope['thread_id']],
        ));
    }

    public function test_enforce_deputy_sees_and_acts_on_scoped_approvals(): void
    {
        $admin = $this->makeAdmin();
        $deputy = $this->makeUser(['username' => 'queuedeputy']);
        $cat = $this->makeCategory();
        $scoped = $this->makeBoard($cat);
        $foreign = $this->makeBoard($cat);
        $this->grantOnBoard((int) $deputy['id'], $this->makeRoleHolding([Cap::CONTENT_APPROVE]), (int) $scoped['id']);

        $inScope = $this->makeThread($scoped, $admin, 'ScopedPendingTopic');
        $outScope = $this->makeThread($foreign, $admin, 'ForeignPendingTopic');
        $this->markPending((int) $inScope['thread_id']);
        $this->markPending((int) $outScope['thread_id']);

        $this->withCapabilitiesEnforced();
        $this->actingAs($deputy);

        $page = $this->get('/mod/approvals');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'ScopedPendingTopic');
        $this->assertDontSeeText($page, 'ForeignPendingTopic');

        $resp = $this->post('/mod/approvals/thread/' . (int) $inScope['thread_id'] . '/approve');
        $this->assertRedirectContains($resp, '/mod/approvals');
        self::assertSame(0, (int) $this->db->fetchValue(
            'SELECT is_pending FROM threads WHERE id = ?',
            [(int) $inScope['thread_id']],
        ));

        // Out-of-scope action still refused.
        $this->assertStatus(403, $this->post('/mod/approvals/thread/' . (int) $outScope['thread_id'] . '/approve'));
    }

    public function test_enforce_deputy_reaches_scoped_reports(): void
    {
        $admin = $this->makeAdmin();
        $deputy = $this->makeUser(['username' => 'reportdeputy']);
        $reporter = $this->makeUser(['username' => 'reporter1']);
        $cat = $this->makeCategory();
        $scoped = $this->makeBoard($cat);
        $foreign = $this->makeBoard($cat);
        $this->grantOnBoard((int) $deputy['id'], $this->makeRoleHolding([Cap::REPORT_HANDLE]), (int) $scoped['id']);

        $inScope = $this->makeThread($scoped, $admin, 'ScopedReportedTopic');
        $outScope = $this->makeThread($foreign, $admin, 'ForeignReportedTopic');
        $this->actingAs($reporter);
        $this->assertRedirect($this->post('/posts/' . (int) $inScope['post_id'] . '/report', ['reason' => 'in-scope probe']));
        $this->assertRedirect($this->post('/posts/' . (int) $outScope['post_id'] . '/report', ['reason' => 'out-of-scope probe']));

        $this->withCapabilitiesEnforced();
        $this->actingAs($deputy);
        $page = $this->get('/mod/reports');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'ScopedReportedTopic');
        $this->assertDontSeeText($page, 'ForeignReportedTopic');
    }

    public function test_enforce_grants_global_moderators_no_queue_broadening(): void
    {
        $admin = $this->makeAdmin();
        $globalMod = $this->makeUser(['role' => 'moderator']);
        $board = $this->makeBoard($this->makeCategory());
        $pending = $this->makeThread($board, $admin, 'BroadeningProbeTopic');
        $this->markPending((int) $pending['thread_id']);

        $this->withCapabilitiesEnforced();
        $this->actingAs($globalMod);

        // Same as legacy: page opens (site view_pending probe), zero rows.
        $page = $this->get('/mod/approvals');
        $this->assertStatus(200, $page);
        $this->assertDontSeeText($page, 'BroadeningProbeTopic');
        $this->assertStatus(404, $this->get('/mod/reports'));
    }
}
