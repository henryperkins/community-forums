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
        return $this->capabilityResolver();
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
        // Inc 6 (P5-08 Task 3): the resolver now memoizes per request, so a raw
        // authority mutation must invalidate() before the next can() call can
        // observe it — the exact contract the grant/revoke services will follow.
        $resolver->invalidate();
        self::assertFalse($resolver->can($u, 'core.thread.lock', $target)->allowed);

        $assign->create([
            'subject_id' => (int) $user['id'],
            'role_id' => $modRoleId,
            'scope_type' => 'board',
            'scope_id' => (int) $board['id'],
            'starts_at' => '2030-01-01 00:00:00',
        ]);
        $resolver->invalidate();
        self::assertFalse($resolver->can($u, 'core.thread.lock', $target)->allowed);

        $active = $assign->create([
            'subject_id' => (int) $user['id'],
            'role_id' => $modRoleId,
            'scope_type' => 'board',
            'scope_id' => (int) $board['id'],
        ]);
        $resolver->invalidate();
        $d = $resolver->can($u, 'core.thread.lock', $target);
        self::assertTrue($d->allowed);
        self::assertSame('system.moderator', $d->roleKey);
        self::assertSame('board', $d->scopeType);

        $this->db->run('UPDATE role_assignments SET revoked_at = UTC_TIMESTAMP() WHERE id IN (' . (int) $active . ',' . (int) $expired . ')');
        $resolver->invalidate();
        self::assertFalse($resolver->can($u, 'core.thread.lock', $target)->allowed);

        $assign->create(['subject_id' => (int) $user['id'], 'role_id' => $adminRoleId, 'scope_type' => 'category', 'scope_id' => $cat]);
        $resolver->invalidate();
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
        $resolver->invalidate(); // Inc 6 Task 3: mutation must invalidate the warm memo.
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
        $resolver->invalidate(); // Inc 6 Task 3: mutation must invalidate the warm memo.
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

    public function test_memo_serves_repeat_decisions_and_invalidate_clears_it(): void
    {
        $modRow = $this->makeUser();
        $mod = $this->userEntity($modRow);
        $board = $this->makeBoard($this->makeCategory());
        $resolver = $this->resolver();
        $target = ['board_id' => (int) $board['id']];
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $modRow['id']);

        self::assertTrue($resolver->can($mod, 'core.thread.lock', $target)->allowed);

        // Mutate DB-read authority behind the memo's back. This would be visible to
        // a fresh resolver call immediately if bundle/decision memoization were not
        // active within the request.
        $this->db->run('DELETE FROM board_moderators WHERE board_id = ? AND user_id = ?', [(int) $board['id'], (int) $modRow['id']]);

        // Memoized: still allowed within this request scope...
        self::assertTrue($resolver->can($mod, 'core.thread.lock', $target)->allowed);
        // ...until invalidated.
        $resolver->invalidate();
        self::assertFalse($resolver->can($mod, 'core.thread.lock', $target)->allowed);
    }

    public function test_expiry_denies_despite_warm_memo_tm_pe_03(): void
    {
        $user = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());

        // Custom role holding core.thread.lock, following this file's existing
        // custom-role pattern (see test_custom_roles_confer_owner_path_only_for_dual_path_keys):
        // RoleRepository::create + RoleCapabilityRepository::replaceForRole.
        $roles = new RoleRepository($this->db);
        $roleId = $roles->create(['role_key' => 'custom.expirytest', 'name' => 'Expiry Test', 'description' => null, 'created_by' => null]);
        (new RoleCapabilityRepository($this->db))->replaceForRole(
            $roleId,
            array_values((new CapabilityRepository($this->db))->idsByKeys(['core.thread.lock'])),
        );
        (new RoleAssignmentRepository($this->db))->create([
            'subject_id' => (int) $user['id'],
            'role_id' => $roleId,
            'scope_type' => 'board',
            'scope_id' => (int) $board['id'],
            'ends_at' => gmdate('Y-m-d H:i:s', time() + 3600),
        ]);
        $resolver = $this->resolver();
        $entity = $this->userEntity($user);
        $target = ['board_id' => (int) $board['id']];

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        self::assertTrue($resolver->can($entity, 'core.thread.lock', $target, $now)->allowed);
        // Two hours later the grant is expired: a warm memo must NOT resurrect it.
        self::assertFalse($resolver->can($entity, 'core.thread.lock', $target, $now->modify('+2 hours'))->allowed);
    }

    public function test_membership_lookups_are_memoized_per_request_until_invalidate(): void
    {
        $cat = $this->makeCategory('MemberMemo');
        $private = $this->makeBoard($cat, ['visibility' => 'private']);
        $member = $this->makeUser();
        $members = new BoardMemberRepository($this->db);
        $members->add((int) $private['id'], (int) $member['id'], null);

        $resolver = $this->resolver();
        $entity = $this->userEntity($member);
        $target = ['board_id' => (int) $private['id']];

        self::assertTrue($resolver->can($entity, 'core.content.report', $target)->allowed);

        // Mutate the row directly (no invalidate): within the request the
        // membership answer stays memoized even for a different capability.
        $this->db->run('DELETE FROM board_members WHERE board_id = ? AND user_id = ?', [(int) $private['id'], (int) $member['id']]);
        self::assertTrue($resolver->can($entity, 'core.content.react', $target)->allowed);

        // After the documented invalidate() the fresh state is observed.
        $resolver->invalidate();
        self::assertFalse($resolver->can($entity, 'core.content.react', $target)->allowed);
    }

    public function test_role_key_lookups_are_memoized_per_request_until_invalidate(): void
    {
        $cat = $this->makeCategory('RoleKeysMemo');
        $public = $this->makeBoard($cat);
        $user = $this->makeUser();

        $resolver = $this->resolver();
        $entity = $this->userEntity($user);

        self::assertTrue($resolver->can($entity, 'core.thread.create', ['board_id' => (int) $public['id']])->allowed);

        // Strip the capability from every role directly (no invalidate): a
        // different-target decision still uses the memoized role-key set.
        $this->db->run(
            'DELETE rc FROM role_capabilities rc
             JOIN capabilities c ON c.id = rc.capability_id
             WHERE c.capability_key = ?',
            ['core.thread.create'],
        );
        $other = $this->makeBoard($cat);
        self::assertTrue($resolver->can($entity, 'core.thread.create', ['board_id' => (int) $other['id']])->allowed);

        $resolver->invalidate();
        self::assertFalse($resolver->can($entity, 'core.thread.create', ['board_id' => (int) $other['id']])->allowed);
    }

    public function test_primed_board_rows_and_membership_short_circuit_lookups(): void
    {
        $cat = $this->makeCategory('Priming');
        $private = $this->makeBoard($cat, ['visibility' => 'private']);
        $user = $this->makeUser();

        $resolver = $this->resolver();
        $entity = $this->userEntity($user);

        // A primed board row is authoritative: this id does not exist in the
        // DB, so a non-primed resolver could never apply its private read gate.
        $ghost = ['id' => 999999, 'category_id' => (int) $cat, 'visibility' => 'private', 'post_min_role' => 'user', 'is_archived' => 0];
        $resolver->primeBoards([$ghost]);
        self::assertFalse($resolver->can($entity, 'core.thread.create', ['board_id' => 999999])->allowed);

        // Primed membership is authoritative: the DB says non-member (deny on
        // a private board), the primed map says member (allow).
        $resolver->primeMembership((int) $user['id'], [(int) $private['id']], [(int) $private['id']]);
        self::assertTrue($resolver->can($entity, 'core.content.report', ['board_id' => (int) $private['id']])->allowed);
    }
}
