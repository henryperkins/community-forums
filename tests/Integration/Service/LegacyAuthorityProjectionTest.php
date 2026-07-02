<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Domain\User;
use App\Repository\BoardModeratorRepository;
use App\Service\LegacyAuthorityProjection;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08): virtual grants derived from users.role and
 * board_moderators, reproducing legacy authority quirks exactly.
 */
final class LegacyAuthorityProjectionTest extends TestCase
{
    private function projection(): LegacyAuthorityProjection
    {
        return new LegacyAuthorityProjection(new BoardModeratorRepository($this->db));
    }

    /**
     * @param list<array<string,mixed>> $grants
     * @return list<array<string,mixed>>
     */
    private function ofKind(array $grants, string $kind): array
    {
        return array_values(array_filter($grants, static fn (array $grant): bool => $grant['kind'] === $kind));
    }

    public function test_guest_projects_only_the_guest_role(): void
    {
        $bundle = $this->projection()->bundleFor(null);
        self::assertSame(0, $bundle['site_rank']);
        self::assertCount(1, $bundle['grants']);
        self::assertSame('system.guest', $bundle['grants'][0]['role_key']);
        self::assertSame('site', $bundle['grants'][0]['scope_type']);
    }

    public function test_plain_user_projects_guest_plus_user_at_rank_10(): void
    {
        $bundle = $this->projection()->bundleFor(User::fromRow($this->makeUser()));
        self::assertSame(10, $bundle['site_rank']);
        $roleKeys = array_column($this->ofKind($bundle['grants'], 'role'), 'role_key');
        sort($roleKeys);
        self::assertSame(['system.guest', 'system.user'], $roleKeys);
        self::assertSame([], $this->ofKind($bundle['grants'], 'capability'));
    }

    public function test_board_moderator_projects_board_scoped_moderator_and_staff_any_warn(): void
    {
        $user = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory('P'));
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $user['id']);

        $bundle = $this->projection()->bundleFor(User::fromRow($user));
        self::assertSame(10, $bundle['site_rank']);

        $modGrants = array_values(array_filter(
            $bundle['grants'],
            static fn (array $grant): bool => $grant['role_key'] === 'system.moderator',
        ));
        self::assertCount(1, $modGrants);
        self::assertSame('board', $modGrants[0]['scope_type']);
        self::assertSame((int) $board['id'], $modGrants[0]['scope_id']);

        $capGrants = $this->ofKind($bundle['grants'], 'capability');
        self::assertSame(['core.user.warn'], array_column($capGrants, 'capability_key'));
        self::assertSame('site', $capGrants[0]['scope_type']);
    }

    public function test_vestigial_global_moderator_gets_pending_view_and_rank_but_no_board_powers(): void
    {
        $bundle = $this->projection()->bundleFor(User::fromRow($this->makeUser(['role' => 'moderator'])));
        self::assertSame(20, $bundle['site_rank']);

        $roleKeys = array_column($this->ofKind($bundle['grants'], 'role'), 'role_key');
        self::assertNotContains('system.moderator', $roleKeys);

        $capKeys = array_column($this->ofKind($bundle['grants'], 'capability'), 'capability_key');
        self::assertSame(['core.content.view_pending'], $capKeys);
        self::assertNotContains('core.user.warn', $capKeys);
    }

    public function test_admin_projects_site_admin_at_rank_30(): void
    {
        $bundle = $this->projection()->bundleFor(User::fromRow($this->makeAdmin()));
        self::assertSame(30, $bundle['site_rank']);
        $roleKeys = array_column($this->ofKind($bundle['grants'], 'role'), 'role_key');
        self::assertContains('system.admin', $roleKeys);
        $adminGrant = array_values(array_filter(
            $bundle['grants'],
            static fn (array $grant): bool => $grant['role_key'] === 'system.admin',
        ))[0];
        self::assertSame('site', $adminGrant['scope_type']);
    }
}
