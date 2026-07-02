<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\CapabilityRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Security\BoardPolicy;
use App\Security\CapabilityResolver;
use App\Security\WriteGate;
use App\Service\LegacyAuthorityProjection;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08): resolver against the real seeded DB. It unions legacy
 * projection and role_assignments, then narrows by scope/state/read/floor.
 */
final class CapabilityResolverTest extends TestCase
{
    private function resolver(): CapabilityResolver
    {
        return new CapabilityResolver(
            new RoleCapabilityRepository($this->db),
            new RoleAssignmentRepository($this->db),
            new LegacyAuthorityProjection(new BoardModeratorRepository($this->db)),
            new ProtectedOwnerRepository($this->db),
            $this->boards(),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
            new WriteGate(),
        );
    }

    public function test_legacy_projection_end_to_end_matrix(): void
    {
        $cat = $this->makeCategory('Resolver');
        $public = $this->makeBoard($cat);
        $private = $this->makeBoard($cat, ['visibility' => 'private']);
        $floor = $this->makeBoard($cat, ['post_min_role' => 'moderator']);

        $guest = null;
        $user = User::fromRow($this->makeUser());
        $globalMod = User::fromRow($this->makeUser(['role' => 'moderator']));
        $admin = User::fromRow($this->makeAdmin());
        $boardModRow = $this->makeUser();
        (new BoardModeratorRepository($this->db))->assign((int) $public['id'], (int) $boardModRow['id']);
        $boardMod = User::fromRow($boardModRow);
        $suspended = User::fromRow($this->makeUser(['status' => 'suspended']));

        $resolver = $this->resolver();
        $pub = ['board_id' => (int) $public['id']];
        $priv = ['board_id' => (int) $private['id']];
        $flo = ['board_id' => (int) $floor['id']];

        self::assertTrue($resolver->can($guest, 'core.board.read', $pub)->allowed);
        self::assertFalse($resolver->can($guest, 'core.board.read', $priv)->allowed);
        self::assertTrue($resolver->can($admin, 'core.board.read', $priv)->allowed);
        self::assertTrue($resolver->can($suspended, 'core.board.read', $pub)->allowed);

        self::assertTrue($resolver->can($user, 'core.thread.create', $pub)->allowed);
        self::assertFalse($resolver->can($guest, 'core.thread.create', $pub)->allowed);
        self::assertSame('state', $resolver->can($suspended, 'core.thread.create', $pub)->source);
        self::assertSame('floor', $resolver->can($user, 'core.thread.create', $flo)->source);
        self::assertTrue($resolver->can($globalMod, 'core.thread.create', $flo)->allowed);
        self::assertSame('floor', $resolver->can($boardMod, 'core.thread.create', $flo)->source);
        self::assertFalse($resolver->can($user, 'core.post.create', $priv)->allowed);

        self::assertTrue($resolver->can($boardMod, 'core.thread.lock', $pub)->allowed);
        self::assertFalse($resolver->can($boardMod, 'core.thread.lock', $priv)->allowed);
        self::assertTrue($resolver->can($admin, 'core.thread.lock', $priv)->allowed);
        self::assertFalse($resolver->can($globalMod, 'core.thread.lock', $pub)->allowed);
        self::assertTrue($resolver->can($globalMod, 'core.content.view_pending', $pub)->allowed);
        self::assertTrue($resolver->can($boardMod, 'core.user.warn')->allowed);
        self::assertFalse($resolver->can($globalMod, 'core.user.warn')->allowed);

        self::assertTrue($resolver->can($admin, 'core.user.ban')->allowed);
        self::assertFalse($resolver->can($boardMod, 'core.user.ban')->allowed);

        self::assertSame('unknown_capability', $resolver->can($admin, 'core.nope')->source);
    }

    public function test_assignment_union_windows_and_category_scope(): void
    {
        $cat = $this->makeCategory('Scoped');
        $otherCat = $this->makeCategory('Other');
        $board = $this->makeBoard($cat);
        $user = $this->makeUser();
        $u = User::fromRow($user);
        $roles = new RoleRepository($this->db);
        $assign = new RoleAssignmentRepository($this->db);
        $modRoleId = (int) $roles->findByKey('system.moderator')['id'];
        $adminRoleId = (int) $roles->findByKey('system.admin')['id'];
        $resolver = $this->resolver();
        $target = ['board_id' => (int) $board['id']];

        self::assertFalse($resolver->can($u, 'core.thread.lock', $target)->allowed);

        $expired = $assign->create([
            'subject_id' => (int) $user['id'],
            'role_id' => $modRoleId,
            'scope_type' => 'board',
            'scope_id' => (int) $board['id'],
            'ends_at' => '2026-01-01 00:00:00',
        ]);
        self::assertFalse($resolver->can($u, 'core.thread.lock', $target)->allowed);

        $assign->create([
            'subject_id' => (int) $user['id'],
            'role_id' => $modRoleId,
            'scope_type' => 'board',
            'scope_id' => (int) $board['id'],
            'starts_at' => '2030-01-01 00:00:00',
        ]);
        self::assertFalse($resolver->can($u, 'core.thread.lock', $target)->allowed);

        $active = $assign->create([
            'subject_id' => (int) $user['id'],
            'role_id' => $modRoleId,
            'scope_type' => 'board',
            'scope_id' => (int) $board['id'],
        ]);
        $d = $resolver->can($u, 'core.thread.lock', $target);
        self::assertTrue($d->allowed);
        self::assertSame('system.moderator', $d->roleKey);
        self::assertSame('board', $d->scopeType);

        $this->db->run('UPDATE role_assignments SET revoked_at = UTC_TIMESTAMP() WHERE id IN (' . (int) $active . ',' . (int) $expired . ')');
        self::assertFalse($resolver->can($u, 'core.thread.lock', $target)->allowed);

        $assign->create(['subject_id' => (int) $user['id'], 'role_id' => $adminRoleId, 'scope_type' => 'category', 'scope_id' => $cat]);
        self::assertTrue($resolver->can($u, 'core.board.manage', ['category_id' => $cat])->allowed);
        self::assertFalse($resolver->can($u, 'core.board.manage', ['category_id' => $otherCat])->allowed);
        self::assertTrue($resolver->can($u, 'core.thread.lock', $target)->allowed);
        self::assertFalse($resolver->can($u, 'core.user.ban')->allowed);
    }

    public function test_protected_keys_and_owner_path(): void
    {
        $adminRow = $this->makeAdmin();
        $admin = User::fromRow($adminRow);
        $resolver = $this->resolver();

        self::assertSame('protected', $resolver->can($admin, 'core.owner.transfer')->source);
        self::assertFalse($resolver->can($admin, 'core.owner.transfer')->allowed);

        (new ProtectedOwnerRepository($this->db))->designate((int) $adminRow['id']);
        self::assertTrue($resolver->can($admin, 'core.owner.transfer')->allowed);
        self::assertTrue($resolver->can($admin, 'core.trust.manage_keys')->allowed);
    }

    public function test_dual_path_and_self_keys_against_real_threads(): void
    {
        $cat = $this->makeCategory('Dual');
        $board = $this->makeBoard($cat);
        $author = $this->makeUser();
        $other = $this->makeUser();
        $thread = $this->makeThread($board, $author);
        $resolver = $this->resolver();
        $ownCtx = ['board_id' => (int) $board['id'], 'owner_id' => (int) $author['id']];

        self::assertTrue($resolver->can(User::fromRow($author), 'core.thread.mark_solved', $ownCtx)->allowed);
        self::assertFalse($resolver->can(User::fromRow($other), 'core.thread.mark_solved', $ownCtx)->allowed);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $other['id']);
        self::assertTrue($resolver->can(User::fromRow($other), 'core.thread.mark_solved', $ownCtx)->allowed);

        self::assertTrue($resolver->can(User::fromRow($author), 'core.post.edit_own', ['owner_id' => (int) $author['id']])->allowed);
        self::assertSame('scope', $resolver->can(User::fromRow($other), 'core.post.edit_own', ['owner_id' => (int) $author['id']])->source);
        self::assertGreaterThan(0, $thread['thread_id']);
    }

    public function test_custom_roles_confer_owner_path_only_for_dual_path_keys(): void
    {
        $cat = $this->makeCategory('DualCustom');
        $board = $this->makeBoard($cat);
        $author = $this->makeUser();
        $helper = $this->makeUser();

        // A custom role holding a dual-path key, assigned board-scoped: it must
        // confer the author path only — board-wide authority over other members'
        // threads stays moderation-tier (taxonomy §4.2).
        $roles = new RoleRepository($this->db);
        $roleId = $roles->create(['role_key' => 'custom.dualtest', 'name' => 'Dual Test', 'description' => null, 'created_by' => null]);
        (new RoleCapabilityRepository($this->db))->replaceForRole(
            $roleId,
            array_values((new CapabilityRepository($this->db))->idsByKeys(['core.thread.mark_solved'])),
        );
        (new RoleAssignmentRepository($this->db))->create([
            'subject_id' => (int) $helper['id'],
            'role_id' => $roleId,
            'scope_type' => 'board',
            'scope_id' => (int) $board['id'],
        ]);

        $resolver = $this->resolver();
        $othersThread = ['board_id' => (int) $board['id'], 'owner_id' => (int) $author['id']];
        self::assertFalse(
            $resolver->can(User::fromRow($helper), 'core.thread.mark_solved', $othersThread)->allowed,
            'board-wide dual-path authority is moderation-tier only',
        );

        $ownThread = ['board_id' => (int) $board['id'], 'owner_id' => (int) $helper['id']];
        self::assertTrue(
            $resolver->can(User::fromRow($helper), 'core.thread.mark_solved', $ownThread)->allowed,
            'the author path still works through a custom role',
        );
    }
}
