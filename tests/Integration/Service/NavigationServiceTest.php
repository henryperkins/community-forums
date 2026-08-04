<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserBoardPrefRepository;
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
            new UserBoardPrefRepository($this->db),
            new BoardPolicy(),
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
}
