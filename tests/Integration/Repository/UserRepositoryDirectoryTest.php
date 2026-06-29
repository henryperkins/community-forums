<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\UserRepository;
use Tests\Support\TestCase;

final class UserRepositoryDirectoryTest extends TestCase
{
    public function test_directory_returns_users_newest_first(): void
    {
        $repo = new UserRepository($this->db);
        $this->makeUser(['username' => 'dir_alpha']);
        $this->makeUser(['username' => 'dir_beta']); // created after alpha → higher id

        $usernames = array_map(
            static fn (array $r): string => (string) $r['username'],
            $repo->directory('', 200, 0),
        );

        self::assertContains('dir_alpha', $usernames);
        self::assertContains('dir_beta', $usernames);
        self::assertLessThan(
            array_search('dir_alpha', $usernames, true),
            array_search('dir_beta', $usernames, true), // beta (newer) appears before alpha
        );
    }

    public function test_directory_search_filters_by_handle(): void
    {
        $repo = new UserRepository($this->db);
        $this->makeUser(['username' => 'needle_user']);
        $this->makeUser(['username' => 'other_person']);

        $usernames = array_map(
            static fn (array $r): string => (string) $r['username'],
            $repo->directory('needle', 200, 0),
        );

        self::assertContains('needle_user', $usernames);
        self::assertNotContains('other_person', $usernames);
    }
}
