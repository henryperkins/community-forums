<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BlockRepository;
use Tests\Support\TestCase;

/**
 * @mention notifications (P2-05), driven through the real create/edit routes so
 * fan-out runs inside the write transaction. Covers cap, block-awareness,
 * nonexistent handles, and edit-only-notifies-new-mentions.
 */
final class AppMentionTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $author;
    /** @var array<string,mixed> */
    private array $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
        $this->author = $this->makeUser(['username' => 'author1']);
        $this->board = $this->makeBoard($this->makeCategory(), ['slug' => 'general']);
    }

    private function mentionCount(int $userId): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'mention'",
            [$userId],
        );
    }

    private function createThread(string $body): void
    {
        $this->actingAs($this->author);
        $r = $this->post('/threads', ['board_id' => (int) $this->board['id'], 'title' => 'Mentioning', 'body' => $body]);
        $this->assertRedirectContains($r, '/t/');
    }

    public function testMentionNotifiesTheMentionedUser(): void
    {
        $bob = $this->makeUser(['username' => 'bob']);
        $this->createThread('Welcome @bob, glad you joined!');
        self::assertSame(1, $this->mentionCount((int) $bob['id']));
    }

    public function testNonexistentMentionIsIgnored(): void
    {
        $bob = $this->makeUser(['username' => 'bob']);
        $this->createThread('Hello @ghostuser and @bob');
        self::assertSame(1, $this->mentionCount((int) $bob['id']));
        // No crash, and only the real user is notified.
    }

    public function testBlockedUserIsNotMentionNotified(): void
    {
        $blocked = $this->makeUser(['username' => 'blockedone']);
        (new BlockRepository($this->db))->block((int) $blocked['id'], (int) $this->author['id']); // they blocked the author
        $this->createThread('Hey @blockedone');
        self::assertSame(0, $this->mentionCount((int) $blocked['id']), 'blocked pair: no mention notification');
    }

    public function testMentionCapIsEnforced(): void
    {
        $ids = [];
        $handles = [];
        for ($i = 1; $i <= 12; $i++) {
            $u = $this->makeUser(['username' => 'mention' . $i]);
            $ids[] = (int) $u['id'];
            $handles[] = '@mention' . $i;
        }
        $this->createThread('Big shoutout: ' . implode(' ', $handles));

        $total = (int) $this->db->fetchValue("SELECT COUNT(*) FROM notifications WHERE type = 'mention'");
        self::assertSame(10, $total, 'at most 10 mentions per post are notified');
    }

    public function testEditNotifiesOnlyNewlyAddedMentions(): void
    {
        $bob = $this->makeUser(['username' => 'bob']);
        $carol = $this->makeUser(['username' => 'carol']);
        $this->createThread('Hi @bob');
        self::assertSame(1, $this->mentionCount((int) $bob['id']));

        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE user_id = ? AND is_op = 1', [(int) $this->author['id']]);
        $this->actingAs($this->author);
        $this->post('/posts/' . $opId . '/edit', ['body' => 'Hi @bob and now also @carol']);

        self::assertSame(1, $this->mentionCount((int) $carol['id']), 'newly added mention is notified');
        self::assertSame(1, $this->mentionCount((int) $bob['id']), 'existing mention is not resent on edit');
    }
}
