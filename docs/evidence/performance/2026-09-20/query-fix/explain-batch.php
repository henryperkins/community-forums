<?php

declare(strict_types=1);

// Local diagnostic: captures the repository's actual SQL, uses only rollback
// fixtures in the test database, and emits no connection configuration.
require dirname(__DIR__, 5) . '/tests/bootstrap.php';

final class BatchPlanStatement extends PDOStatement
{
    public static array $lastQuery = [];

    public function execute(?array $params = null): bool
    {
        self::$lastQuery = ['sql' => $this->queryString, 'params' => $params ?? []];
        return parent::execute($params);
    }
}

final class BatchPlanProbe extends Tests\Support\TestCase
{
    public function measure(): array
    {
        $this->setUp();
        $result = [];
        $threadId = 0;
        try {
            $this->makeAdmin();
            $author = $this->makeUser();
            $board = $this->makeBoard($this->makeCategory('Batch plan fixture'));
            $thread = $this->makeThread($board, $author);
            $threadId = (int) $thread['thread_id'];
            $insert = $this->pdo->prepare(
                'INSERT INTO posts (thread_id, user_id, body, body_html, created_at, is_deleted, is_pending)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
            );
            $postIds = [];
            for ($i = 1; $i <= 1000; $i++) {
                $insert->execute([
                    $threadId, (int) $author['id'], 'Plan fixture', '<p>Plan fixture</p>',
                    gmdate('Y-m-d H:i:s', strtotime('2026-09-01 00:00:00 UTC') + $i),
                    $i % 10 === 0 ? 1 : 0, $i % 17 === 0 ? 1 : 0,
                ]);
                if (in_array($i, [101, 301, 501, 701, 901, 999], true)) {
                    $postIds[] = (int) $this->pdo->lastInsertId();
                }
            }
            $result = [
                'database_engine' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                'fixture_posts' => 1001,
                'target_posts' => count($postIds),
                'scope' => 'Local MariaDB rollback fixtures; not a production timing measurement.',
                'plans' => [],
            ];
            $repo = new App\Repository\PostRepository($this->db);
            foreach ([false, true] as $includeDeleted) {
                $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [BatchPlanStatement::class]);
                $this->db->resetMetrics();
                $pages = $repo->pagesOfPosts($threadId, $postIds, 20, $includeDeleted);
                $metrics = $this->db->metrics();
                $query = BatchPlanStatement::$lastQuery;
                $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class]);
                $plan = $this->pdo->prepare('EXPLAIN FORMAT=JSON ' . $query['sql']);
                $plan->execute($query['params']);
                $analyze = $this->pdo->prepare('ANALYZE FORMAT=JSON ' . $query['sql']);
                $analyze->execute($query['params']);
                $result['plans'][$includeDeleted ? 'staff' : 'member'] = [
                    'queries' => $metrics['queries'],
                    'pages' => array_values($pages),
                    'sql' => $query['sql'],
                    'explain' => json_decode((string) $plan->fetchColumn(), true, flags: JSON_THROW_ON_ERROR),
                    'analyze' => json_decode((string) $analyze->fetchColumn(), true, flags: JSON_THROW_ON_ERROR),
                ];
            }
        } finally {
            $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class]);
            $this->tearDown();
            $check = $this->pdo->prepare('SELECT COUNT(*) FROM posts WHERE thread_id = ?');
            $check->execute([$threadId]);
            $result['remaining_fixture_posts'] = (int) $check->fetchColumn();
            if ($result['remaining_fixture_posts'] !== 0) {
                throw new RuntimeException('Fixture rollback failed.');
            }
        }
        return $result;
    }
}

echo json_encode((new BatchPlanProbe('probe'))->measure(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
