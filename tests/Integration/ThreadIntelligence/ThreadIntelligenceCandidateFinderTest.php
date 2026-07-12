<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Core\App;
use App\Core\Database;
use App\Repository\TagRepository;
use App\Security\ArrayRateLimiter;
use App\Service\ThreadIntelligence\ThreadIntelligenceCandidateFinder;
use App\Service\ThreadIntelligence\ThreadIntelligenceRelatedCandidate;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\TestCase;

/**
 * InnoDB FULLTEXT does not index rows in the normal rollback harness. These
 * fixtures are committed and the shared database is reset before and after
 * every test, matching AppSearchTest's dedicated nonparallel pattern.
 */
#[Group('nonparallel')]
final class ThreadIntelligenceCandidateFinderTest extends TestCase
{
    protected function setUp(): void
    {
        // Deliberately skip parent::setUp(): FULLTEXT fixtures must autocommit.
        $this->pdo = $GLOBALS['__RB_TEST_PDO'];
        $this->config = $GLOBALS['__RB_TEST_CONFIG'];
        $this->db = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $this->db->setPdo($this->pdo);
        $this->resetDatabase();
        $this->rateLimiter = new ArrayRateLimiter();
        $this->app = new App($this->config, $this->db, $this->rateLimiter);
        $this->cookies = [];
        $this->csrfSecret = null;
        $this->makeAdmin();
    }

    protected function tearDown(): void
    {
        $this->resetDatabase();
    }

    private function resetDatabase(): void
    {
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
                $quoted = str_replace('`', '', (string) $table);
                $this->pdo->exec("TRUNCATE TABLE `$quoted`");
            }
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private function finder(): ThreadIntelligenceCandidateFinder
    {
        return new ThreadIntelligenceCandidateFinder($this->db);
    }

    public function test_rank_order_is_stable_across_tags_relevance_scope_activity_and_id_ties(): void
    {
        $author = $this->makeUser();
        $category = $this->makeCategory('Source category');
        $otherCategory = $this->makeCategory('Other category');
        $sourceBoard = $this->makeBoard($category, ['name' => 'Source board']);
        $sameCategoryBoard = $this->makeBoard($category, ['name' => 'Sibling board']);
        $otherBoard = $this->makeBoard($otherCategory, ['name' => 'Other board']);

        $source = $this->makeThread(
            $sourceBoard,
            $author,
            'Quasarforge calibration',
            'The source discussion establishes the calibration problem.',
        );

        $twoTags = $this->candidate($otherBoard, $author, 'Twin-tag context', 'No lexical overlap.', '2026-07-01 00:00:00');
        $highRelevance = $this->candidate($otherBoard, $author, 'Quasarforge calibration troubleshooting', 'Quasarforge calibration details.', '2026-06-01 00:00:00');
        $lowRelevance = $this->candidate($sourceBoard, $author, 'Single-tag context', 'No lexical overlap.', '2026-07-09 00:00:00');
        $sameBoardNewest = $this->candidate($sourceBoard, $author, 'Scope newest', 'No lexical overlap.', '2026-07-08 00:00:00');
        $sameBoardTieFirst = $this->candidate($sourceBoard, $author, 'Scope tie one', 'No lexical overlap.', '2026-07-07 00:00:00');
        $sameBoardTieSecond = $this->candidate($sourceBoard, $author, 'Scope tie two', 'No lexical overlap.', '2026-07-07 00:00:00');
        $sameCategory = $this->candidate($sameCategoryBoard, $author, 'Category context', 'No lexical overlap.', '2026-07-10 00:00:00');
        $other = $this->candidate($otherBoard, $author, 'Remote context', 'No lexical overlap.', '2026-07-10 00:00:00');

        $tags = new TagRepository($this->db);
        $alpha = $tags->create('alpha', 'Alpha', null, (int) $author['id']);
        $beta = $tags->create('beta', 'Beta', null, (int) $author['id']);
        $tags->setForThread((int) $source['thread_id'], [$alpha, $beta], (int) $author['id']);
        $tags->setForThread($twoTags, [$beta, $alpha], (int) $author['id']);
        $tags->setForThread($highRelevance, [$alpha], (int) $author['id']);
        $tags->setForThread($lowRelevance, [$alpha], (int) $author['id']);

        $results = $this->finder()->find((int) $source['thread_id']);

        self::assertContainsOnlyInstancesOf(ThreadIntelligenceRelatedCandidate::class, $results);
        self::assertSame(
            [
                $twoTags,
                $highRelevance,
                $lowRelevance,
                $sameBoardNewest,
                $sameBoardTieFirst,
                $sameBoardTieSecond,
                $sameCategory,
                $other,
            ],
            array_map(static fn (ThreadIntelligenceRelatedCandidate $candidate): int => $candidate->threadId, $results),
        );
        self::assertSame(['Alpha', 'Beta'], $results[0]->sharedTags, 'shared tags have a canonical name order');
        self::assertSame(2, $results[0]->sharedTagCount);
        self::assertGreaterThan($results[2]->relevance, $results[1]->relevance, 'FULLTEXT relevance wins before scope');
        self::assertSame(range(1, count($results)), array_column($results, 'rank'));
        self::assertLessThan($sameBoardTieSecond, $sameBoardTieFirst, 'the fixture must exercise ascending-ID tie order');
    }

    public function test_results_are_bounded_public_private_data_free_and_exclude_approved_curated_targets(): void
    {
        $author = $this->makeUser([
            'username' => 'account-sentinel',
            'email' => 'private-sentinel@example.test',
            'display_name' => 'Private Display Sentinel',
        ]);
        $admin = $this->makeAdmin();
        $category = $this->makeCategory();
        $public = $this->makeBoard($category, ['visibility' => 'public']);
        $private = $this->makeBoard($category, ['visibility' => 'private']);
        $hidden = $this->makeBoard($category, ['visibility' => 'hidden']);
        $source = $this->makeThread($public, $author, 'Nebulawrench alignment', 'Public source opener.');

        $tags = new TagRepository($this->db);
        $priority = $tags->create('priority', 'Priority', null, (int) $author['id']);
        $tags->setForThread((int) $source['thread_id'], [$priority], (int) $author['id']);

        $longBody = "# Heading\n\n<script>bad()</script> **Visible opener** " . str_repeat('x', 700);
        $longExcerptTarget = $this->candidate($public, $author, 'Long public candidate', $longBody, '2026-07-10 00:00:00');
        $tags->setForThread($longExcerptTarget, [$priority], (int) $author['id']);

        $rejectedCurated = $this->candidate($public, $author, 'Rejected curated candidate', 'Still eligible.', '2026-07-09 00:00:00');
        $tags->setForThread($rejectedCurated, [$priority], (int) $author['id']);
        $this->db->run(
            "INSERT INTO related_threads
                (source_thread_id, related_thread_id, relation_type, source, status, created_at)
             VALUES (?, ?, 'related', 'curated', 'rejected', UTC_TIMESTAMP())",
            [(int) $source['thread_id'], $rejectedCurated],
        );

        $ordinary = [];
        for ($i = 0; $i < 25; $i++) {
            $ordinary[] = $this->candidate($public, $author, 'Public candidate ' . $i, 'Ordinary public opener ' . $i, sprintf('2026-06-%02d 00:00:00', 1 + ($i % 28)));
        }

        $curated = $ordinary[0];
        $this->db->run(
            "INSERT INTO related_threads
                (source_thread_id, related_thread_id, relation_type, source, status, created_at)
             VALUES (?, ?, 'related', 'curated', 'approved', UTC_TIMESTAMP())",
            [(int) $source['thread_id'], $curated],
        );

        $privateId = $this->candidate($private, $admin, 'PRIVATE TITLE SENTINEL', 'PRIVATE BODY SENTINEL', '2026-07-10 00:00:00');
        $hiddenId = $this->candidate($hidden, $admin, 'HIDDEN TITLE SENTINEL', 'HIDDEN BODY SENTINEL', '2026-07-10 00:00:00');
        $deletedId = $this->candidate($public, $author, 'DELETED TITLE SENTINEL', 'DELETED BODY SENTINEL', '2026-07-10 00:00:00');
        $pendingId = $this->candidate($public, $author, 'PENDING TITLE SENTINEL', 'PENDING BODY SENTINEL', '2026-07-10 00:00:00');
        $this->db->run('UPDATE threads SET is_deleted = 1 WHERE id = ?', [$deletedId]);
        $this->db->run('UPDATE threads SET is_pending = 1 WHERE id = ?', [$pendingId]);

        $results = $this->finder()->find((int) $source['thread_id'], 99);
        $ids = array_column($results, 'threadId');

        self::assertCount(20, $results, 'the hard candidate ceiling applies even when a larger limit is requested');
        self::assertNotContains((int) $source['thread_id'], $ids);
        self::assertNotContains($curated, $ids, 'approved curated relationships never enter the model candidate set');
        self::assertContains($rejectedCurated, $ids, 'only approved curated targets are excluded');
        foreach ([$privateId, $hiddenId, $deletedId, $pendingId] as $forbiddenId) {
            self::assertNotContains($forbiddenId, $ids);
        }

        $long = array_values(array_filter(
            $results,
            static fn (ThreadIntelligenceRelatedCandidate $candidate): bool => $candidate->threadId === $longExcerptTarget,
        ))[0];
        self::assertLessThanOrEqual(500, mb_strlen($long->excerpt));
        self::assertStringNotContainsString('<script>', $long->excerpt);
        self::assertStringNotContainsString('#', $long->excerpt);
        self::assertStringNotContainsString('**', $long->excerpt);

        $serialized = json_encode($results, JSON_THROW_ON_ERROR);
        foreach ([
            'account-sentinel', 'private-sentinel@example.test', 'Private Display Sentinel',
            'PRIVATE TITLE SENTINEL', 'PRIVATE BODY SENTINEL', 'HIDDEN TITLE SENTINEL', 'HIDDEN BODY SENTINEL',
        ] as $sentinel) {
            self::assertStringNotContainsString($sentinel, $serialized);
        }

        $properties = array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            (new \ReflectionClass($results[0]))->getProperties(),
        );
        sort($properties);
        self::assertSame(
            ['excerpt', 'lastActivityAtUtc', 'rank', 'relevance', 'sharedTagCount', 'sharedTags', 'threadId', 'title'],
            $properties,
        );
    }

    public function test_metadata_changes_only_affect_the_next_local_candidate_recomputation(): void
    {
        $author = $this->makeUser();
        $sourceCategory = $this->makeCategory('Source category');
        $targetCategory = $this->makeCategory('Target category');
        $sourceBoard = $this->makeBoard($sourceCategory);
        $targetBoard = $this->makeBoard($targetCategory);
        $source = $this->makeThread($sourceBoard, $author, 'Ionspindle calibration', 'Source opener.');
        $target = $this->candidate($targetBoard, $author, 'Unrelated title', 'Unrelated opener.', '2026-07-10 00:00:00');

        $tags = new TagRepository($this->db);
        $disabledShared = $tags->create('disabled-shared', 'Disabled shared', null, (int) $author['id']);
        $sourceOnly = $tags->create('source-only', 'Source only', null, (int) $author['id']);
        $targetOnly = $tags->create('target-only', 'Target only', null, (int) $author['id']);
        $tags->setForThread((int) $source['thread_id'], [$disabledShared, $sourceOnly], (int) $author['id']);
        $tags->setForThread($target, [$disabledShared, $targetOnly], (int) $author['id']);
        $this->db->run('UPDATE tags SET is_enabled = 0 WHERE id = ?', [$disabledShared]);

        $summaryId = $this->db->insert(
            "INSERT INTO thread_summaries
                (thread_id, kind, status, body, body_html, version, author_id, published_at, created_at)
             VALUES (?, 'manual', 'published', 'Durable curator baseline.', '<p>Durable curator baseline.</p>', 7, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [(int) $source['thread_id'], (int) $author['id']],
        );
        $storedHash = str_repeat('ab', 32);
        $this->db->run(
            "INSERT INTO thread_intelligence_jobs
                (thread_id, state, trigger_code, source_snapshot_hash, activity_version, created_at)
             VALUES (?, 'idle', 'post_created', ?, 3, UTC_TIMESTAMP())",
            [(int) $source['thread_id'], $storedHash],
        );

        $before = $this->finder()->find((int) $source['thread_id']);
        $beforeTarget = $this->byId($before, $target);
        self::assertSame(0, $beforeTarget->sharedTagCount);

        $this->db->run('UPDATE tags SET is_enabled = 1, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$disabledShared]);
        self::assertSame(1, $this->byId($this->finder()->find((int) $source['thread_id']), $target)->sharedTagCount);

        $tags->mergeInto($sourceOnly, $targetOnly);
        self::assertSame(2, $this->byId($this->finder()->find((int) $source['thread_id']), $target)->sharedTagCount);

        $this->db->run('UPDATE boards SET tags_enabled = 0 WHERE id = ?', [(int) $targetBoard['id']]);
        self::assertSame(0, $this->byId($this->finder()->find((int) $source['thread_id']), $target)->sharedTagCount);
        $this->db->run('UPDATE boards SET tags_enabled = 1, category_id = ? WHERE id = ?', [$sourceCategory, (int) $targetBoard['id']]);

        $this->db->run('UPDATE boards SET tags_enabled = 0 WHERE id = ?', [(int) $sourceBoard['id']]);
        self::assertSame(0, $this->byId($this->finder()->find((int) $source['thread_id']), $target)->sharedTagCount);
        $this->db->run('UPDATE boards SET tags_enabled = 1 WHERE id = ?', [(int) $sourceBoard['id']]);

        $this->db->run("UPDATE threads SET title = 'Ionspindle calibration follow-up' WHERE id = ?", [$target]);
        $afterTarget = $this->byId($this->finder()->find((int) $source['thread_id']), $target);
        self::assertSame(2, $afterTarget->sharedTagCount);
        self::assertGreaterThan($beforeTarget->relevance, $afterTarget->relevance);

        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_intelligence_generations'));
        self::assertSame(
            ['id' => $summaryId, 'body' => 'Durable curator baseline.', 'version' => 7, 'status' => 'published'],
            array_map(
                static fn (mixed $value): mixed => is_numeric($value) ? (int) $value : $value,
                $this->db->fetch('SELECT id, body, version, status FROM thread_summaries WHERE id = ?', [$summaryId]),
            ),
        );
        self::assertSame($storedHash, $this->db->fetchValue('SELECT source_snapshot_hash FROM thread_intelligence_jobs WHERE thread_id = ?', [(int) $source['thread_id']]));
        self::assertSame(3, (int) $this->db->fetchValue('SELECT activity_version FROM thread_intelligence_jobs WHERE thread_id = ?', [(int) $source['thread_id']]));
    }

    public function test_more_than_fifty_equal_fulltext_hits_are_ranked_before_any_internal_cutoff(): void
    {
        $author = $this->makeUser();
        $category = $this->makeCategory();
        $sourceBoard = $this->makeBoard($category, ['name' => 'Source board']);
        $siblingBoard = $this->makeBoard($category, ['name' => 'Sibling board']);
        $source = $this->makeThread($sourceBoard, $author, 'Cutoffquasar signal', 'Cutoffquasar signal source.');

        $siblingIds = [];
        for ($index = 0; $index < 55; $index++) {
            $siblingIds[] = $this->candidate(
                $siblingBoard,
                $author,
                'Cutoffquasar signal candidate',
                'Cutoffquasar signal candidate body.',
                '2026-07-01 00:00:00',
            );
        }
        $sameBoardIds = [];
        for ($index = 0; $index < 10; $index++) {
            $sameBoardIds[] = $this->candidate(
                $sourceBoard,
                $author,
                'Cutoffquasar signal candidate',
                'Cutoffquasar signal candidate body.',
                '2026-07-01 00:00:00',
            );
        }

        $first = $this->finder()->find((int) $source['thread_id']);
        $second = $this->finder()->find((int) $source['thread_id']);
        $expected = [...$sameBoardIds, ...array_slice($siblingIds, 0, 10)];

        self::assertSame($expected, array_column($first, 'threadId'), 'scope wins after equal complete relevance scores');
        self::assertSame(array_column($first, 'threadId'), array_column($second, 'threadId'), 'repeated retrieval is stable');
        self::assertSame(array_column($first, 'relevance'), array_column($second, 'relevance'));
        self::assertGreaterThan(0.0, $first[0]->relevance);
        self::assertSame($first[0]->relevance, $first[19]->relevance, 'no hit loses relevance at an internal presentation cutoff');
    }

    public function test_final_bounded_payload_read_drops_a_target_hidden_after_ranking(): void
    {
        $author = $this->makeUser();
        $category = $this->makeCategory();
        $sourceBoard = $this->makeBoard($category, ['visibility' => 'public']);
        $targetBoard = $this->makeBoard($category, ['visibility' => 'public']);
        $source = $this->makeThread($sourceBoard, $author, 'Racequasar source', 'Public source.');
        $target = $this->candidate(
            $targetBoard,
            $author,
            'RACE PRIVATE TITLE SENTINEL',
            'RACE PRIVATE OPENER SENTINEL',
            '2026-07-10 00:00:00',
        );

        $childCode = <<<'PHP'
require getenv('RB_ROOT') . '/vendor/autoload.php';
$config = json_decode(base64_decode((string) getenv('RB_CHILD_DB')), true, 512, JSON_THROW_ON_ERROR);
$db = new App\Core\Database($config);
$db->run('SET SESSION innodb_lock_wait_timeout = 10');
$db->pdo()->beginTransaction();
$db->run("UPDATE boards SET visibility = 'hidden' WHERE id = ?", [(int) getenv('RB_BOARD_ID')]);
echo "HIDDEN_UNCOMMITTED\n";
fflush(STDOUT);
usleep(1000000);
$db->pdo()->commit();
echo "COMMITTED\n";
PHP;
        $process = proc_open(
            [PHP_BINARY, '-r', $childCode],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            [
                'RB_ROOT' => dirname(__DIR__, 3),
                'RB_CHILD_DB' => base64_encode(json_encode($GLOBALS['__RB_TEST_DBCONFIG'], JSON_THROW_ON_ERROR)),
                'RB_BOARD_ID' => (string) $targetBoard['id'],
            ] + getenv(),
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        self::assertSame("HIDDEN_UNCOMMITTED\n", fgets($pipes[1]));

        $started = microtime(true);
        $results = $this->finder()->find((int) $source['thread_id']);
        $elapsed = microtime(true) - $started;

        stream_set_timeout($pipes[1], 5);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, $stderr);
        self::assertStringContainsString('COMMITTED', $stdout);
        self::assertGreaterThanOrEqual(0.5, $elapsed, 'the current-state payload read waits for the visibility writer');
        self::assertNotContains($target, array_column($results, 'threadId'));
        $serialized = json_encode($results, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('RACE PRIVATE TITLE SENTINEL', $serialized);
        self::assertStringNotContainsString('RACE PRIVATE OPENER SENTINEL', $serialized);
    }

    /** @param list<ThreadIntelligenceRelatedCandidate> $candidates */
    private function byId(array $candidates, int $threadId): ThreadIntelligenceRelatedCandidate
    {
        foreach ($candidates as $candidate) {
            if ($candidate->threadId === $threadId) {
                return $candidate;
            }
        }
        self::fail('candidate ' . $threadId . ' was not returned');
    }

    /** @param array<string,mixed> $board @param array<string,mixed> $author */
    private function candidate(array $board, array $author, string $title, string $body, string $activity): int
    {
        $thread = $this->makeThread($board, $author, $title, $body);
        $this->db->run('UPDATE threads SET last_post_at = ? WHERE id = ?', [$activity, (int) $thread['thread_id']]);
        return (int) $thread['thread_id'];
    }
}
