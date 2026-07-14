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

    public function test_thread_read_renders_a_missing_html_cache_without_writing_it(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'post-cache-fallback']);
        $author = $this->makeUser(['username' => 'postcachefallback']);
        $thread = $this->makeThread($board, $author, 'Legacy post cache', "**Rendered post**\n\n3. third");
        $postId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [(int) $thread['thread_id']],
        );
        $this->db->run('UPDATE posts SET body_html = NULL WHERE id = ?', [$postId]);

        $page = $this->get('/t/' . (int) $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString('<strong>Rendered post</strong>', $page->body());
        self::assertStringContainsString('<ol start="3">', $page->body());
        self::assertNull($this->db->fetchValue('SELECT body_html FROM posts WHERE id = ?', [$postId]));
    }

    public function test_dm_read_renders_an_empty_html_cache_without_writing_it(): void
    {
        $author = $this->makeUser(['username' => 'dmcacheauthor']);
        $recipient = $this->makeUser(['username' => 'dmcacherecipient']);
        $conversationId = (new ConversationRepository($this->db))->findOrCreateBetween(
            (int) $author['id'],
            (int) $recipient['id'],
        );
        $messageId = (new DmMessageRepository($this->db))->create(
            $conversationId,
            (int) $recipient['id'],
            '**Rendered message**',
            '',
        );
        $this->actingAs($author);

        $page = $this->get('/messages/' . $conversationId);

        $this->assertStatus(200, $page);
        self::assertStringContainsString('<strong>Rendered message</strong>', $page->body());
        self::assertSame('', $this->db->fetchValue('SELECT body_html FROM dm_messages WHERE id = ?', [$messageId]));
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
        self::assertStringContainsString('class="post-body formatted-content"', $threadPage->body());
        self::assertStringContainsString('action="/t/' . (int) $thread['thread_id'] . '/reply"', $threadPage->body());
        self::assertStringContainsString('action="/posts/' . $postId . '/edit"', $threadPage->body());
        self::assertStringContainsString('class="composer-input"', $threadPage->body());
        self::assertStringContainsString('class="thread thread-conversation thread-study"', $threadPage->body());
        self::assertStringContainsString('class="thread-scroll"', $threadPage->body());
        self::assertStringContainsString('class="thread-dock"', $threadPage->body());
        self::assertStringContainsString('class="composer composer-shell reply-composer thread-composer-card"', $threadPage->body());
        self::assertStringContainsString('data-thread-composer', $threadPage->body());

        $newDm = $this->get('/messages/new');
        $this->assertStatus(200, $newDm);
        self::assertStringContainsString('action="/messages"', $newDm->body());
        self::assertStringContainsString('class="composer-input"', $newDm->body());

        $dmThread = $this->get('/messages/' . $convId);
        $this->assertStatus(200, $dmThread);
        self::assertStringContainsString('class="dm-body formatted-content"', $dmThread->body());
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
        self::assertMatchesRegularExpression('/<textarea\b[^>]*class="composer-input"[^>]*name="body"/', $page->body());
        self::assertStringNotContainsString('/assets/composer.js', $page->body());

        $reply = $this->post('/t/' . (int) $thread['thread_id'] . '/reply', ['body' => 'Textarea fallback reply.']);
        $this->assertRedirectContains($reply, '/t/' . (int) $thread['thread_id']);
        self::assertSame(2, (int) $this->db->fetchValue('SELECT COUNT(*) FROM posts WHERE thread_id = ?', [(int) $thread['thread_id']]));
    }

    public function test_wysiwyg_editor_assets_load_by_default_and_honor_flag_and_kill_switch(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'wysiwyg-assets']);
        $user = $this->makeUser(['username' => 'wysiwygassets']);
        $this->actingAs($user);

        // GA default-on (2026-07-02): with no features override the Milkdown
        // bundle loads alongside the shared composer bridge.
        $defaultPage = $this->get('/c/wysiwyg-assets');
        self::assertStringContainsString('/assets/composer.js', $defaultPage->body());
        self::assertStringContainsString('/assets/wysiwyg-composer.css', $defaultPage->body());
        self::assertStringContainsString('<script type="module" src="/assets/wysiwyg-composer.js"></script>', $defaultPage->body());
        self::assertStringContainsString('data-wysiwyg-composer="1"', $defaultPage->body());

        // Operator rollback: the narrow flag removes only the WYSIWYG layer;
        // the enhanced Markdown composer keeps loading.
        (new SettingRepository($this->db))->set('features', ['wysiwyg_composer' => false]);
        $disabledPage = $this->get('/c/wysiwyg-assets');
        self::assertStringContainsString('/assets/composer.js', $disabledPage->body());
        self::assertStringNotContainsString('/assets/wysiwyg-composer.js', $disabledPage->body());
        self::assertStringNotContainsString('data-wysiwyg-composer="1"', $disabledPage->body());

        // Broad kill switch: rich_composer=false keeps every enhanced asset
        // out even though wysiwyg_composer stays true by default.
        (new SettingRepository($this->db))->set('features', ['rich_composer' => false]);
        $killedPage = $this->get('/c/wysiwyg-assets');
        self::assertStringNotContainsString('/assets/composer.js', $killedPage->body());
        self::assertStringNotContainsString('/assets/wysiwyg-composer.js', $killedPage->body());
        self::assertStringNotContainsString('data-wysiwyg-composer="1"', $killedPage->body());
    }

    public function test_drafts_route_renders_browser_local_shell(): void
    {
        // With server_drafts graduated to default-on, the server-owned list is
        // exercised by AppServerDraftsTest; disable the flag here to assert the
        // browser-local fallback shell operators keep after a rollback.
        (new SettingRepository($this->db))->set('features', ['server_drafts' => false]);
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

    /**
     * A failed inline edit re-renders the thread (HTTP 422) with this post's edit
     * form re-opened and the rejected text + error preserved, instead of
     * redirecting to the thread and dropping the typed edit (symmetric with the
     * reply/DM re-render). The post itself is left unchanged.
     */
    public function test_failed_inline_edit_rerenders_thread_with_typed_body(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'edit-rerender']);
        $user = $this->makeUser(['username' => 'editrerender']);
        $thread = $this->makeThread($board, $user, 'Editable', 'Original body.');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [(int) $thread['thread_id']]);
        $this->actingAs($user);

        $tooLong = str_repeat('y', 20001);                      // > limits.post_body_max (20000) → ValidationException
        $res = $this->post('/posts/' . $postId . '/edit', ['body' => $tooLong]);

        // Re-rendered in place (not a PRG redirect to the thread) with the text kept.
        $this->assertStatus(422, $res);
        self::assertStringContainsString('Your post is too long.', $res->body());
        self::assertStringContainsString($tooLong, $res->body(), 'the rejected edit text is preserved in the re-opened edit form');
        self::assertStringContainsString(
            'class="post-native-disclosure post-edit" id="post-edit-' . $postId . '" open',
            $res->body(),
            'the failing post\'s native edit disclosure is re-opened',
        );
        // The stored post is untouched by the failed edit.
        self::assertSame('Original body.', (string) $this->db->fetchValue('SELECT body FROM posts WHERE id = ?', [$postId]));
    }

    public function test_failed_reply_rerenders_the_thread_with_an_expanded_dock(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'reply-rerender']);
        $user = $this->makeUser(['username' => 'replyrerender']);
        $thread = $this->makeThread($board, $user, 'Reply validation', 'Opening body.');
        $this->actingAs($user);

        $tooLong = str_repeat('z', 20001);
        $response = $this->post('/t/' . (int) $thread['thread_id'] . '/reply', [
            'body' => $tooLong,
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);

        $this->assertStatus(422, $response);
        self::assertStringContainsString('Your post is too long.', $response->body());
        self::assertStringContainsString($tooLong, $response->body(), 'the rejected reply body remains in the composer');
        self::assertStringContainsString('class="composer composer-shell reply-composer thread-composer-card is-expanded"', $response->body());
        self::assertStringContainsString('data-thread-composer', $response->body());
    }

    /**
     * pageOfPost returns the 1-based page (at $perPage) on which a post falls in
     * the public thread render order, so a failed inline edit can re-render the
     * page that actually contains the post (not just page 1).
     */
    public function test_page_of_post_locates_the_post_across_pages(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'pageofpost']);
        $author = $this->makeUser(['username' => 'pageofpostauthor']);
        $thread = $this->makeThread($board, $author, 'Paged', 'OP body.');
        $threadId = (int) $thread['thread_id'];

        $repo = $this->posts();
        $ids = [(int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId])];
        for ($i = 1; $i <= 5; $i++) {
            $ids[] = $repo->create([
                'thread_id' => $threadId,
                'user_id' => (int) $author['id'],
                'body' => "reply {$i}",
                'body_html' => "<p>reply {$i}</p>",
            ]);
        }

        // perPage = 2 → 6 posts over 3 pages in (created_at, id ASC) order.
        self::assertSame(1, $repo->pageOfPost($threadId, $ids[0], 2));
        self::assertSame(1, $repo->pageOfPost($threadId, $ids[1], 2));
        self::assertSame(2, $repo->pageOfPost($threadId, $ids[2], 2));
        self::assertSame(2, $repo->pageOfPost($threadId, $ids[3], 2));
        self::assertSame(3, $repo->pageOfPost($threadId, $ids[4], 2));
        self::assertSame(3, $repo->pageOfPost($threadId, $ids[5], 2));
        // A missing/hidden post falls back to page 1.
        self::assertSame(1, $repo->pageOfPost($threadId, 999999, 2));
    }

    public function test_composer_forms_expose_bridge_context_metadata(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'bridge-meta']);
        $author = $this->makeUser(['username' => 'bridgemeta']);
        $recipient = $this->makeUser(['username' => 'bridgedm']);
        $thread = $this->makeThread($board, $author, 'Bridge meta', 'Opening');
        $this->actingAs($author);

        $boardPage = $this->get('/c/bridge-meta');
        self::assertStringContainsString('data-composer-context="new_thread"', $boardPage->body());
        self::assertStringContainsString('data-composer-target-id="' . (int) $board['id'] . '"', $boardPage->body());

        $threadPage = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        self::assertStringContainsString('data-composer-context="reply"', $threadPage->body());
        self::assertStringContainsString('data-composer-target-id="' . $thread['thread_id'] . '"', $threadPage->body());
        self::assertStringContainsString('data-composer-context="edit"', $threadPage->body());

        $newDm = $this->get('/messages/new');
        self::assertStringContainsString('data-composer-context="dm"', $newDm->body());
    }
}
