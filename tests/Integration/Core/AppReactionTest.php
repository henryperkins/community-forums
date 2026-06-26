<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\ReactionRepository;
use App\Service\RepairService;
use Tests\Support\TestCase;

/**
 * Reactions + reaction-derived reputation (P2-02). Covers the acceptance
 * scenarios: reaction retry/idempotency, self-reaction = 0 rep, delete adjusts
 * reputation, and the write gate.
 */
final class AppReactionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(); // initialise the app so the setup gate doesn't intercept HTTP routes
    }

    /** @return array{author:array<string,mixed>, board:array<string,mixed>, thread:array{thread_id:int,slug:string}, op_id:int} */
    private function scenario(): array
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'React to me', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        return compact('author', 'board', 'thread') + ['op_id' => $opId];
    }

    private function reputation(int $userId): int
    {
        return (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [$userId]);
    }

    private function reactionRows(int $postId): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM reactions WHERE post_id = ?', [$postId]);
    }

    public function testReactionFromAnotherUserTogglesReputation(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser();
        $this->actingAs($fan);

        $r = $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '👍']);
        $this->assertRedirect($r);
        self::assertSame(1, $this->reactionRows($s['op_id']));
        self::assertSame(1, $this->reputation((int) $s['author']['id']), 'received reaction grants +1');

        // Toggling the same emoji removes it and the reputation.
        $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '👍']);
        self::assertSame(0, $this->reactionRows($s['op_id']));
        self::assertSame(0, $this->reputation((int) $s['author']['id']));
    }

    public function testSelfReactionContributesZeroReputation(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['author']);

        $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '🎉']);
        self::assertSame(1, $this->reactionRows($s['op_id']), 'self-reaction may exist');
        self::assertSame(0, $this->reputation((int) $s['author']['id']), 'but never grants the author reputation');
    }

    public function testToggleIsIdempotentAndNeverDuplicates(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser();
        $repo = new ReactionRepository($this->db);

        self::assertSame('added', $repo->toggle($s['op_id'], (int) $fan['id'], '👍'));
        self::assertSame(1, $this->reactionRows($s['op_id']));
        self::assertSame('removed', $repo->toggle($s['op_id'], (int) $fan['id'], '👍'));
        self::assertSame(0, $this->reactionRows($s['op_id']));

        // Distinct emoji accumulate; same emoji never duplicates.
        $repo->toggle($s['op_id'], (int) $fan['id'], '👍');
        $repo->toggle($s['op_id'], (int) $fan['id'], '❤️');
        self::assertSame(2, $this->reactionRows($s['op_id']));
        $counts = $repo->countsForPost($s['op_id']);
        self::assertCount(2, $counts);
        self::assertSame(1, $counts['👍']);
        self::assertSame(1, $counts['❤️']);
    }

    public function testDisallowedEmojiIsRejected(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser();
        $this->actingAs($fan);

        $r = $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '💀', 'format' => 'json']);
        $this->assertStatus(422, $r);
        self::assertSame(0, $this->reactionRows($s['op_id']));
    }

    public function testDeletingReactedPostRemovesReputation(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser();
        // Author writes a reply; the fan reacts to it (the reply earns rep).
        $replyId = $this->posting()->reply($this->userEntity($s['author']), $s['thread']['thread_id'], ['body' => 'A reply that gets a reaction.']);
        (new ReactionRepository($this->db))->toggle($replyId, (int) $fan['id'], '🔥');
        (new RepairService($this->db))->repairReputation();
        self::assertSame(1, $this->reputation((int) $s['author']['id']));

        // Author deletes the reacted reply → reputation recomputes downward.
        $this->actingAs($s['author']);
        $this->post('/posts/' . $replyId . '/delete');
        self::assertSame(0, $this->reputation((int) $s['author']['id']));
    }

    public function testSuspendedUserCannotReact(): void
    {
        $s = $this->scenario();
        $suspended = $this->makeUser(['status' => 'suspended']);
        $this->actingAs($suspended);

        $r = $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '👍']);
        $this->assertStatus(403, $r);
        self::assertSame(0, $this->reactionRows($s['op_id']));
    }

    public function testReactionJsonReturnsState(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser();
        $this->actingAs($fan);

        $r = $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '💯', 'format' => 'json']);
        $this->assertStatus(200, $r);
        $data = json_decode($r->body(), true);
        self::assertTrue($data['ok']);
        self::assertSame('added', $data['state']);
        self::assertSame(1, $data['counts']['💯']);
    }
}
