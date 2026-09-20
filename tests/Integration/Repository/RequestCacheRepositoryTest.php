<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Core\FeatureFlags;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Repository\SettingRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Repository\ThreadUserRepository;
use App\Repository\UserPreferenceRepository;
use App\Service\NotificationVisibilityService;
use App\Service\SinceLastReadContextService;
use DateTimeImmutable;
use Tests\Support\TestCase;

final class RequestCacheRepositoryTest extends TestCase
{
    public function test_cursor_and_context_writes_keep_unrelated_metadata_without_hiding_the_new_cursor(): void
    {
        $user = $this->makeUser();
        $userId = (int) $user['id'];
        $board = $this->makeBoard($this->makeCategory());
        $boardId = (int) $board['id'];
        $thread = $this->makeThread($board, $user);
        $threadId = (int) $thread['thread_id'];
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId]);
        (new BoardMemberRepository($this->db))->add($boardId, $userId, null);
        (new BoardModeratorRepository($this->db))->assign($boardId, $userId);
        (new SettingRepository($this->db))->set('site_name', 'Cached community');
        (new UserPreferenceRepository($this->db))->merge($userId, ['theme' => 'dark']);
        $metadata = fn (): array => [
            (new SettingRepository($this->db))->getString('site_name'),
            (new UserPreferenceRepository($this->db))->get($userId),
            (new BoardMemberRepository($this->db))->boardIdsFor($userId),
            (new BoardModeratorRepository($this->db))->boardsFor($userId),
            (new RoleCapabilityRepository($this->db))->roleKeysHolding('core.post.create'),
        ];
        $state = new ThreadUserRepository($this->db);
        $cursor = fn () => $this->db->remember('test.cursor', fn () => $state->find($userId, $threadId), ['thread_user']);
        $this->db->beginRequestCache();
        $expected = $metadata();
        self::assertSame('Cached community', $expected[0]);
        self::assertSame(['theme' => 'dark'], $expected[1]);
        self::assertSame([$boardId], $expected[2]);
        self::assertSame([$boardId], $expected[3]);
        self::assertContains('system.user', $expected[4]);
        self::assertNull($cursor());

        $state->markRead($userId, $threadId, $opId);
        self::assertSame($opId, (int) $cursor()['last_read_post_id']);
        $this->db->resetMetrics();
        self::assertSame($expected, $metadata());
        self::assertSame(0, $this->db->metrics()['queries'], 'Cursor persistence must not reload settings or permissions.');

        $replyId = $this->posting()->reply($this->userEntity($user), $threadId, ['body' => 'An unread reply.']);
        $metadata();
        $context = (new SinceLastReadContextService($this->db))->forThread($userId, $threadId);
        self::assertSame([$replyId], array_column($context['items'], 'post_id'));
        $this->db->resetMetrics();
        self::assertSame($expected, $metadata());
        self::assertSame(0, $this->db->metrics()['queries'], 'Context persistence must not reload settings or permissions.');
    }

    public function test_notification_scope_reuses_membership_and_assignment_reads_but_fresh_scope_observes_revocation(): void
    {
        $user = $this->makeUser();
        $viewer = $this->userEntity($user);
        $boardId = (int) $this->makeBoard($this->makeCategory())['id'];
        (new BoardMemberRepository($this->db))->add($boardId, $viewer->id(), null);
        (new BoardModeratorRepository($this->db))->assign($boardId, $viewer->id());
        $flags = new FeatureFlags(new SettingRepository($this->db));
        $visibility = new NotificationVisibilityService($this->db, $flags);
        $this->db->beginRequestCache();
        $flags->all();
        (new BoardMemberRepository($this->db))->boardIdsFor($viewer->id());
        (new BoardModeratorRepository($this->db))->boardsFor($viewer->id());
        $this->db->resetMetrics();

        $scope = $visibility->scope($viewer);
        self::assertSame([$boardId], $scope['member_board_ids']);
        self::assertSame([$boardId], $scope['assigned_board_ids']);
        self::assertSame(0, $this->db->metrics()['queries']);

        // Raw writes model an external revocation that an explicit fresh read
        // must observe even while the old request snapshot is populated.
        $this->pdo->exec('DELETE FROM board_members WHERE user_id = ' . $viewer->id());
        $this->pdo->exec('DELETE FROM board_moderators WHERE user_id = ' . $viewer->id());
        $fresh = $visibility->scope($viewer, fresh: true);
        self::assertSame([], $fresh['member_board_ids']);
        self::assertSame([], $fresh['assigned_board_ids']);
    }

    public function test_settings_bulk_single_and_has_share_one_snapshot_with_per_call_defaults(): void
    {
        $writer = new SettingRepository($this->db);
        $writer->set('cache_one', 'one');
        $writer->set('cache_null', null);
        $writer->set('cache_false', false);
        $reader = new SettingRepository($this->db);
        $this->db->beginRequestCache();
        $this->db->resetMetrics();

        self::assertSame(['cache_one' => 'one', 'cache_null' => null, 'missing' => 'first'], $reader->getMany([
            'cache_one' => 'fallback', 'cache_null' => 'fallback', 'missing' => 'first',
        ]));
        self::assertSame('one', $writer->getString('cache_one'));
        self::assertFalse($reader->get('cache_false', true));
        self::assertSame('second', $reader->get('missing', 'second'));
        self::assertTrue($reader->has('cache_null'));
        self::assertFalse($reader->has('missing'));
        self::assertSame(1, $this->db->metrics()['queries']);

        $writer->set('missing', 'now present');
        $writer->set('cache_one', 'updated');
        self::assertSame('now present', $reader->getString('missing'));
        self::assertSame('updated', $reader->getString('cache_one'));
        self::assertTrue($reader->has('missing'));
    }

    public function test_capability_checks_load_the_whole_map_once_and_refresh_after_revocation(): void
    {
        $reader = new RoleCapabilityRepository($this->db);
        $userRole = (new RoleRepository($this->db))->findByKey('system.user');
        $this->db->beginRequestCache();
        $this->db->resetMetrics();

        self::assertContains('system.guest', $reader->roleKeysHolding('core.board.read'));
        self::assertNotContains('system.guest', $reader->roleKeysHolding('core.post.create'));
        self::assertContains('system.user', (new RoleCapabilityRepository($this->db))->roleKeysHolding('core.post.create'));
        self::assertSame([], $reader->roleKeysHolding('missing.capability'));
        self::assertSame(1, $this->db->metrics()['queries']);

        (new RoleCapabilityRepository($this->db))->replaceForRole((int) $userRole['id'], []);
        self::assertNotContains('system.user', $reader->roleKeysHolding('core.post.create'));
        self::assertContains('system.admin', $reader->roleKeysHolding('core.post.create'));
    }

    public function test_preferences_share_reads_across_instances_and_invalidate_after_merge(): void
    {
        $userId = (int) $this->makeUser()['id'];
        $reader = new UserPreferenceRepository($this->db);
        $writer = new UserPreferenceRepository($this->db);
        $this->db->beginRequestCache();
        $this->db->resetMetrics();
        self::assertSame([], $reader->get($userId));
        self::assertSame([], $writer->get($userId));
        self::assertSame(1, $this->db->metrics()['queries']);

        $writer->merge($userId, ['theme' => 'dark', 'per_page' => 40]);
        self::assertSame(['theme' => 'dark', 'per_page' => 40], $reader->get($userId));
        $writer->merge($userId, ['theme' => null]);
        self::assertSame(['per_page' => 40], $reader->get($userId));
    }

    public function test_membership_list_and_gate_share_reads_and_revoke_immediately(): void
    {
        $userId = (int) $this->makeUser()['id'];
        $boardId = (int) $this->makeBoard($this->makeCategory())['id'];
        $reader = new BoardMemberRepository($this->db);
        $writer = new BoardMemberRepository($this->db);
        $writer->add($boardId, $userId, null);
        $this->db->beginRequestCache();
        $this->db->resetMetrics();

        self::assertTrue($reader->isMember($boardId, $userId));
        self::assertSame([$boardId], $writer->boardIdsFor($userId));
        self::assertFalse($reader->isMember($boardId + 1, $userId));
        self::assertSame(1, $this->db->metrics()['queries']);
        $writer->remove($boardId, $userId);
        self::assertFalse($reader->isMember($boardId, $userId));
        self::assertSame([], $reader->boardIdsFor($userId));
        $writer->add($boardId, $userId, null);
        self::assertTrue($reader->isMember($boardId, $userId));
    }

    public function test_ordered_boards_share_reads_and_refresh_after_update(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Before']);
        $reader = new BoardRepository($this->db);
        $this->db->beginRequestCache();
        $this->db->resetMetrics();
        $before = $reader->allOrdered();
        self::assertSame($before, (new BoardRepository($this->db))->allOrdered());
        self::assertSame(1, $this->db->metrics()['queries']);

        $this->db->run('UPDATE boards SET name = ? WHERE id = ?', ['After', $board['id']]);
        $after = array_column($reader->allOrdered(), null, 'id');
        self::assertSame('After', $after[$board['id']]['name']);
    }

    public function test_moderation_scope_shares_reads_across_instances_and_revokes_immediately(): void
    {
        $userId = (int) $this->makeUser()['id'];
        $otherUserId = (int) $this->makeUser()['id'];
        $boardId = (int) $this->makeBoard($this->makeCategory())['id'];
        $reader = new BoardModeratorRepository($this->db);
        $writer = new BoardModeratorRepository($this->db);
        $writer->assign($boardId, $userId);
        $this->db->beginRequestCache();
        $this->db->resetMetrics();

        self::assertTrue($reader->isModerator($boardId, $userId));
        self::assertSame([$boardId], $writer->boardsFor($userId));
        self::assertFalse($reader->isModerator($boardId + 1, $userId));
        self::assertSame(1, $this->db->metrics()['queries']);
        self::assertFalse($reader->isModerator($boardId, $otherUserId));
        $writer->unassign($boardId, $userId);
        self::assertFalse($reader->isModerator($boardId, $userId));
        self::assertSame([], $reader->boardsFor($userId));
        $writer->assign($boardId, $userId);
        self::assertTrue($reader->isModerator($boardId, $userId));
    }

    public function test_job_reads_cache_misses_but_locking_reads_always_reach_the_database(): void
    {
        $user = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $threadId = $this->makeThread($board, $user)['thread_id'];
        $reader = new ThreadIntelligenceJobRepository($this->db);
        $writer = new ThreadIntelligenceJobRepository($this->db);
        $this->db->run('DELETE FROM thread_intelligence_jobs WHERE thread_id = ?', [$threadId]);
        $this->db->beginRequestCache();
        $this->db->resetMetrics();
        self::assertNull($reader->find($threadId));
        self::assertNull($writer->find($threadId));
        self::assertSame(1, $this->db->metrics()['queries']);

        $writer->upsertStale($threadId, 'test', null, new DateTimeImmutable('now'));
        self::assertSame('queued', $reader->find($threadId)['state']);
        $this->db->resetMetrics();
        self::assertSame('queued', $reader->findForUpdate($threadId)['state']);
        self::assertSame('queued', $reader->findForUpdate($threadId)['state']);
        self::assertSame(2, $this->db->metrics()['queries']);
        $writer->requireReconcile($threadId);
        self::assertSame(1, (int) $reader->find($threadId)['reconcile_required']);
    }
}
