<?php

declare(strict_types=1);

// Run from the repository root against the dedicated disposable PHPUnit schema:
// flock /tmp/retroboards-unified-phpunit.lock env DB_TEST_DATABASE=retroboards_unified_test MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit docs/evidence/unified-notifications-and-settings/A4QueryProfileTest.php
// Metrics are written to /tmp/a4-query-profile.json; this file is outside the default suite.

final class A4QueryProfileTest extends \Tests\Support\TestCase
{
    public function testRepresentativeQueryWork(): void
    {
        $viewer = $this->makeUser(); $author = $this->makeUser(); $boards = [];
        for ($b = 0; $b < 20; $b++) {
            $board = $this->makeBoard($this->makeCategory()); $boards[] = (int) $board['id'];
            for ($t = 0; $t < 10; $t++) { $this->makeThread($board, $author, 'Profile topic ' . $b . '-' . $t); }
        }
        $feed = new \App\Service\FeedService($this->db, new \App\Repository\FollowRepository($this->db), new \App\Repository\BlockRepository($this->db), new \App\Repository\BoardMemberRepository($this->db));
        $profile = ['fixture' => ['boards' => 20, 'topics' => 200, 'posts' => 200]];
        $entity = $this->userEntity($viewer);
        foreach ([1, 100] as $perPage) {
            $this->db->resetMetrics();
            $result = $feed->forSavedFeed($entity, ['board_ids' => $boards, 'sort' => 'latest'], 1, $perPage);
            $profile['saved_feed_' . $perPage] = $this->db->metrics() + ['items' => count($result['items'])];
            self::assertSame($perPage, count($result['items'])); self::assertSame(4, $profile['saved_feed_' . $perPage]['queries']);
        }
        $repo = new \App\Repository\SavedFeedRepository($this->db);
        for ($i = 0; $i < 20; $i++) { $repo->create((int) $viewer['id'], 'Profile feed ' . $i, json_encode(['board_ids' => $boards, 'sort' => 'latest']), true); }
        $activity = new \App\Repository\DigestActivityRepository($this->db);
        $scope = (new \App\Service\NotificationVisibilityService($this->db))->scope($entity);
        $payload = ['sources' => ['subscriptions'=>[], 'saved_feeds'=>$activity->savedSources((int) $viewer['id'])], 'window_start_utc'=>'2000-01-01 00:00:00', 'window_end_utc'=>'2099-01-01 00:00:00', 'max_post_id'=>(int) $this->db->fetchValue('SELECT MAX(id) FROM posts')];
        $this->db->resetMetrics(); $threads = $activity->activity((int) $viewer['id'], $payload, $scope);
        $profile['digest_20_overlapping_sources'] = $this->db->metrics() + ['topics' => count($threads)];
        self::assertCount(200, $threads); self::assertSame(2, $profile['digest_20_overlapping_sources']['queries']);
        file_put_contents('/tmp/a4-query-profile.json', json_encode($profile, JSON_PRETTY_PRINT) . "\n");
    }
}
