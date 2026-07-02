<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Security\BoardPolicy;
use App\Security\CapabilityResolver;
use App\Security\WriteGate;
use App\Service\LegacyAuthorityProjection;
use App\Service\PermissionSimulatorService;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08): simulation uses the real resolver and redacts targets
 * the viewer cannot read.
 */
final class PermissionSimulatorTest extends TestCase
{
    private function service(): PermissionSimulatorService
    {
        $resolver = new CapabilityResolver(
            new RoleCapabilityRepository($this->db),
            new RoleAssignmentRepository($this->db),
            new LegacyAuthorityProjection(new BoardModeratorRepository($this->db)),
            new ProtectedOwnerRepository($this->db),
            $this->boards(),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
            new WriteGate(),
        );

        return new PermissionSimulatorService(
            $resolver,
            $this->users(),
            $this->boards(),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
        );
    }

    public function test_simulates_allow_and_deny_with_decisive_reason(): void
    {
        $board = $this->makeBoard($this->makeCategory('Sim'));
        $mod = $this->makeUser();
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);
        $viewer = User::fromRow($this->makeAdmin());
        $svc = $this->service();

        $r = $svc->simulate($viewer, (string) $mod['username'], 'core.thread.lock', (int) $board['id'], null);
        self::assertNull($r['error']);
        self::assertTrue($r['decision']->allowed);
        self::assertSame('grant', $r['decision']->source);
        self::assertStringContainsString((string) $board['name'], (string) $r['target_label']);

        $r = $svc->simulate($viewer, 'guest', 'core.thread.lock', (int) $board['id'], null);
        self::assertFalse($r['decision']->allowed);
        self::assertSame('guest', $r['actor_label']);
    }

    public function test_unknown_actor_reports_an_error_not_an_exception(): void
    {
        $viewer = User::fromRow($this->makeAdmin());

        $r = $this->service()->simulate($viewer, 'nobody-here', 'core.thread.lock', null, null);

        self::assertNotNull($r['error']);
        self::assertNull($r['decision']);
    }

    public function test_at_time_simulation_respects_expiry(): void
    {
        $board = $this->makeBoard($this->makeCategory('SimT'));
        $user = $this->makeUser();
        $roles = new RoleRepository($this->db);
        (new RoleAssignmentRepository($this->db))->create([
            'subject_id' => (int) $user['id'],
            'role_id' => (int) $roles->findByKey('system.moderator')['id'],
            'scope_type' => 'board',
            'scope_id' => (int) $board['id'],
            'ends_at' => '2026-08-01 00:00:00',
        ]);
        $viewer = User::fromRow($this->makeAdmin());
        $svc = $this->service();

        $inside = $svc->simulate($viewer, (string) $user['username'], 'core.thread.lock', (int) $board['id'], '2026-07-15 12:00');
        self::assertTrue($inside['decision']->allowed);
        $after = $svc->simulate($viewer, (string) $user['username'], 'core.thread.lock', (int) $board['id'], '2026-09-01 12:00');
        self::assertFalse($after['decision']->allowed);
        $bad = $svc->simulate($viewer, (string) $user['username'], 'core.thread.lock', (int) $board['id'], 'not-a-time');
        self::assertNotNull($bad['error']);
    }

    public function test_target_label_is_redacted_for_viewers_without_read_access(): void
    {
        $board = $this->makeBoard($this->makeCategory('SimP'), ['visibility' => 'private', 'name' => 'Secret Ops']);
        $svc = $this->service();

        $nonAdminViewer = User::fromRow($this->makeUser());
        $r = $svc->simulate($nonAdminViewer, 'guest', 'core.board.read', (int) $board['id'], null);
        self::assertSame('Board #' . (int) $board['id'] . ' (restricted)', $r['target_label']);
        self::assertStringNotContainsString('Secret Ops', (string) $r['target_label']);

        $adminViewer = User::fromRow($this->makeAdmin());
        $r = $svc->simulate($adminViewer, 'guest', 'core.board.read', (int) $board['id'], null);
        self::assertStringContainsString('Secret Ops', (string) $r['target_label']);
    }
}
