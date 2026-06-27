<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * P3-02: the shared composer. The live preview uses the same server render path
 * as a real post; canonical Markdown is stored verbatim (no silent
 * normalization); and the same Markdown produces identical output in a new
 * thread and a reply (one pipeline, four contexts).
 */
final class AppComposerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_preview_uses_the_server_render_pipeline(): void
    {
        $user = $this->makeUser(['username' => 'previewer']);
        $this->actingAs($user);

        $res = $this->post('/composer/preview', ['body' => "**bold** and ||hush|| and <script>alert(1)</script>"]);
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        self::assertTrue($json['ok']);
        self::assertStringContainsString('<strong>bold</strong>', $json['html']);
        self::assertStringContainsString('class="spoiler"', $json['html']);
        self::assertStringNotContainsString('<script', $json['html']);
    }

    public function test_guest_cannot_preview(): void
    {
        $res = $this->post('/composer/preview', ['body' => 'hi']);
        self::assertNotSame(200, $res->status());
    }

    public function test_canonical_markdown_is_stored_verbatim(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'canon']);
        $user = $this->makeUser(['username' => 'canonical']);
        $this->actingAs($user);

        $body = "# Title\n\nSome **bold**, a ||spoiler||, and a list:\n\n- one\n- two\n";
        $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'Canon', 'body' => $body]);

        $stored = (string) $this->db->fetchValue('SELECT body FROM posts WHERE user_id = ? ORDER BY id DESC LIMIT 1', [(int) $user['id']]);
        self::assertSame($body, $stored, 'canonical Markdown must be stored byte-for-byte');
    }

    public function test_new_thread_and_reply_render_identically(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'parity']);
        $user = $this->makeUser(['username' => 'parityposter']);
        $this->actingAs($user);

        $body = "**same** ||markdown|| with `code` and [a](https://example.com)";
        $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'Parity', 'body' => $body]);
        $thread = $this->db->fetch('SELECT * FROM threads WHERE user_id = ? ORDER BY id DESC LIMIT 1', [(int) $user['id']]);
        $this->post('/t/' . (int) $thread['id'] . '/reply', ['body' => $body]);

        $rows = $this->db->fetchAll('SELECT is_op, body_html FROM posts WHERE thread_id = ? ORDER BY id ASC', [(int) $thread['id']]);
        self::assertCount(2, $rows);
        self::assertSame($rows[0]['body_html'], $rows[1]['body_html'], 'same Markdown must render identically in thread + reply');
    }
}
