<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Domain\User;
use App\Security\BoardPolicy;
use PHPUnit\Framework\TestCase;

final class BoardPolicyArchiveTest extends TestCase
{
    private function user(string $role = 'user'): User
    {
        return User::fromRow(['id' => 1, 'username' => 'u', 'role' => $role, 'status' => 'active']);
    }

    public function test_archived_public_board_is_still_readable_and_listed(): void
    {
        $policy = new BoardPolicy();
        $board = ['visibility' => 'public', 'is_archived' => 1];

        self::assertTrue($policy->isArchived($board));
        self::assertTrue($policy->canRead($board, $this->user(), false), 'archived stays readable');
        self::assertTrue($policy->isListed($board, $this->user(), false), 'archived stays listed');
    }

    public function test_archived_board_cannot_be_posted_to_by_any_role(): void
    {
        $policy = new BoardPolicy();
        $board = ['visibility' => 'public', 'post_min_role' => 'user', 'is_archived' => 1];

        self::assertFalse($policy->canPost($board, $this->user('user'), false));
        self::assertFalse($policy->canPost($board, $this->user('moderator'), false));
        self::assertFalse($policy->canPost($board, $this->user('admin'), false), 'no admin carve-out');
    }

    public function test_live_board_still_allows_posting(): void
    {
        $policy = new BoardPolicy();
        $board = ['visibility' => 'public', 'post_min_role' => 'user', 'is_archived' => 0];

        self::assertFalse($policy->isArchived($board));
        self::assertTrue($policy->canPost($board, $this->user('user'), false));
    }
}
