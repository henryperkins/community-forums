<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardMemberRepository;
use App\Repository\ThreadUserRepository;
use Tests\Support\TestCase;

/**
 * Private-board membership (P2-08): an added member can read the board, its
 * threads, and see them in their inbox; a removed member immediately loses that
 * access. (Search membership is covered in AppSearchTest.)
 */
final class AppPrivateBoardMembershipTest extends TestCase
{
    /** @var array<string,mixed> */ private array $admin;
    /** @var array<string,mixed> */ private array $member;
    /** @var array<string,mixed> */ private array $board;
    /** @var array{thread_id:int,slug:string} */ private array $thread;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin();
        $this->member = $this->makeUser(['username' => 'memberx']);
        $this->board = $this->makeBoard($this->makeCategory(), ['slug' => 'secret', 'visibility' => 'private']);
        $this->thread = $this->makeThread($this->board, $this->admin, 'Secret topic', 'classified');
    }

    private function inboxTitles(): array
    {
        $rows = (new ThreadUserRepository($this->db))->inbox((int) $this->member['id'], 'active', false, ThreadUserRepository::NO_CUTOVER, 50, 0);
        return array_column($rows, 'title');
    }

    public function testNonMemberHasNoAccess(): void
    {
        $this->actingAs($this->member);
        $this->assertStatus(404, $this->get('/c/secret'));
        $this->assertStatus(404, $this->get('/t/' . $this->thread['thread_id'] . '-' . $this->thread['slug']));
        self::assertNotContains('Secret topic', $this->inboxTitles());
    }

    public function testAddedMemberGainsAccessAndRemovalRevokesIt(): void
    {
        $members = new BoardMemberRepository($this->db);
        $members->add((int) $this->board['id'], (int) $this->member['id'], (int) $this->admin['id']);

        $this->actingAs($this->member);
        $this->assertStatus(200, $this->get('/c/secret'));
        $this->assertStatus(200, $this->get('/t/' . $this->thread['thread_id'] . '-' . $this->thread['slug']));
        self::assertContains('Secret topic', $this->inboxTitles(), 'member sees the private thread in their inbox');

        // Revoke → immediate loss of access.
        $members->remove((int) $this->board['id'], (int) $this->member['id']);
        $this->assertStatus(404, $this->get('/c/secret'));
        self::assertNotContains('Secret topic', $this->inboxTitles());
    }

    public function testMemberCanPostInPrivateBoardOnceAdded(): void
    {
        (new BoardMemberRepository($this->db))->add((int) $this->board['id'], (int) $this->member['id'], (int) $this->admin['id']);
        // A member may reply in the private board they belong to.
        $postId = $this->posting()->reply($this->userEntity($this->member), $this->thread['thread_id'], ['body' => 'member reply']);
        self::assertGreaterThan(0, $postId);
    }
}
