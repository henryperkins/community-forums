<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\IdempotencyRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use App\Security\BoardAuthority;
use App\Security\BoardPolicy;
use App\Security\WriteGate;
use App\Service\AdminService;
use App\Service\CommunityMemoryService;
use App\Service\ModerationService;
use App\Service\PostingService;
use App\Service\ThreadReadService;
use App\Service\ThreadIntelligence\ThreadIntelligenceBoardSweep;
use App\Service\ThreadIntelligence\ThreadIntelligenceBudget;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceEligibility;
use App\Service\ThreadIntelligence\ThreadIntelligenceQueue;
use App\Service\ThreadIntelligence\ThreadIntelligenceSettings;
use App\Service\ThreadSplitMergeService;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use PDO;
use PDOException;
use Tests\Support\TestCase;

final class ThreadIntelligenceEnqueueIntegrationTest extends TestCase
{
    private bool $committedFixtures = false;

    protected function setUp(): void
    {
        parent::setUp();
        (new SettingRepository($this->db))->set('features', [
            'community_memory' => true,
            'automated_context' => true,
            'split_merge' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->committedFixtures) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $preserve = [
                'schema_migrations', 'badges', 'roles', 'identity_providers', 'provider_aliases',
                'capabilities', 'role_capabilities', 'theme_state',
            ];
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
                if (!in_array($table, $preserve, true)) {
                    $this->pdo->exec('TRUNCATE TABLE `' . str_replace('`', '', (string) $table) . '`');
                }
            }
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $this->committedFixtures = false;
        }
        parent::tearDown();
    }

    public function test_public_reply_and_pending_approvals_enqueue_but_rejected_held_content_does_not(): void
    {
        $queue = $this->queue();
        $posting = $this->postingWithQueue($queue);

        $seed = $this->seedThread(7);
        self::assertNull($this->job($seed['thread_id']));
        $posting->reply($this->userEntity($seed['author']), $seed['thread_id'], ['body' => 'Eighth public post']);
        $job = $this->job($seed['thread_id']);
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_POST_CREATED, $job['trigger_code']);
        self::assertSame(1, (int) $job['activity_version']);
        self::assertSame(0, (int) $job['reconcile_required']);

        $pendingThread = $this->seedThread(8);
        $opId = $pendingThread['post_ids'][0];
        $this->db->run('UPDATE threads SET is_pending = 1 WHERE id = ?', [$pendingThread['thread_id']]);
        $this->db->run('UPDATE posts SET is_pending = 1 WHERE id = ?', [$opId]);
        self::assertTrue($posting->approvePendingThread($pendingThread['thread_id']));
        $approvedThreadJob = $this->job($pendingThread['thread_id']);
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_POST_APPROVED, $approvedThreadJob['trigger_code']);
        self::assertSame(1, (int) $approvedThreadJob['reconcile_required']);

        $pendingPost = $this->seedThread(7);
        $heldId = $this->insertPost($pendingPost['thread_id'], (int) $pendingPost['author']['id'], 'Held eighth post', pending: true);
        self::assertTrue($posting->approvePendingPost($heldId));
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_POST_APPROVED, $this->job($pendingPost['thread_id'])['trigger_code']);

        $rejected = $this->seedThread(7);
        $rejectedId = $this->insertPost($rejected['thread_id'], (int) $rejected['author']['id'], 'Rejected held post', pending: true);
        self::assertTrue($posting->rejectPendingPost($rejectedId, (int) $rejected['author']['id']));
        self::assertNull($this->job($rejected['thread_id']));
    }

    public function test_only_body_changes_enqueue_edits_and_duplicate_submissions_increment_once(): void
    {
        $queue = $this->queue();
        $posting = $this->postingWithQueue($queue);
        $seed = $this->seedThread(8);
        $queue->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED);
        $this->checkpoint($seed['thread_id']);

        $postId = $seed['post_ids'][1];
        $body = (string) $this->posts()->find($postId)['body'];
        $posting->editOwnPost($this->userEntity($seed['author']), $postId, ['body' => $body]);
        self::assertSame(1, (int) $this->job($seed['thread_id'])['activity_version']);

        $posting->editOwnPost($this->userEntity($seed['author']), $postId, ['body' => 'Changed canonical body']);
        $edited = $this->job($seed['thread_id']);
        self::assertSame(2, (int) $edited['activity_version']);
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_POST_EDITED, $edited['trigger_code']);
        self::assertSame(1, (int) $edited['reconcile_required']);

        for ($i = 1; $i <= 5; $i++) {
            $posting->reply(
                $this->userEntity($seed['author']),
                $seed['thread_id'],
                ['body' => 'Routine post after reconciliation ' . $i],
            );
        }
        self::assertSame(1, (int) $this->job($seed['thread_id'])['reconcile_required'], 'routine posts must not clear reconciliation intent');

        $idempotent = $this->seedThread(7);
        $input = ['body' => 'Exactly once reply', 'idempotency_key' => 'enqueue-idempotency-key'];
        $first = $posting->reply($this->userEntity($idempotent['author']), $idempotent['thread_id'], $input);
        $second = $posting->reply($this->userEntity($idempotent['author']), $idempotent['thread_id'], $input);
        self::assertSame($first, $second);
        self::assertSame(1, (int) $this->job($idempotent['thread_id'])['activity_version']);
    }

    public function test_owner_and_moderator_delete_restore_hooks_force_reconciliation(): void
    {
        $queue = $this->queue();
        $posting = $this->postingWithQueue($queue);
        $moderation = $this->moderation($posting, $queue);
        $seed = $this->seedThread(10);
        $queue->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED);
        $this->checkpoint($seed['thread_id']);

        $posting->deleteOwnPost($this->userEntity($seed['author']), $seed['post_ids'][1]);
        $ownDelete = $this->job($seed['thread_id']);
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_POST_DELETED, $ownDelete['trigger_code']);
        self::assertSame(1, (int) $ownDelete['reconcile_required']);

        $admin = $this->makeAdmin();
        $moderation->deletePost($this->userEntity($admin), $seed['post_ids'][2], 'test removal');
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_POST_DELETED, $this->job($seed['thread_id'])['trigger_code']);
        $afterDelete = (int) $this->job($seed['thread_id'])['activity_version'];

        $moderation->restorePost($this->userEntity($admin), $seed['post_ids'][2], 'test restore');
        $restored = $this->job($seed['thread_id']);
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_POST_RESTORED, $restored['trigger_code']);
        self::assertSame($afterDelete + 1, (int) $restored['activity_version']);
        self::assertSame(1, (int) $restored['reconcile_required']);
    }

    public function test_wiki_edit_and_revert_enqueue_after_the_post_update(): void
    {
        $queue = $this->queue();
        $seed = $this->seedThread(8);
        $this->db->run('UPDATE boards SET wiki_enabled = 1 WHERE id = ?', [(int) $seed['board']['id']]);
        $memory = $this->memory($queue);
        $actor = $this->userEntity($this->makeAdmin());
        $postId = $seed['post_ids'][1];
        $memory->makeWiki($actor, $postId);
        $revisionId = (int) $this->db->fetchValue('SELECT MIN(id) FROM post_revisions WHERE post_id = ?', [$postId]);

        $queue->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED);
        $this->checkpoint($seed['thread_id']);
        $memory->editWiki($actor, $postId, 'Updated wiki evidence', 'clarify');
        $edited = $this->job($seed['thread_id']);
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_WIKI_EDITED, $edited['trigger_code']);
        self::assertSame('Updated wiki evidence', $this->posts()->find($postId)['body']);

        $memory->revertWiki($actor, $postId, $revisionId);
        $reverted = $this->job($seed['thread_id']);
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_WIKI_REVERTED, $reverted['trigger_code']);
        self::assertSame(3, (int) $reverted['activity_version']);
        self::assertSame(1, (int) $reverted['reconcile_required']);
    }

    public function test_public_and_private_destination_moves_reconcile_or_idle_current_work(): void
    {
        $queue = $this->queue();
        $posting = $this->postingWithQueue($queue);
        $moderation = $this->moderation($posting, $queue);
        $admin = $this->userEntity($this->makeAdmin());

        $public = $this->seedThread(8);
        $publicDestination = $this->makeBoard($this->makeCategory(), ['visibility' => 'public']);
        $queue->markStale($public['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED);
        $this->checkpoint($public['thread_id']);
        $moderation->moveThread($admin, $public['thread_id'], (int) $publicDestination['id']);
        $moved = $this->job($public['thread_id']);
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_THREAD_MOVED, $moved['trigger_code']);
        self::assertSame(1, (int) $moved['reconcile_required']);

        $private = $this->seedThread(8);
        $privateDestination = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $queue->markStale($private['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED);
        $moderation->moveThread($admin, $private['thread_id'], (int) $privateDestination['id']);
        $hidden = $this->job($private['thread_id']);
        self::assertSame('idle', $hidden['state']);
        self::assertNull($hidden['due_at']);
        self::assertSame(1, (int) $hidden['reconcile_required']);
    }

    public function test_split_and_merge_force_reconciliation_for_every_surviving_thread(): void
    {
        $queue = $this->queue();
        $posting = $this->postingWithQueue($queue);
        $moderation = $this->moderation($posting, $queue);
        $service = new ThreadSplitMergeService(
            db: $this->db,
            threads: new ThreadRepository($this->db),
            posts: new PostRepository($this->db),
            moderation: $moderation,
            logs: new ModerationLogRepository($this->db),
            readService: $this->readService(),
            boards: new BoardRepository($this->db),
            threadIntelligence: $queue,
        );
        $actor = $this->userEntity($this->makeAdmin());

        $split = $this->seedThread(16);
        $queue->markStale($split['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED);
        $this->checkpoint($split['thread_id']);
        $new = $service->split($actor, $split['thread_id'], array_slice($split['post_ids'], 8, 8), 'Split evidence topic');
        $sourceJob = $this->job($split['thread_id']);
        $newJob = $this->job((int) $new['id']);
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_THREAD_SPLIT, $sourceJob['trigger_code']);
        self::assertSame(1, (int) $sourceJob['reconcile_required']);
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_THREAD_SPLIT, $newJob['trigger_code']);
        self::assertSame(1, (int) $newJob['reconcile_required']);

        $mergeSource = $this->seedThread(8);
        $mergeTarget = $this->seedThread(8);
        $queue->markStale($mergeSource['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED);
        $queue->markStale($mergeTarget['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED);
        $this->checkpoint($mergeTarget['thread_id']);
        $service->merge($actor, $mergeSource['thread_id'], $mergeTarget['thread_id']);
        self::assertSame('idle', $this->job($mergeSource['thread_id'])['state']);
        $targetJob = $this->job($mergeTarget['thread_id']);
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_THREAD_MERGED, $targetJob['trigger_code']);
        self::assertSame(1, (int) $targetJob['reconcile_required']);
    }

    public function test_curator_refresh_is_durable_and_board_visibility_uses_only_the_sweep_marker(): void
    {
        $queue = $this->queue();
        $seed = $this->seedThread(8);
        $result = $queue->requestRefresh($seed['thread_id']);
        self::assertTrue($result->queued);
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_CURATOR_REFRESH, $this->job($seed['thread_id'])['trigger_code']);

        $admin = $this->userEntity($this->makeAdmin());
        $service = new AdminService(
            db: $this->db,
            categories: new CategoryRepository($this->db),
            boards: new BoardRepository($this->db),
            settings: new SettingRepository($this->db),
            log: new ModerationLogRepository($this->db),
            writeGate: new WriteGate(),
            users: new UserRepository($this->db),
            boardMods: new BoardModeratorRepository($this->db),
            boardMembers: new BoardMemberRepository($this->db),
            threadIntelligenceBoardSweep: new ThreadIntelligenceBoardSweep($this->db),
        );
        $before = $this->job($seed['thread_id']);
        $service->updateBoard($admin, (int) $seed['board']['id'], $this->boardInput($seed['board'], 'private'));
        $after = $this->job($seed['thread_id']);
        self::assertSame((int) $before['activity_version'], (int) $after['activity_version'], 'visibility writes must not enumerate thread jobs');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT thread_intelligence_sweep_after_id FROM boards WHERE id = ?', [(int) $seed['board']['id']]));

        $this->db->run('UPDATE boards SET thread_intelligence_sweep_after_id = 99 WHERE id = ?', [(int) $seed['board']['id']]);
        $freshBoard = $this->boards()->find((int) $seed['board']['id']);
        $service->updateBoard($admin, (int) $seed['board']['id'], $this->boardInput($freshBoard, 'private'));
        self::assertSame(99, (int) $this->db->fetchValue('SELECT thread_intelligence_sweep_after_id FROM boards WHERE id = ?', [(int) $seed['board']['id']]), 'unchanged visibility must not touch the marker');
    }

    public function test_a_late_queue_failure_rolls_back_content_and_queue_together(): void
    {
        $queue = $this->queue();
        $posting = $this->postingWithQueue($queue);
        $seed = $this->seedThread(8);
        $queue->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED);
        $this->checkpoint($seed['thread_id']);
        for ($i = 1; $i <= 4; $i++) {
            $this->insertPost($seed['thread_id'], (int) $seed['author']['id'], 'Pre-existing delta ' . $i);
        }
        $threadId = $seed['thread_id'];
        $author = $this->userEntity($seed['author']);
        $beforeVersion = (int) $this->job($threadId)['activity_version'];
        $this->committedFixtures = true;

        $this->withoutHarnessTransaction(function () use ($posting, $threadId, $author, $beforeVersion): void {
            $this->pdo->exec('DROP TRIGGER IF EXISTS ti_enqueue_forced_failure');
            $this->pdo->exec(<<<'SQL'
                CREATE TRIGGER ti_enqueue_forced_failure
                BEFORE UPDATE ON thread_intelligence_jobs
                FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced enqueue failure'
                SQL);
            try {
                try {
                    $posting->reply($author, $threadId, ['body' => 'Must roll back with queue']);
                    self::fail('Expected the forced queue failure.');
                } catch (PDOException $exception) {
                    self::assertStringContainsString('forced enqueue failure', $exception->getMessage());
                }

                $other = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
                self::assertSame(0, (int) $other->fetchValue('SELECT COUNT(*) FROM posts WHERE thread_id = ? AND body = ?', [$threadId, 'Must roll back with queue']));
                self::assertSame($beforeVersion, (int) (new ThreadIntelligenceJobRepository($other))->find($threadId)['activity_version']);
            } finally {
                $this->pdo->exec('DROP TRIGGER IF EXISTS ti_enqueue_forced_failure');
            }
        });
    }

    private function queue(): ThreadIntelligenceQueue
    {
        $apiKey = 'sk-test-thread-intelligence-enqueue';
        $config = ThreadIntelligenceConfig::fromArray(['api_key' => $apiKey]);
        $jobs = new ThreadIntelligenceJobRepository($this->db);
        $settings = new ThreadIntelligenceSettings(
            new SettingRepository($this->db),
            $config,
            (string) $this->config->get('app.key'),
            $apiKey,
            $this->db,
        );
        $eligibility = new ThreadIntelligenceEligibility(
            $this->db,
            new FeatureFlags(new SettingRepository($this->db)),
            $config,
            $settings,
            new ThreadIntelligenceBudget($this->db, $config),
            $jobs,
        );
        return new ThreadIntelligenceQueue($this->db, $jobs, $eligibility);
    }

    private function postingWithQueue(ThreadIntelligenceQueue $queue): PostingService
    {
        return new PostingService(
            db: $this->db,
            threads: new ThreadRepository($this->db),
            posts: new PostRepository($this->db),
            boards: new BoardRepository($this->db),
            users: new UserRepository($this->db),
            markdown: new Markdown(new HtmlSanitizer()),
            writeGate: new WriteGate(),
            policy: new BoardPolicy(),
            config: $this->config,
            idempotency: new IdempotencyRepository($this->db),
            threadIntelligence: $queue,
        );
    }

    private function moderation(PostingService $posting, ThreadIntelligenceQueue $queue): ModerationService
    {
        return new ModerationService(
            db: $this->db,
            threads: new ThreadRepository($this->db),
            posts: new PostRepository($this->db),
            log: new ModerationLogRepository($this->db),
            posting: $posting,
            writeGate: new WriteGate(),
            boardMods: new BoardModeratorRepository($this->db),
            boards: new BoardRepository($this->db),
            users: new UserRepository($this->db),
            boardAuthority: $this->boardAuthority(),
            readService: $this->readService(),
            threadIntelligence: $queue,
        );
    }

    private function boardAuthority(): BoardAuthority
    {
        return new BoardAuthority(new WriteGate(), new BoardModeratorRepository($this->db), new BoardRepository($this->db));
    }

    private function readService(): ThreadReadService
    {
        return new ThreadReadService(
            new ThreadRepository($this->db),
            new BoardPolicy(),
            new BoardMemberRepository($this->db),
            $this->boardAuthority(),
        );
    }

    private function memory(ThreadIntelligenceQueue $queue): CommunityMemoryService
    {
        return new CommunityMemoryService(
            db: $this->db,
            threads: new ThreadRepository($this->db),
            posts: new PostRepository($this->db),
            moderators: new BoardModeratorRepository($this->db),
            members: new BoardMemberRepository($this->db),
            policy: new BoardPolicy(),
            writeGate: new WriteGate(),
            markdown: new Markdown(new HtmlSanitizer()),
            threadIntelligence: $queue,
        );
    }

    /** @return array{thread_id:int,board:array<string,mixed>,author:array<string,mixed>,post_ids:list<int>} */
    private function seedThread(int $postCount): array
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author);
        $threadId = (int) $thread['thread_id'];
        $postIds = [(int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId])];
        for ($i = 1; $i < $postCount; $i++) {
            $postIds[] = $this->insertPost($threadId, (int) $author['id'], 'Seed post ' . $i);
        }
        return ['thread_id' => $threadId, 'board' => $board, 'author' => $author, 'post_ids' => $postIds];
    }

    private function insertPost(int $threadId, int $authorId, string $body, bool $pending = false): int
    {
        return $this->db->insert(
            'INSERT INTO posts
                (thread_id, user_id, body, body_html, is_op, is_anonymous, is_deleted, is_pending, created_at)
             VALUES (?, ?, ?, ?, 0, 0, 0, ?, UTC_TIMESTAMP())',
            [$threadId, $authorId, $body, '<p>' . $body . '</p>', $pending ? 1 : 0],
        );
    }

    /** @return array<string,mixed>|null */
    private function job(int $threadId): ?array
    {
        return (new ThreadIntelligenceJobRepository($this->db))->find($threadId);
    }

    private function checkpoint(int $threadId): void
    {
        $this->db->run(
            "UPDATE thread_intelligence_jobs
             SET state = 'idle', due_at = NULL,
                 last_processed_post_id = (SELECT MAX(id) FROM posts WHERE thread_id = ?)
             WHERE thread_id = ?",
            [$threadId, $threadId],
        );
    }

    /** @param array<string,mixed> $board @return array<string,mixed> */
    private function boardInput(array $board, string $visibility): array
    {
        return [
            'category_id' => (int) $board['category_id'],
            'name' => (string) $board['name'],
            'slug' => (string) $board['slug'],
            'description' => (string) ($board['description'] ?? ''),
            'visibility' => $visibility,
            'post_min_role' => (string) ($board['post_min_role'] ?? 'user'),
            'allow_anonymous' => (int) ($board['allow_anonymous'] ?? 0),
            'require_approval' => (int) ($board['require_approval'] ?? 0),
            'assignment_mode' => (string) ($board['assignment_mode'] ?? 'off'),
            'tags_enabled' => (int) ($board['tags_enabled'] ?? 1),
            'wiki_enabled' => (int) ($board['wiki_enabled'] ?? 0),
        ];
    }
}
