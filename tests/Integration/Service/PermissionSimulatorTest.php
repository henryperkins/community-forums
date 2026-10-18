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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08): simulation uses the real resolver and redacts targets
 * the viewer cannot read.
 */
final class PermissionSimulatorTest extends TestCase
{
    private function service(): PermissionSimulatorService
    {
        $resolver = $this->capabilityResolver();

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

    #[DataProvider('invalidSimulationTimes')]
    public function test_invalid_simulation_time_reports_an_error_without_a_decision(string $at): void
    {
        $viewer = User::fromRow($this->makeAdmin());

        $result = $this->service()->simulate($viewer, 'guest', 'core.board.read', null, $at);

        self::assertNotNull($result['error']);
        self::assertNull($result['decision']);
    }

    public static function invalidSimulationTimes(): array
    {
        return [
            'February overflow' => ['2026-02-30 12:00'],
            'non-leap year' => ['2026-02-29T12:00'],
            'April overflow' => ['2026-04-31 12:00'],
            'zero day' => ['2026-03-00 12:00'],
            'month overflow' => ['2026-13-01T12:00'],
            'zero month' => ['2026-00-15 12:00'],
            'hour overflow' => ['2026-07-15 25:00'],
            'next-day midnight' => ['2026-07-15T24:00'],
            'minute overflow' => ['2026-07-15 12:60'],
            'unrecognized text' => ['not-a-time'],
            'date only' => ['2026-07-15'],
            'unpadded month' => ['2026-7-15 12:00'],
            'unpadded hour' => ['2026-07-15 1:00'],
            'double separator' => ['2026-07-15  12:00'],
            'embedded null byte' => ["2026-07-15\0 12:00"],
            'trailing content' => ['2026-07-15 12:00 UTC'],
            'unrequested seconds' => ['2026-07-15 12:00:00'],
            'timezone offset' => ['2026-07-15T12:00+01:00'],
        ];
    }

    public function test_valid_space_and_t_times_resolve_grant_windows_in_utc(): void
    {
        $board = $this->makeBoard($this->makeCategory('SimUTC'));
        $user = $this->makeUser();
        $roles = new RoleRepository($this->db);
        (new RoleAssignmentRepository($this->db))->create([
            'subject_id' => (int) $user['id'],
            'role_id' => (int) $roles->findByKey('system.moderator')['id'],
            'scope_type' => 'board',
            'scope_id' => (int) $board['id'],
            'starts_at' => '2028-02-29 12:00:00',
            'ends_at' => '2028-02-29 12:01:00',
        ]);
        $viewer = User::fromRow($this->makeAdmin());
        $service = $this->service();
        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            foreach (['2028-02-29 12:00', '2028-02-29T12:00', ' 2028-02-29T12:00 '] as $at) {
                $inside = $service->simulate($viewer, (string) $user['username'], 'core.thread.lock', (int) $board['id'], $at);
                self::assertNull($inside['error'], $at);
                self::assertTrue($inside['decision']->allowed, $at);
            }
            foreach (['2028-02-29 11:59', '2028-02-29T12:01'] as $at) {
                $outside = $service->simulate($viewer, (string) $user['username'], 'core.thread.lock', (int) $board['id'], $at);
                self::assertNull($outside['error'], $at);
                self::assertFalse($outside['decision']->allowed, $at);
            }
        } finally {
            date_default_timezone_set($previousTimezone);
        }
    }

    public function test_omitted_or_blank_time_uses_the_current_grant_window(): void
    {
        $board = $this->makeBoard($this->makeCategory('SimNow'));
        $user = $this->makeUser();
        $roles = new RoleRepository($this->db);
        (new RoleAssignmentRepository($this->db))->create([
            'subject_id' => (int) $user['id'],
            'role_id' => (int) $roles->findByKey('system.moderator')['id'],
            'scope_type' => 'board',
            'scope_id' => (int) $board['id'],
            'starts_at' => gmdate('Y-m-d H:i:s', time() - 86400),
            'ends_at' => gmdate('Y-m-d H:i:s', time() + 86400),
        ]);
        $viewer = User::fromRow($this->makeAdmin());
        $service = $this->service();

        foreach ([null, '', '   '] as $at) {
            $result = $service->simulate($viewer, (string) $user['username'], 'core.thread.lock', (int) $board['id'], $at);
            self::assertNull($result['error']);
            self::assertTrue($result['decision']->allowed);
        }
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
