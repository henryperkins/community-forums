<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\SettingRepository;
use App\Security\BoardAuthority;
use App\Security\BoardPolicy;
use App\Security\CapabilityResolver;
use App\Security\WriteGate;
use App\Service\ThreadReadService;
use App\Service\LegacyAuthorityProjection;
use App\Service\ModerationService;
use App\Service\Phase5FixtureSeeder;
use App\Service\ResolverParityService;
use Tests\Support\TestCase;

/**
 * Increment 1 exit gate (P5-08): zero parity mismatch for built-in roles on the
 * F9 fixture.
 */
final class ResolverParityTest extends TestCase
{
    private function service(): ResolverParityService
    {
        $resolver = $this->capabilityResolver();

        return new ResolverParityService(
            $this->db,
            $resolver,
            new BoardModeratorRepository($this->db),
            new BoardMemberRepository($this->db),
            new ProtectedOwnerRepository($this->db),
            new BoardPolicy(),
            new WriteGate(),
            new SettingRepository($this->db),
        );
    }

    private function seedFixture(): void
    {
        (new Phase5FixtureSeeder($this->db, new SettingRepository($this->db), 'testing'))->seed(true);
    }

    public function test_refuses_to_run_without_the_fixture(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->run();
    }

    public function test_zero_mismatch_on_the_f9_fixture(): void
    {
        $this->seedFixture();
        $result = $this->service()->run();

        // v2 fixture: 20 board keys x 4 boards x 11 actors + 3 dual-path x 8
        // owner-contexts x 11 + 6 self x 2 x 11 + 24 site x 11 + 1 category x 11
        // = 1551. A shrink below 1500 means the corpus lost actors or boards.
        self::assertGreaterThan(1500, $result['tuples']);
        self::assertSame(
            [],
            $result['mismatches'],
            "Parity mismatches:\n" . json_encode(array_slice($result['mismatches'], 0, 20), JSON_PRETTY_PRINT),
        );
        self::assertSame($result['tuples'], $result['agreed']);
        self::assertStringContainsString('phase5_fixture_v', $result['fixture']);
    }

    public function test_oracle_canmoderate_matches_the_real_moderation_service(): void
    {
        $this->seedFixture();
        $svc = $this->service();
        $board = $this->makeBoard($this->makeCategory('Pin'));
        $modRow = $this->makeUser();
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $modRow['id']);

        $boardAuthority = new BoardAuthority(new WriteGate(), new BoardModeratorRepository($this->db), $this->boards());
        $modService = new ModerationService(
            $this->db,
            $this->threads(),
            $this->posts(),
            new ModerationLogRepository($this->db),
            $this->posting(),
            new WriteGate(),
            new BoardModeratorRepository($this->db),
            $this->boards(),
            $this->users(),
            $boardAuthority,
            new ThreadReadService($this->threads(), new BoardPolicy(), new BoardMemberRepository($this->db), $boardAuthority),
        );

        $cases = [
            User::fromRow($this->makeAdmin()),
            User::fromRow($modRow),
            User::fromRow($this->makeUser(['role' => 'moderator'])),
            User::fromRow($this->makeUser(['status' => 'suspended'])),
            User::fromRow($this->makeUser()),
        ];

        foreach ($cases as $user) {
            self::assertSame(
                $modService->canModerate($user, (int) $board['id']),
                $svc->legacyCanModerate($user, (int) $board['id']),
                'oracle predicate must equal the real ModerationService::canModerate for ' . $user->username(),
            );
        }
        self::assertFalse($svc->legacyCanModerate(null, (int) $board['id']));
    }

    public function test_render_produces_the_archived_report_shape(): void
    {
        $this->seedFixture();
        $svc = $this->service();
        $md = $svc->render($svc->run(), 'abc1234');
        self::assertStringContainsString('# Phase 5 - Resolver Parity Corpus (Increment 1, P5-08)', $md);
        self::assertStringContainsString('Commit: `abc1234`', $md);
        self::assertStringContainsString('Mismatches: **0**', $md);
        self::assertStringContainsString('core.thread.lock', $md);
        self::assertStringContainsString('Known divergences (recorded, not modeled)', $md);
        self::assertStringContainsString('capability-taxonomy.md', $md);
    }
}
