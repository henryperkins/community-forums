<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ThreadRepository;
use App\Security\BoardPolicy;
use App\Service\NavigationService;
use Tests\Support\TestCase;

final class NavigationServiceTest extends TestCase
{
    public function test_sidebar_and_home_share_one_navigation_snapshot(): void
    {
        $visibleCategoryId = $this->makeCategory('Visible navigation category');
        $this->makeBoard($visibleCategoryId, ['slug' => 'visible-nav', 'name' => 'Visible navigation board']);
        $this->makeCategory('Empty navigation category');

        $service = new NavigationService(
            new CategoryRepository($this->db),
            new BoardRepository($this->db),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
            new ThreadRepository($this->db),
        );

        $this->db->resetMetrics();
        $sidebar = $service->sidebar(null);
        $home = $service->homeSections(null);

        self::assertSame(2, $this->db->metrics()['queries']);
        self::assertContains('Visible navigation category', array_column(array_column($sidebar, 'category'), 'name'));
        self::assertNotContains('Empty navigation category', array_column(array_column($sidebar, 'category'), 'name'));
        self::assertContains('Visible navigation category', array_column(array_column($home, 'category'), 'name'));
        self::assertContains('Empty navigation category', array_column(array_column($home, 'category'), 'name'));
    }

    public function test_directory_adds_one_bounded_query_without_personal_state_or_per_board_reads(): void
    {
        $categoryId = $this->makeCategory('Directory category');
        $first = $this->makeBoard($categoryId, ['slug' => 'directory-one']);
        $second = $this->makeBoard($categoryId, ['slug' => 'directory-two']);
        $author = $this->makeUser(['username' => 'directory_service_author']);
        $this->makeThread($first, $author, 'First public topic');
        $this->makeThread($second, $author, 'Second public topic');

        $service = new NavigationService(
            new CategoryRepository($this->db),
            new BoardRepository($this->db),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
            new ThreadRepository($this->db),
        );

        $this->db->resetMetrics();
        $groups = $service->directory(null, 'active', 5);

        self::assertSame(3, $this->db->metrics()['queries']);
        self::assertCount(1, $groups);
        self::assertCount(2, $groups[0]['boards']);
        foreach ($groups[0]['boards'] as $board) {
            self::assertArrayNotHasKey('unread_count', $board);
            self::assertArrayNotHasKey('is_muted', $board);
            self::assertArrayNotHasKey('is_starred', $board);
            self::assertLessThanOrEqual(5, count($board['topics']));
        }
    }
}
