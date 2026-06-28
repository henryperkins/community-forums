<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use App\Repository\SettingRepository;
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

    public function test_all_write_contexts_share_the_markdown_render_pipeline(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'all-context-parity']);
        $author = $this->makeUser(['username' => 'allcontext']);
        $recipient = $this->makeUser(['username' => 'allcontextdm']);
        $this->actingAs($author);

        $body = "**same** ||markdown|| with `code`, [a](https://example.com), and :smile:";
        $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'New context', 'body' => $body]);
        $thread = $this->db->fetch('SELECT * FROM threads WHERE title = ? LIMIT 1', ['New context']);
        $newThreadHtml = (string) $this->db->fetchValue(
            'SELECT body_html FROM posts WHERE thread_id = ? AND is_op = 1',
            [(int) $thread['id']],
        );

        $this->post('/t/' . (int) $thread['id'] . '/reply', ['body' => $body]);
        $replyHtml = (string) $this->db->fetchValue(
            'SELECT body_html FROM posts WHERE thread_id = ? AND is_op = 0 ORDER BY id DESC LIMIT 1',
            [(int) $thread['id']],
        );

        $editThread = $this->makeThread($board, $author, 'Edit context', 'before edit');
        $editPostId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [(int) $editThread['thread_id']],
        );
        $this->post('/posts/' . $editPostId . '/edit', ['body' => $body]);
        $editHtml = (string) $this->db->fetchValue('SELECT body_html FROM posts WHERE id = ?', [$editPostId]);

        $this->post('/messages', ['to' => 'allcontextdm', 'body' => $body]);
        $dmHtml = (string) $this->db->fetchValue(
            'SELECT body_html FROM dm_messages WHERE user_id = ? ORDER BY id DESC LIMIT 1',
            [(int) $author['id']],
        );

        self::assertSame($newThreadHtml, $replyHtml);
        self::assertSame($newThreadHtml, $editHtml);
        self::assertSame($newThreadHtml, $dmHtml);
        self::assertStringContainsString('😄', $newThreadHtml);
    }

    public function test_shared_composer_surfaces_render_plain_textareas(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'composer-surfaces']);
        $author = $this->makeUser(['username' => 'surfaceauthor']);
        $recipient = $this->makeUser(['username' => 'surfacerecipient']);
        $thread = $this->makeThread($board, $author, 'Surface thread', 'Opening body.');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [(int) $thread['thread_id']]);
        $convId = (new ConversationRepository($this->db))->findOrCreateBetween((int) $author['id'], (int) $recipient['id']);
        (new DmMessageRepository($this->db))->create($convId, (int) $recipient['id'], 'hello', '<p>hello</p>');

        $this->actingAs($author);

        $boardPage = $this->get('/c/composer-surfaces');
        $this->assertStatus(200, $boardPage);
        self::assertStringContainsString('action="/threads"', $boardPage->body());
        self::assertStringContainsString('class="composer-input"', $boardPage->body());

        $threadPage = $this->get('/t/' . (int) $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $threadPage);
        self::assertStringContainsString('action="/t/' . (int) $thread['thread_id'] . '/reply"', $threadPage->body());
        self::assertStringContainsString('action="/posts/' . $postId . '/edit"', $threadPage->body());
        self::assertStringContainsString('class="composer-input"', $threadPage->body());

        $newDm = $this->get('/messages/new');
        $this->assertStatus(200, $newDm);
        self::assertStringContainsString('action="/messages"', $newDm->body());
        self::assertStringContainsString('class="composer-input"', $newDm->body());

        $dmThread = $this->get('/messages/' . $convId);
        $this->assertStatus(200, $dmThread);
        self::assertStringContainsString('action="/messages/' . $convId . '"', $dmThread->body());
        self::assertStringContainsString('class="composer-input"', $dmThread->body());
    }

    public function test_rich_composer_kill_switch_keeps_textarea_fallback(): void
    {
        (new SettingRepository($this->db))->set('features', ['rich_composer' => false]);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'textarea-fallback']);
        $user = $this->makeUser(['username' => 'fallbackuser']);
        $thread = $this->makeThread($board, $user, 'Fallback thread', 'Fallback body.');
        $this->actingAs($user);

        $page = $this->get('/t/' . (int) $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $page);
        self::assertStringContainsString('<textarea name="body"', $page->body());
        self::assertStringNotContainsString('/assets/composer.js', $page->body());

        $reply = $this->post('/t/' . (int) $thread['thread_id'] . '/reply', ['body' => 'Textarea fallback reply.']);
        $this->assertRedirectContains($reply, '/t/' . (int) $thread['thread_id']);
        self::assertSame(2, (int) $this->db->fetchValue('SELECT COUNT(*) FROM posts WHERE thread_id = ?', [(int) $thread['thread_id']]));
    }

    public function test_drafts_route_renders_browser_local_shell(): void
    {
        $user = $this->makeUser(['username' => 'draftshell']);
        $this->actingAs($user);

        $page = $this->get('/drafts');
        $this->assertStatus(200, $page);
        self::assertStringContainsString('data-drafts-list', $page->body());
        self::assertStringContainsString('Drafts are browser-local', $page->body());
    }

    /**
     * The inline post-edit form opts out of local draft autosave (data-no-draft):
     * its textarea is server-pre-filled with the current body, so a saved draft
     * can never be restored into it and there is no Drafts resume target —
     * autosaving would only leave a misleading, unrecoverable "Post edit" draft
     * that the next page load discards (P3-03 draft-loss follow-up).
     */
    public function test_inline_edit_form_opts_out_of_draft_autosave(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'edit-nodraft']);
        $user = $this->makeUser(['username' => 'editnodraft']);
        $thread = $this->makeThread($board, $user, 'Edit opt-out', 'Original body.');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [(int) $thread['thread_id']]);
        $this->actingAs($user);

        $page = $this->get('/t/' . (int) $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $page);
        self::assertMatchesRegularExpression(
            '/action="\/posts\/' . $postId . '\/edit"[^>]*\bdata-no-draft\b/',
            $page->body(),
            'the inline edit form must carry data-no-draft so composer.js skips draft autosave',
        );
    }
}
