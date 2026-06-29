<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Core\FeatureFlags;
use App\Repository\BoardMemberRepository;
use App\Repository\SettingRepository;
use App\Repository\TagRepository;
use App\Worker\RelatedTopicRefreshWorker;
use Tests\Support\TestCase;

final class RelatedTopicRefreshWorkerTest extends TestCase
{
    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_worker_is_dark_without_automation_flag(): void
    {
        $this->setFlags(['community_memory' => true]);
        $worker = new RelatedTopicRefreshWorker($this->db, new FeatureFlags(new SettingRepository($this->db)));

        self::assertSame(['linked' => 0, 'skipped' => 1], $worker->run());
    }

    public function test_worker_is_dark_without_tags_flag(): void
    {
        $this->setFlags(['community_memory' => true, 'automated_context' => true]);
        $worker = new RelatedTopicRefreshWorker($this->db, new FeatureFlags(new SettingRepository($this->db)));

        self::assertSame(['linked' => 0, 'skipped' => 1], $worker->run());
    }

    public function test_worker_links_public_tag_related_threads_without_private_leaks(): void
    {
        $this->makeAdmin();
        $this->setFlags(['community_memory' => true, 'automated_context' => true, 'tags' => true]);
        $author = $this->makeUser(['username' => 'relatedauthor']);
        $category = $this->makeCategory('Related Refresh');
        $publicBoard = $this->makeBoard($category, ['slug' => 'related-public']);
        $privateBoard = $this->makeBoard($category, ['slug' => 'related-private', 'visibility' => 'private']);
        (new BoardMemberRepository($this->db))->add((int) $privateBoard['id'], (int) $author['id'], null);

        $alpha = $this->makeThread($publicBoard, $author, 'Alpha tagged topic', 'alpha');
        $beta = $this->makeThread($publicBoard, $author, 'Beta tagged topic', 'beta');
        $secret = $this->makeThread($privateBoard, $author, 'Secret tagged topic', 'secret');

        $tags = new TagRepository($this->db);
        $tagId = $tags->create('release', 'Release', null, (int) $author['id']);
        $tags->setForThread($alpha['thread_id'], [$tagId], (int) $author['id']);
        $tags->setForThread($beta['thread_id'], [$tagId], (int) $author['id']);
        $tags->setForThread($secret['thread_id'], [$tagId], (int) $author['id']);

        $worker = new RelatedTopicRefreshWorker($this->db, new FeatureFlags(new SettingRepository($this->db)));
        $stats = $worker->run();

        self::assertSame(['linked' => 2, 'skipped' => 0], $stats);
        self::assertSame(2, (int) $this->db->fetchValue("SELECT COUNT(*) FROM related_threads WHERE source = 'tag' AND status = 'approved'"));
        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM related_threads WHERE related_thread_id = ? OR source_thread_id = ?",
            [$secret['thread_id'], $secret['thread_id']],
        ));

        $page = $this->get('/t/' . $alpha['thread_id'] . '-' . $alpha['slug']);
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Beta tagged topic');
        $this->assertDontSeeText($page, 'Secret tagged topic');
    }
}
