<?php

declare(strict_types=1);

// Reproduce only against the designated disposable test database, under the suite lock:
// flock /tmp/retroboards-unified-phpunit.lock env DB_TEST_DATABASE=retroboards_unified_test MAIL_DRIVER=sendmail MAIL_FROM='' php docs/evidence/unified-notifications-and-settings/notification-query-plan.php
if (getenv('DB_TEST_DATABASE') !== 'retroboards_unified_test') {
    throw new RuntimeException('Set DB_TEST_DATABASE=retroboards_unified_test; this uses the integration-test bootstrap.');
}
require dirname(__DIR__, 3) . '/tests/bootstrap.php';

$probe = new class('queryEvidence') extends \Tests\Support\TestCase {
    public function capture(): array
    {
        $this->setUp();
        try {
            $author = $this->makeUser();
            $member = $this->makeUser();
            $category = $this->makeCategory();
            $board = $this->makeBoard($category);
            $private = $this->makeBoard($category);
            $threads = [];
            $subs = new \App\Repository\SubscriptionRepository($this->db);
            for ($i = 0; $i < 100; $i++) {
                $thread = $this->makeThread($i < 50 ? $board : $private, $author, 'Query plan topic ' . $i);
                $threads[] = $thread['thread_id'];
                $subs->set((int) $member['id'], 'thread', $thread['thread_id'], true, true, 'daily');
            }
            $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$private['id']]);
            $repo = new \App\Repository\NotificationRepository($this->db);
            for ($i = 0; $i < 10000; $i++) {
                $repo->create(['user_id' => (int) $member['id'], 'type' => 'reply', 'actor_id' => (int) $author['id'], 'thread_id' => $threads[$i % 100]]);
            }
            $viewer = $this->userEntity($member);
            $visibility = new \App\Service\NotificationVisibilityService($this->db);
            $reader = new \App\Service\NotificationReadService($repo, $visibility);
            $before = $this->db->metrics();
            $started = hrtime(true);
            $page = $reader->page($viewer);
            $subscriptions = $subs->listForUserWithContext($viewer->id(), $visibility->scope($viewer));
            $duration = (hrtime(true) - $started) / 1e6;
            $after = $this->db->metrics();
            $memoBefore = $after['queries'];
            $reader->unreadCount($viewer);
            $visibility->scope($viewer);
            $memoQueries = $this->db->metrics()['queries'] - $memoBefore;
            $scope = $visibility->scope($viewer);
            $context = (new ReflectionClass($repo))->getConstant('CONTEXT');
            $predicate = \App\Repository\NotificationEligibility::notification($scope);
            $query = $context . ' WHERE n.user_id = ? AND ' . $predicate;
            return [
                'database' => 'retroboards_unified_test', 'server' => $this->db->fetchValue('SELECT VERSION()'),
                'notifications' => 10000, 'subscriptions' => 100,
                'eligible_unread' => $page['unread'], 'returned_items' => count($page['items']),
                'returned_subscriptions' => count($subscriptions),
                'unavailable_subscriptions' => count(array_filter($subscriptions, static fn (array $s): bool => !(bool) $s['available'])),
                'scope_page_count_subscription_queries' => $after['queries'] - $before['queries'],
                'repeat_scope_count_queries' => $memoQueries,
                'elapsed_ms_observed_not_a_threshold' => round($duration, 3),
                'page_plan' => $this->db->fetchAll('EXPLAIN SELECT n.*' . $query . ' ORDER BY n.id DESC LIMIT 31', [$viewer->id()]),
                'count_plan' => $this->db->fetchAll('EXPLAIN SELECT COUNT(*)' . $query . ' AND n.is_read = 0', [$viewer->id()]),
                'count_analysis' => json_decode((string) $this->db->fetchValue('ANALYZE FORMAT=JSON SELECT COUNT(*)' . $query . ' AND n.is_read = 0', [$viewer->id()]), true, 512, JSON_THROW_ON_ERROR),
                'cleanup' => 'All fixture writes rolled back in finally.',
            ];
        } finally {
            $this->tearDown();
        }
    }
};
echo json_encode($probe->capture(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
