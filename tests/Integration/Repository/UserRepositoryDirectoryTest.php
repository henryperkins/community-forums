<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\UserRepository;
use Tests\Support\TestCase;

final class UserRepositoryDirectoryTest extends TestCase
{
    /** @param array<int,array<string,mixed>> $rows @return array<int,string> */
    private static function usernames(array $rows): array
    {
        return array_map(static fn (array $r): string => (string) $r['username'], $rows);
    }

    public function test_directory_returns_users_newest_first(): void
    {
        $repo = new UserRepository($this->db);
        $this->makeUser(['username' => 'dir_alpha']);
        $this->makeUser(['username' => 'dir_beta']); // created after alpha → higher id

        $usernames = self::usernames($repo->directory(['limit' => 200]));

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

        $usernames = self::usernames($repo->directory(['q' => 'needle', 'limit' => 200]));

        self::assertContains('needle_user', $usernames);
        self::assertNotContains('other_person', $usernames);
    }

    public function test_directory_filters_by_role(): void
    {
        $repo = new UserRepository($this->db);
        $this->makeUser(['username' => 'plain_role']);
        $this->makeAdmin(['username' => 'admin_role']);

        $usernames = self::usernames($repo->directory(['role' => 'admin', 'limit' => 200]));

        self::assertContains('admin_role', $usernames);
        self::assertNotContains('plain_role', $usernames);
    }

    public function test_directory_filters_by_status(): void
    {
        $repo = new UserRepository($this->db);
        $this->makeUser(['username' => 'active_one']);
        $this->makeUser(['username' => 'banned_one', 'status' => 'banned']);

        $usernames = self::usernames($repo->directory(['status' => 'banned', 'limit' => 200]));

        self::assertContains('banned_one', $usernames);
        self::assertNotContains('active_one', $usernames);
    }

    public function test_directory_filters_by_post_count_range(): void
    {
        $repo = new UserRepository($this->db);
        $low = (int) $this->makeUser(['username' => 'few_posts'])['id'];
        $high = (int) $this->makeUser(['username' => 'many_posts'])['id'];
        $this->db->run('UPDATE users SET post_count = 2 WHERE id = ?', [$low]);
        $this->db->run('UPDATE users SET post_count = 40 WHERE id = ?', [$high]);

        $usernames = self::usernames($repo->directory(['min_posts' => 10, 'limit' => 200]));

        self::assertContains('many_posts', $usernames);
        self::assertNotContains('few_posts', $usernames);
    }

    public function test_directory_sorts_by_username_ascending(): void
    {
        $repo = new UserRepository($this->db);
        $this->makeUser(['username' => 'zzz_sort_user']);
        $this->makeUser(['username' => 'aaa_sort_user']);

        $usernames = self::usernames($repo->directory([
            'sort' => 'username',
            'direction' => 'asc',
            'limit' => 200,
        ]));

        self::assertLessThan(
            array_search('zzz_sort_user', $usernames, true),
            array_search('aaa_sort_user', $usernames, true),
        );
    }

    public function test_directory_filters_never_seen(): void
    {
        $repo = new UserRepository($this->db);
        $seen = (int) $this->makeUser(['username' => 'seen_recently'])['id'];
        $this->makeUser(['username' => 'never_seen']); // last_seen_at defaults NULL
        $this->db->run('UPDATE users SET last_seen_at = UTC_TIMESTAMP() WHERE id = ?', [$seen]);

        $usernames = self::usernames($repo->directory(['last_seen' => 'never', 'limit' => 200]));

        self::assertContains('never_seen', $usernames);
        self::assertNotContains('seen_recently', $usernames);
    }

    public function test_unknown_sort_key_falls_back_without_error(): void
    {
        $repo = new UserRepository($this->db);
        $this->makeUser(['username' => 'safe_sort']);

        // An attacker-supplied sort key must not reach the SQL; it falls back to created_at.
        $usernames = self::usernames($repo->directory(['sort' => 'id; DROP TABLE users', 'limit' => 200]));

        self::assertContains('safe_sort', $usernames);
    }
}
