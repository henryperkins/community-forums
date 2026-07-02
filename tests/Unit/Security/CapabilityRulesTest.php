<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Domain\User;
use App\Security\CapabilityCatalog;
use App\Security\CapabilityDecision;
use App\Security\CapabilityRules;
use PHPUnit\Framework\TestCase;

/**
 * Increment 1 (P5-08): pure union-then-narrow decision core. State beats role;
 * grants union; scope then read-gate/floor narrow; starts_at/ends_at are
 * enforced by the resolver, not by cleanup jobs.
 */
final class CapabilityRulesTest extends TestCase
{
    private const UTC_NOW = '2026-07-02 12:00:00';

    private function at(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::UTC_NOW, new \DateTimeZone('UTC'));
    }

    /** @param array<string,mixed> $overrides */
    private function user(array $overrides = []): User
    {
        return User::fromRow($overrides + [
            'id' => 7,
            'username' => 'alice',
            'email' => 'alice@example.test',
            'role' => 'user',
            'status' => 'active',
        ]);
    }

    /** @return array<string,mixed> catalogue meta for a key */
    private function meta(string $key): array
    {
        return CapabilityCatalog::all()[$key];
    }

    /** @param array<string,mixed> $overrides */
    private function grant(array $overrides = []): array
    {
        return $overrides + [
            'kind' => 'role',
            'role_key' => 'system.user',
            'capability_key' => null,
            'scope_type' => 'site',
            'scope_id' => null,
            'starts_at' => null,
            'ends_at' => null,
            'source' => 'legacy',
        ];
    }

    /** @param array<string,mixed> $overrides */
    private function ctx(array $overrides = []): array
    {
        return $overrides + [
            'board' => null,
            'board_member' => false,
            'board_readable' => false,
            'owner_id' => null,
            'user_id' => null,
            'category_id' => null,
        ];
    }

    /** @return array<string,mixed> a boards row */
    private function board(array $overrides = []): array
    {
        return $overrides + [
            'id' => 3,
            'category_id' => 1,
            'visibility' => 'public',
            'post_min_role' => 'user',
            'is_archived' => 0,
            'name' => 'B',
        ];
    }

    /**
     * @param list<array<string,mixed>> $grants
     * @param list<string> $rolesHolding
     * @param array<string,mixed> $ctx
     */
    private function decide(
        string $key,
        ?User $actor,
        bool $canWrite,
        int $siteRank,
        array $grants,
        array $rolesHolding,
        array $ctx,
        bool $isOwner = false,
    ): CapabilityDecision {
        return CapabilityRules::decide(
            $key,
            $this->meta($key),
            $actor,
            $canWrite,
            $isOwner,
            $siteRank,
            $grants,
            $rolesHolding,
            $ctx,
            $this->at(),
        );
    }

    public function test_guest_reads_public_board_but_not_private(): void
    {
        $guestGrants = [$this->grant(['role_key' => 'system.guest'])];
        $readable = $this->ctx(['board' => $this->board(), 'board_readable' => true]);
        $d = $this->decide('core.board.read', null, false, 0, $guestGrants, ['system.guest', 'system.user', 'system.moderator', 'system.admin'], $readable);
        self::assertTrue($d->allowed);
        self::assertSame('grant', $d->source);
        self::assertSame('system.guest', $d->roleKey);

        $private = $this->ctx(['board' => $this->board(['visibility' => 'private']), 'board_readable' => false]);
        $d = $this->decide('core.board.read', null, false, 0, $guestGrants, ['system.guest', 'system.user', 'system.moderator', 'system.admin'], $private);
        self::assertFalse($d->allowed);
        self::assertSame('read_gate', $d->source);
    }

    public function test_guest_has_no_grant_for_thread_create(): void
    {
        $d = $this->decide(
            'core.thread.create',
            null,
            false,
            0,
            [$this->grant(['role_key' => 'system.guest'])],
            ['system.user', 'system.moderator', 'system.admin'],
            $this->ctx(['board' => $this->board(), 'board_readable' => true]),
        );
        self::assertFalse($d->allowed);
        self::assertSame('no_grant', $d->source);
    }

    public function test_state_beats_role_except_read_and_self_account(): void
    {
        $u = $this->user(['status' => 'suspended', 'suspended_until' => null]);
        $grants = [$this->grant()];
        $holding = ['system.user', 'system.moderator', 'system.admin'];
        $boardCtx = $this->ctx(['board' => $this->board(), 'board_readable' => true]);

        $d = $this->decide('core.post.create', $u, false, 10, $grants, $holding, $boardCtx);
        self::assertFalse($d->allowed);
        self::assertSame('state', $d->source);

        $d = $this->decide('core.board.read', $u, false, 10, [$this->grant(['role_key' => 'system.guest'])], ['system.guest', 'system.user', 'system.moderator', 'system.admin'], $boardCtx);
        self::assertTrue($d->allowed, 'suspended accounts can still read');

        $d = $this->decide('core.account.manage_self', $u, false, 10, $grants, $holding, $this->ctx());
        self::assertTrue($d->allowed, 'suspended accounts still manage their own account');
    }

    public function test_temporal_window_is_enforced_by_the_resolver(): void
    {
        $u = $this->user();
        $holding = ['system.moderator', 'system.admin'];
        $ctx = $this->ctx(['board' => $this->board(['id' => 3]), 'board_readable' => true]);

        $expired = [$this->grant(['role_key' => 'system.moderator', 'scope_type' => 'board', 'scope_id' => 3, 'starts_at' => '2026-06-01 00:00:00', 'ends_at' => '2026-07-01 00:00:00', 'source' => 'assignment'])];
        self::assertFalse($this->decide('core.thread.lock', $u, true, 10, $expired, $holding, $ctx)->allowed);

        $future = [$this->grant(['role_key' => 'system.moderator', 'scope_type' => 'board', 'scope_id' => 3, 'starts_at' => '2026-08-01 00:00:00', 'ends_at' => null, 'source' => 'assignment'])];
        self::assertFalse($this->decide('core.thread.lock', $u, true, 10, $future, $holding, $ctx)->allowed);

        $active = [$this->grant(['role_key' => 'system.moderator', 'scope_type' => 'board', 'scope_id' => 3, 'starts_at' => '2026-06-01 00:00:00', 'ends_at' => '2026-08-01 00:00:00', 'source' => 'assignment'])];
        $d = $this->decide('core.thread.lock', $u, true, 10, $active, $holding, $ctx);
        self::assertTrue($d->allowed);
        self::assertSame('board', $d->scopeType);
        self::assertSame(3, $d->scopeId);
    }

    public function test_scope_narrowing_board_category_site(): void
    {
        $u = $this->user();
        $holding = ['system.moderator', 'system.admin'];
        $boardA = $this->ctx(['board' => $this->board(['id' => 3, 'category_id' => 1]), 'board_readable' => true]);
        $boardB = $this->ctx(['board' => $this->board(['id' => 4, 'category_id' => 2]), 'board_readable' => true]);
        $grantA = [$this->grant(['role_key' => 'system.moderator', 'scope_type' => 'board', 'scope_id' => 3])];

        self::assertTrue($this->decide('core.thread.lock', $u, true, 10, $grantA, $holding, $boardA)->allowed);
        self::assertFalse($this->decide('core.thread.lock', $u, true, 10, $grantA, $holding, $boardB)->allowed);

        $catGrant = [$this->grant(['role_key' => 'system.moderator', 'scope_type' => 'category', 'scope_id' => 1])];
        self::assertTrue($this->decide('core.thread.lock', $u, true, 10, $catGrant, $holding, $boardA)->allowed);
        self::assertFalse($this->decide('core.thread.lock', $u, true, 10, $catGrant, $holding, $boardB)->allowed);

        $boardAdmin = [$this->grant(['role_key' => 'system.admin', 'scope_type' => 'board', 'scope_id' => 3])];
        $d = $this->decide('core.user.ban', $u, true, 10, $boardAdmin, ['system.admin'], $this->ctx());
        self::assertFalse($d->allowed);
        self::assertSame('no_grant', $d->source);
    }

    public function test_posting_floor_uses_global_site_rank_only(): void
    {
        $u = $this->user();
        $holding = ['system.user', 'system.moderator', 'system.admin'];
        $floorBoard = $this->ctx(['board' => $this->board(['id' => 3, 'post_min_role' => 'moderator']), 'board_readable' => true]);
        $grants = [
            $this->grant(),
            $this->grant(['role_key' => 'system.moderator', 'scope_type' => 'board', 'scope_id' => 3]),
        ];

        $d = $this->decide('core.thread.create', $u, true, 10, $grants, $holding, $floorBoard);
        self::assertFalse($d->allowed);
        self::assertSame('floor', $d->source);

        self::assertTrue($this->decide('core.thread.create', $u, true, 20, $grants, $holding, $floorBoard)->allowed);
    }

    public function test_archived_and_unreadable_boards_close_canpost_gated_keys(): void
    {
        $u = $this->user();
        $holding = ['system.user', 'system.moderator', 'system.admin'];
        $grants = [$this->grant()];

        $archived = $this->ctx(['board' => $this->board(['is_archived' => 1]), 'board_readable' => true]);
        self::assertSame('archived', $this->decide('core.thread.tag', $u, true, 10, $grants, $holding, $archived)->source);

        $unreadable = $this->ctx(['board' => $this->board(['visibility' => 'private']), 'board_readable' => false]);
        self::assertSame('read_gate', $this->decide('core.post.create', $u, true, 10, $grants, $holding, $unreadable)->source);

        $modGrant = [$this->grant(['role_key' => 'system.moderator', 'scope_type' => 'board', 'scope_id' => 3])];
        $privateBoard = $this->ctx(['board' => $this->board(['id' => 3, 'visibility' => 'private']), 'board_readable' => false]);
        self::assertTrue($this->decide('core.post.delete_any', $u, true, 10, $modGrant, ['system.moderator', 'system.admin'], $privateBoard)->allowed);
    }

    public function test_dual_path_keys_allow_owner_or_board_moderator(): void
    {
        $owner = $this->user(['id' => 7]);
        $holding = ['system.user', 'system.moderator', 'system.admin'];
        $userGrant = [$this->grant()];

        $ownCtx = $this->ctx(['board' => $this->board(['id' => 3]), 'board_readable' => true, 'owner_id' => 7]);
        $d = $this->decide('core.thread.mark_solved', $owner, true, 10, $userGrant, $holding, $ownCtx);
        self::assertTrue($d->allowed);

        $otherCtx = $this->ctx(['board' => $this->board(['id' => 3]), 'board_readable' => true, 'owner_id' => 99]);
        self::assertFalse($this->decide('core.thread.mark_solved', $owner, true, 10, $userGrant, $holding, $otherCtx)->allowed);

        $modGrants = [$this->grant(), $this->grant(['role_key' => 'system.moderator', 'scope_type' => 'board', 'scope_id' => 3])];
        self::assertTrue($this->decide('core.thread.mark_solved', $owner, true, 10, $modGrants, $holding, $otherCtx)->allowed);
    }

    public function test_dual_path_board_authority_comes_only_from_moderation_tier_roles(): void
    {
        // A custom role holding a dual-path key confers the author path only:
        // board-wide use over other members' threads is reserved to
        // system.moderator/system.admin (taxonomy §4.2 — "board-wide use comes
        // only through a board-scoped moderator assignment").
        $u = $this->user(['id' => 7]);
        $holding = ['system.user', 'system.moderator', 'system.admin', 'custom.helper'];
        $customGrant = [$this->grant(['role_key' => 'custom.helper', 'scope_type' => 'board', 'scope_id' => 3, 'source' => 'assignment'])];
        $otherCtx = $this->ctx(['board' => $this->board(['id' => 3]), 'board_readable' => true, 'owner_id' => 99]);

        $d = $this->decide('core.thread.mark_solved', $u, true, 10, $customGrant, $holding, $otherCtx);
        self::assertFalse($d->allowed, 'custom roles never grant board-wide dual-path authority');
        self::assertSame('no_grant', $d->source);

        $ownCtx = $this->ctx(['board' => $this->board(['id' => 3]), 'board_readable' => true, 'owner_id' => 7]);
        self::assertTrue(
            $this->decide('core.thread.mark_solved', $u, true, 10, $customGrant, $holding, $ownCtx)->allowed,
            'the author path still works through a custom role',
        );

        // A held-anywhere probe (board target, no owner context) must not read
        // the baseline bundle as board-wide authority either.
        $userGrant = [$this->grant()];
        $noOwnerCtx = $this->ctx(['board' => $this->board(['id' => 3]), 'board_readable' => true]);
        self::assertFalse($this->decide('core.thread.mark_solved', $u, true, 10, $userGrant, $holding, $noOwnerCtx)->allowed);
    }

    public function test_direct_capability_grant_satisfies_only_its_key(): void
    {
        $u = $this->user(['role' => 'moderator']);
        $viewPending = [$this->grant(['kind' => 'capability', 'role_key' => null, 'capability_key' => 'core.content.view_pending'])];
        $ctx = $this->ctx(['board' => $this->board(), 'board_readable' => true]);

        self::assertTrue($this->decide('core.content.view_pending', $u, true, 20, $viewPending, ['system.moderator', 'system.admin'], $ctx)->allowed);
        self::assertFalse($this->decide('core.thread.lock', $u, true, 20, $viewPending, ['system.moderator', 'system.admin'], $ctx)->allowed);
    }

    public function test_self_scope_denies_other_subjects(): void
    {
        $u = $this->user(['id' => 7]);
        $grants = [$this->grant()];
        $holding = ['system.user', 'system.moderator', 'system.admin'];

        self::assertTrue($this->decide('core.post.edit_own', $u, true, 10, $grants, $holding, $this->ctx(['owner_id' => 7]))->allowed);
        $d = $this->decide('core.post.edit_own', $u, true, 10, $grants, $holding, $this->ctx(['owner_id' => 8]));
        self::assertFalse($d->allowed);
        self::assertSame('scope', $d->source);
        self::assertFalse($this->decide('core.account.manage_self', $u, true, 10, $grants, $holding, $this->ctx(['user_id' => 8]))->allowed);
    }

    public function test_protected_keys_resolve_only_via_active_owner(): void
    {
        $admin = $this->user(['role' => 'admin']);
        $adminGrants = [$this->grant(['role_key' => 'system.admin'])];

        $d = $this->decide('core.owner.transfer', $admin, true, 30, $adminGrants, [], $this->ctx(), isOwner: false);
        self::assertFalse($d->allowed);
        self::assertSame('protected', $d->source);

        self::assertTrue($this->decide('core.owner.transfer', $admin, true, 30, $adminGrants, [], $this->ctx(), isOwner: true)->allowed);
        self::assertFalse($this->decide('core.owner.transfer', $admin, false, 30, $adminGrants, [], $this->ctx(), isOwner: true)->allowed);
    }

    public function test_decision_vo_shape(): void
    {
        $d = CapabilityDecision::deny('core.x', 'unknown_capability', 'Unknown capability keys fail dark.');
        self::assertFalse($d->allowed);
        self::assertSame('core.x', $d->capability);
        self::assertNull($d->roleKey);

        $a = CapabilityDecision::allow('core.board.read', 'grant', 'ok', 'system.guest', 'site', null);
        self::assertTrue($a->allowed);
        self::assertSame('site', $a->scopeType);
    }
}
