<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Config;
use App\Repository\CapabilityRepository;
use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
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
