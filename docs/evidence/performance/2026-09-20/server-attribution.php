<?php

declare(strict_types=1);

// Diagnostic-only instrumentation. This loads an in-memory copy of Database;
// product source files are never rewritten. No credentials or SQL parameters
// are emitted. All fixture writes live inside TestCase's rollback transaction.
// Run separately from PHPUnit: php docs/evidence/performance/2026-09-20/server-attribution.php
$root = dirname(__DIR__, 4);
$GLOBALS['server_trace'] = null;

function serverTraceStack(): array
{
    $root = dirname(__DIR__, 4) . '/';
    return array_values(array_map(static fn (array $frame): array => [
        'call' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
        'site' => str_replace($root, '', (string) ($frame['file'] ?? '')) . ':' . ($frame['line'] ?? 0),
    ], array_filter(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 32), static fn (array $frame): bool =>
        isset($frame['class']) && ($frame['class'] !== 'App\\Core\\Database'
            || str_starts_with((string) ($frame['file'] ?? ''), $root . 'src/'))
    )));
}

function serverTraceQuery(string $sql, array $params, float $elapsed, array $stack): void
{
    if ($GLOBALS['server_trace'] === null) {
        return;
    }
    $GLOBALS['server_trace']['queries'][] = [
        'sequence' => count($GLOBALS['server_trace']['queries']) + 1,
        'sql' => preg_replace('/\\s+/', ' ', trim($sql)),
        'params_fingerprint' => hash('sha256', serialize($params)),
        'query_ms' => round($elapsed, 6),
        'stack' => $stack,
    ];
}

function serverTraceClear(array $keys): void
{
    if ($GLOBALS['server_trace'] !== null) {
        $GLOBALS['server_trace']['invalidations'][] = [
            'after_query' => count($GLOBALS['server_trace']['queries']),
            'keys' => $keys,
            'stack' => serverTraceStack(),
        ];
    }
}

function serverTraceRemember(string $key, bool $hit): void
{
    if ($GLOBALS['server_trace'] !== null) {
        $GLOBALS['server_trace']['cache'][] = [
            'after_query' => count($GLOBALS['server_trace']['queries']),
            'key' => $key,
            'hit' => $hit,
        ];
    }
}

$source = (string) file_get_contents($root . '/src/Core/Database.php');
$patches = [
    '        $this->requestCache = [];' => '        \\serverTraceClear(array_keys($this->requestCache));' . "\n" . '        $this->requestCache = [];',
    '        if (!array_key_exists($key, $this->requestCache)) {' => '        \\serverTraceRemember($key, array_key_exists($key, $this->requestCache));' . "\n" . '        if (!array_key_exists($key, $this->requestCache)) {',
    "        \$startedAt = hrtime(true);\n        \$this->queryCount++;\n        try {\n            \$stmt" => "        \$traceStack = \$GLOBALS['server_trace'] !== null ? \\serverTraceStack() : [];\n        \$startedAt = hrtime(true);\n        \$this->queryCount++;\n        try {\n            \$stmt",
    "            return \$stmt;\n        } finally {\n            \$this->queryDurationMs += (hrtime(true) - \$startedAt) / 1_000_000;" => "            return \$stmt;\n        } finally {\n            \$elapsed = (hrtime(true) - \$startedAt) / 1_000_000;\n            \$this->queryDurationMs += \$elapsed;\n            \\serverTraceQuery(\$sql, \$params, \$elapsed, \$traceStack);",
    "        } catch (PDOException) {\n            return false;\n        } finally {\n            \$this->queryDurationMs += (hrtime(true) - \$startedAt) / 1_000_000;" => "        } catch (PDOException) {\n            return false;\n        } finally {\n            \$elapsed = (hrtime(true) - \$startedAt) / 1_000_000;\n            \$this->queryDurationMs += \$elapsed;\n            \\serverTraceQuery('SELECT 1', [], \$elapsed, \\serverTraceStack());",
];
foreach ($patches as $before => $after) {
    if (substr_count($source, $before) !== 1) {
        throw new RuntimeException('Diagnostic patch did not match source uniquely.');
    }
    $source = str_replace($before, $after, $source);
}
if (!in_array('--plain', $argv ?? [], true)) {
    eval(substr($source, 5));
}
require $root . '/tests/bootstrap.php';

final class ServerAttributionProbe extends Tests\Support\TestCase
{
    private array $samples = [];

    private function sample(string $name, string $path, array $query = []): void
    {
        $GLOBALS['server_trace'] = ['queries' => [], 'invalidations' => [], 'cache' => []];
        $this->db->resetMetrics();
        $started = hrtime(true);
        try {
            $response = $this->app->handle(new App\Core\Request('GET', $path, $query, [], $this->cookies, [
                'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'server-attribution-probe',
            ]));
            $elapsed = (hrtime(true) - $started) / 1_000_000;
            $this->samples[$name] = [
                'status' => $response->status(),
                'body_bytes' => strlen($response->body()),
                ...$this->db->metrics(),
                'query_count' => $this->db->metrics()['queries'],
                'total_ms' => round($elapsed, 3),
                ...$GLOBALS['server_trace'],
            ];
        } finally {
            $GLOBALS['server_trace'] = null;
        }
    }

    private function stale(array $viewer): void
    {
        $this->db->run('UPDATE users SET last_seen_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 180), $viewer['id']]);
        $this->db->run('UPDATE sessions SET last_seen_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 180), hash('sha256', $this->cookies['rb_session'])]);
    }

    public function measure(): array
    {
        $this->setUp();
        $fixtureUserIds = [];
        try {
            $admin = $this->makeAdmin(['username' => 'serverperfadmin']);
            $author = $this->makeUser(['username' => 'serverperfauthor']);
            $viewer = $this->makeUser(['username' => 'serverperfviewer']);
            $fixtureUserIds = [(int) $admin['id'], (int) $author['id'], (int) $viewer['id']];
            $this->users()->updateLastSeen((int) $viewer['id']);
            (new App\Repository\SettingRepository($this->db))->set('installed_at', '2026-09-20 00:00:00');
            $board = $this->makeBoard($this->makeCategory('Server Attribution'), ['slug' => 'server-attribution']);
            $thread = $this->makeThread($board, $author, 'Server attribution fixture', 'A **public** post.');
            $threadId = (int) $thread['thread_id'];
            $path = '/t/' . $threadId . '-' . $thread['slug'];
            $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId]);

            $this->sample('guest_home', '/');
            $this->sample('guest_thread', $path);
            $this->actingAs($viewer);
            $this->sample('member_home_fresh', '/');
            $this->sample('member_thread_first_visit', $path);
            $this->sample('member_thread_repeat', $path);
            $this->sample('presence_fresh', '/presence', ['format' => 'json']);
            $this->sample('bell_fresh', '/notifications/bell', ['format' => 'json']);

            foreach (['member_home_stale' => '/', 'member_thread_stale' => $path, 'presence_stale' => '/presence', 'bell_stale' => '/notifications/bell'] as $name => $route) {
                $this->stale($viewer);
                $this->sample($name, $route);
            }

            $replyIds = [$this->posting()->reply($this->userEntity($author), $threadId, ['body' => 'Unread reply 1'])];
            $this->sample('member_thread_one_unread', $path);
            $this->logoutClient();
            $this->sample('guest_thread_one_reply', $path);
            $this->actingAs($viewer);
            for ($i = 2; $i <= 6; $i++) {
                $replyIds[] = $this->posting()->reply($this->userEntity($author), $threadId, ['body' => 'Unread reply ' . $i]);
            }
            $this->db->run('UPDATE thread_user SET last_read_post_id = ? WHERE user_id = ? AND thread_id = ?', [$opId, $viewer['id'], $threadId]);
            $this->sample('member_thread_six_unread', $path);
            $this->sample('member_thread_six_read_repeat', $path);
            $this->logoutClient();
            $this->sample('guest_thread_six_replies', $path);
            $this->actingAs($viewer);

            // Isolate one reference per post, deliberately sharing a target to
            // make redundant target reads visible independently of membership.
            foreach ($replyIds as $postId) {
                $this->db->run("INSERT INTO content_references (source_type, source_id, target_type, target_id, token, resolved_at, unavailable, created_at) VALUES ('post', ?, 'thread', ?, ?, UTC_TIMESTAMP(), 0, UTC_TIMESTAMP())", [$postId, $threadId, (string) $threadId]);
            }
            $this->sample('member_thread_six_references', $path);
            $this->logoutClient();
            $this->sample('guest_thread_six_references', $path);
            $this->actingAs($viewer);
            $this->db->run('DELETE FROM content_references WHERE source_type = ? AND source_id IN (' . implode(',', array_map('intval', $replyIds)) . ')', ['post']);

            $this->db->run('UPDATE boards SET wiki_enabled = 1 WHERE id = ?', [$board['id']]);
            $this->users()->updateLastSeen((int) $admin['id']);
            $this->actingAs($admin);
            $this->sample('admin_thread_wiki_baseline_first', $path);
            $this->sample('admin_thread_wiki_baseline_repeat', $path);

            foreach ($replyIds as $postId) {
                $this->db->run('UPDATE posts SET is_wiki = 1 WHERE id = ?', [$postId]);
                $this->db->run("INSERT INTO post_revisions (post_id, editor_id, body, body_html, reason, created_at) VALUES (?, ?, 'History fixture', '<p>History fixture</p>', 'wiki_enabled', UTC_TIMESTAMP())", [$postId, $author['id']]);
            }
            $this->sample('admin_thread_six_wikis', $path);
            $this->db->run('UPDATE users SET last_seen_at = NULL WHERE id = ?', [$admin['id']]);
            $this->actingAs($viewer);
            $this->sample('member_thread_six_wikis', $path);
            $this->db->run('UPDATE posts SET is_wiki = 0 WHERE thread_id = ?', [$threadId]);

            $pollId = $this->db->insert("INSERT INTO polls (thread_id, question, mode, status, results_policy, created_by, created_at) VALUES (?, 'Which option?', 'single', 'open', 'after_vote_or_close', ?, UTC_TIMESTAMP())", [$threadId, $author['id']]);
            foreach (['Alpha', 'Beta'] as $position => $option) {
                $this->db->run('INSERT INTO poll_options (poll_id, body, position, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())', [$pollId, $option, $position]);
            }
            $this->sample('member_thread_poll', $path);
            $this->logoutClient();
            $this->sample('guest_thread_poll', $path);

            $imageThread = $this->makeThread($board, $admin, 'Staff image topic', '![Fixture image](/media/1)');
            $this->sample('guest_thread_staff_image', '/t/' . $imageThread['thread_id'] . '-' . $imageThread['slug']);

            $this->sample('guest_healthz_control', '/healthz');
            $this->sample('guest_login_control', '/login');
            $this->sample('guest_404_control', '/server-attribution-does-not-exist');

            return ['php_version' => PHP_VERSION, 'database' => $GLOBALS['__RB_TEST_DBCONFIG']['database'], 'emulated_prepares' => (bool) $this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES), 'fixture_note' => 'Local dedicated test DB; injected PDO means no connect latency. Tracing adds PHP overhead unless --plain is used, timings are not production forecasts. Query parameters are hashed; no credentials are included.', 'samples' => $this->samples];
        } finally {
            $this->tearDown();
            $place = implode(',', array_fill(0, count($fixtureUserIds), '?'));
            $remaining = $fixtureUserIds === [] ? 0 : (int) $this->db->fetchValue('SELECT COUNT(*) FROM users WHERE id IN (' . $place . ')', $fixtureUserIds);
            if ($remaining !== 0 || $this->pdo->inTransaction()) {
                throw new RuntimeException('Rollback cleanup verification failed.');
            }
            fwrite(STDERR, "Rollback verified: no fixture users remain; no open transaction.\n");
        }
    }
}

echo json_encode((new ServerAttributionProbe('measure'))->measure(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
