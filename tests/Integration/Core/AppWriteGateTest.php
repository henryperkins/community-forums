<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * Account-state write gate: suspended/banned accounts can read (and suspended
 * can authenticate) but are denied on every write path — including via a stale
 * session whose stored state changed after login. The trigger state is set
 * out-of-band (no in-app ban action ships in Phase 1).
 */
final class AppWriteGateTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $board;
    /** @var array{thread_id:int,slug:string} */
    private array $thread;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
        $author = $this->makeUser(['username' => 'author']);
        $this->board = $this->makeBoard($this->makeCategory(), ['slug' => 'general']);
        $this->thread = $this->makeThread($this->board, $author, 'Topic', 'OP');
    }

    public function test_suspended_user_is_denied_on_every_write_path(): void
    {
        // Create while active so they own a post, then suspend out-of-band.
        $user = $this->makeUser(['username' => 'susp']);
        $ownPost = $this->posting()->reply($this->userEntity($user), $this->thread['thread_id'], ['body' => 'mine']);
        $this->users()->setStatus((int) $user['id'], 'suspended', null);

        $this->actingAs($user);

        // Can still read.
        $this->assertStatus(200, $this->get('/t/' . $this->thread['thread_id'] . '-' . $this->thread['slug']));

        // Every write is blocked (403).
        $this->assertStatus(403, $this->post('/threads', ['board_id' => $this->board['id'], 'title' => 'x', 'body' => 'y']));
        $this->assertStatus(403, $this->post('/t/' . $this->thread['thread_id'] . '/reply', ['body' => 'y']));
        $this->assertStatus(403, $this->post('/posts/' . $ownPost . '/edit', ['body' => 'edited']));
        $this->assertStatus(403, $this->post('/posts/' . $ownPost . '/delete'));
        $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'Nope']));
        $this->assertStatus(403, $this->post('/settings/security', [
            'current_password' => 'password123',
            'new_password' => 'whatever123',
            'new_password_confirm' => 'whatever123',
        ]));

        // The own post was not edited or deleted.
        self::assertSame('mine', $this->posts()->find($ownPost)['body']);
        self::assertSame(0, (int) $this->posts()->find($ownPost)['is_deleted']);
    }

    public function test_banned_stale_session_cannot_write_but_can_read(): void
    {
        $user = $this->makeUser(['username' => 'willban']);
        $this->actingAs($user); // session created while active

        // Banned out-of-band after the session already exists.
        $this->users()->setStatus((int) $user['id'], 'banned', null);

        // Stale session can still read…
        $this->assertStatus(200, $this->get('/'));
        // …but cannot write.
        $this->assertStatus(403, $this->post('/threads', ['board_id' => $this->board['id'], 'title' => 'x', 'body' => 'y']));
        $this->assertStatus(403, $this->post('/t/' . $this->thread['thread_id'] . '/reply', ['body' => 'y']));
    }

    public function test_active_user_can_write(): void
    {
        $user = $this->makeUser(['username' => 'active']);
        $this->actingAs($user);
        $this->get('/t/' . $this->thread['thread_id'] . '-' . $this->thread['slug']);
        $this->assertRedirectContains(
            $this->post('/t/' . $this->thread['thread_id'] . '/reply', ['body' => 'a normal reply']),
            '#p',
        );
    }
}
