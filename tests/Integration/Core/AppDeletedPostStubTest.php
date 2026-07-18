<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use Tests\Support\TestCase;

/**
 * Deleted-reply stubs (ADMIN §3.3): staff who can moderate the board see a
 * restorable stub — preserved content included — in place of a deleted reply;
 * members see nothing, exactly as before. The /mod/p/{id}/restore endpoint
 * predates this UI; these tests pin the surface that exposes it.
 */
final class AppDeletedPostStubTest extends TestCase
{
    /** @return array{thread:array{thread_id:int,slug:string},reply:int,board:array<string,mixed>} */
    private function seedDeletedReply(): array
    {
        $this->makeAdmin(); // satisfy the first-run setup gate
        $board = $this->makeBoard($this->makeCategory('Stubs'), ['slug' => 'stub-board']);
        $author = $this->makeUser(['username' => 'stubauthor']);
        $t = $this->makeThread($board, $author, 'Stub topic', 'Opening body.');
        $reply = $this->posting()->reply($this->userEntity($author), $t['thread_id'], ['body' => 'Recoverable reply body.']);
        $this->actingAs($this->makeAdmin(['username' => 'stubadmin']));
        $this->post('/posts/' . $reply . '/delete', ['reason' => 'cleanup']);
        self::assertSame(1, (int) $this->posts()->find($reply)['is_deleted']);
        return ['thread' => $t, 'reply' => $reply, 'board' => $board];
    }

    public function test_admin_sees_deleted_reply_stub_with_restore_control(): void
    {
        $seed = $this->seedDeletedReply();
        $res = $this->get('/t/' . $seed['thread']['thread_id'] . '-' . $seed['thread']['slug']);
        $this->assertStatus(200, $res);
        self::assertStringContainsString('Removed by a warden', $res->body());
        self::assertStringContainsString('/mod/p/' . $seed['reply'] . '/restore', $res->body());
        self::assertStringContainsString('Recoverable reply body.', $res->body()); // preserved for accountability
    }

    public function test_members_see_no_stub_or_deleted_content(): void
    {
        $seed = $this->seedDeletedReply();
        $this->actingAs($this->makeUser(['username' => 'bystander']));
        $res = $this->get('/t/' . $seed['thread']['thread_id'] . '-' . $seed['thread']['slug']);
        $this->assertStatus(200, $res);
        self::assertStringNotContainsString('Removed by a warden', $res->body());
        self::assertStringNotContainsString('Recoverable reply body.', $res->body());
    }

    public function test_scoped_board_moderator_sees_stub_and_restore(): void
    {
        $seed = $this->seedDeletedReply();
        $mod = $this->makeUser(['username' => 'stubmod']);
        (new BoardModeratorRepository($this->db))->assign((int) $seed['board']['id'], (int) $mod['id']);
        $this->actingAs($mod);
        $res = $this->get('/t/' . $seed['thread']['thread_id'] . '-' . $seed['thread']['slug']);
        self::assertStringContainsString('Removed by a warden', $res->body());
        self::assertStringContainsString('/mod/p/' . $seed['reply'] . '/restore', $res->body());
    }

    public function test_page_of_post_matches_the_staff_stream_when_deleted_rows_precede(): void
    {
        // Review finding: staff paginate the with-deleted stream, so the failed
        // inline-edit refocus (pageOfPost) must rank against the same stream or
        // the re-render opens a page that does not contain the edited post.
        $seed = $this->seedDeletedReply(); // OP + one soft-deleted reply
        $author2 = $this->makeUser(['username' => 'stubauthor2']);
        $after = $this->posting()->reply($this->userEntity($author2), $seed['thread']['thread_id'], ['body' => 'After the removal.']);

        // Staff stream: OP, deleted stub, this reply → rank 3 (page 3 at perPage=1).
        self::assertSame(3, $this->posts()->pageOfPost($seed['thread']['thread_id'], $after, 1, includeDeleted: true));
        // Member stream is unchanged: OP, this reply → rank 2.
        self::assertSame(2, $this->posts()->pageOfPost($seed['thread']['thread_id'], $after, 1));
    }

    public function test_restore_from_stub_returns_post_to_members(): void
    {
        $seed = $this->seedDeletedReply();
        // Still acting as the deleting admin — restore exactly as the stub form posts it.
        $this->post('/mod/p/' . $seed['reply'] . '/restore', ['reason' => 'on reflection']);
        self::assertSame(0, (int) $this->posts()->find($seed['reply'])['is_deleted']);

        $this->actingAs($this->makeUser(['username' => 'reader2']));
        $res = $this->get('/t/' . $seed['thread']['thread_id'] . '-' . $seed['thread']['slug']);
        self::assertStringContainsString('Recoverable reply body.', $res->body());
        self::assertStringNotContainsString('Removed by a warden', $res->body());
    }
}
