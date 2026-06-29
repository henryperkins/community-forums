<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\BadgeRepository;
use Tests\Support\TestCase;

final class BadgeRepositoryManualTest extends TestCase
{
    public function test_manual_catalogue_lists_enabled_manual_badges_only(): void
    {
        $repo = new BadgeRepository($this->db);
        $slugs = array_map(static fn (array $b): string => (string) $b['slug'], $repo->manualCatalogue());

        self::assertContains('staff', $slugs);
        self::assertContains('founder', $slugs);
        self::assertNotContains('welcome', $slugs); // an auto badge must never appear
    }

    public function test_manual_held_by_user_returns_only_granted_manual_badges(): void
    {
        $repo = new BadgeRepository($this->db);
        $user = $this->makeUser(['username' => 'badgeholder']);
        $uid = (int) $user['id'];

        self::assertSame([], $repo->manualHeldByUser($uid));

        $repo->awardBySlug($uid, 'staff');   // manual
        $repo->awardBySlug($uid, 'welcome'); // auto — must be excluded

        $held = array_map(static fn (array $b): string => (string) $b['slug'], $repo->manualHeldByUser($uid));
        self::assertSame(['staff'], $held);
    }
}
