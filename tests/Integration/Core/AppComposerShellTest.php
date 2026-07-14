<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use Tests\Support\TestCase;

final class AppComposerShellTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(['username' => 'shellbootstrap']);
    }

    public function test_all_eight_body_forms_render_the_shared_shell_contract(): void
    {
        $fixture = $this->makeComposerFixture('allmounts');
        $this->actingAs($fixture['author']);

        $boardPage = $this->get('/c/' . $fixture['board']['slug']);
        $this->assertStatus(200, $boardPage);

        $threadPage = $this->get('/t/' . $fixture['thread']['thread_id'] . '-' . $fixture['thread']['slug']);
        $this->assertStatus(200, $threadPage);

        // There is intentionally no GET /compose route. The dedicated composer
        // is the anti-draft-loss view used by a rejected POST /threads request.
        $composePage = $this->post('/threads', [
            'board_id' => (int) $fixture['board']['id'],
            'title' => '',
            'body' => '',
        ]);
        $this->assertStatus(422, $composePage);

        $dmNewPage = $this->get('/messages/new', ['to' => $fixture['recipient']['username']]);
        $this->assertStatus(200, $dmNewPage);

        $dmListPage = $this->get('/messages');
        $this->assertStatus(200, $dmListPage);

        $dmThreadPage = $this->get('/messages/' . $fixture['conversation_id']);
        $this->assertStatus(200, $dmThreadPage);

        $threadId = (int) $fixture['thread']['thread_id'];
        $postId = (int) $fixture['post_id'];
        $boardId = (int) $fixture['board']['id'];
        $conversationId = (int) $fixture['conversation_id'];

        $specs = [
            [
                'page' => $threadPage->body(),
                'instance' => 'reply-thread-' . $threadId,
                'action' => '/t/' . $threadId . '/reply',
                'context' => 'reply',
                'target' => $threadId,
                'maxlength' => 20000,
                'label' => 'Reply',
                'placeholder' => 'Reply to “' . $fixture['title'] . '”…',
            ],
            [
                'page' => $boardPage->body(),
                'instance' => 'new-thread-board-' . $boardId,
                'action' => '/threads',
                'context' => 'new_thread',
                'target' => $boardId,
                'maxlength' => 20000,
                'label' => 'Create topic',
                'placeholder' => 'Start a new topic in #' . $fixture['board']['slug'] . '…',
            ],
            [
                'page' => $composePage->body(),
                'instance' => 'new-thread-page',
                'action' => '/threads',
                'context' => 'new_thread',
                'target' => $boardId,
                'maxlength' => 20000,
                'label' => 'Create topic',
                'placeholder' => 'Start a new topic in #' . $fixture['board']['slug'] . '…',
            ],
            [
                'page' => $dmThreadPage->body(),
                'instance' => 'dm-conversation-' . $conversationId,
                'action' => '/messages/' . $conversationId,
                'context' => 'dm',
                'target' => $conversationId,
                'maxlength' => 5000,
                'label' => 'Send',
                'placeholder' => 'Message @' . $fixture['recipient']['username'] . '…',
            ],
            [
                'page' => $dmNewPage->body(),
                'instance' => 'dm-new-page',
                'action' => '/messages',
                'context' => 'dm',
                'target' => 0,
                'maxlength' => 5000,
                'label' => 'Send',
                'placeholder' => 'Message @' . $fixture['recipient']['username'] . '…',
            ],
            [
                'page' => $dmListPage->body(),
                'instance' => 'dm-new-dialog',
                'action' => '/messages',
                'context' => 'dm',
                'target' => 0,
                'maxlength' => 5000,
                'label' => 'Send',
                'placeholder' => 'Message @recipient…',
            ],
            [
                'page' => $threadPage->body(),
                'instance' => 'edit-post-' . $postId,
                'action' => '/posts/' . $postId . '/edit',
                'context' => 'edit',
                'target' => $postId,
                'maxlength' => 20000,
                'label' => 'Save changes',
                'placeholder' => 'Edit your post…',
            ],
            [
                'page' => $threadPage->body(),
                'instance' => 'wiki-post-' . $postId,
                'action' => '/posts/' . $postId . '/wiki/edit',
                'context' => 'edit',
                'target' => $postId,
                'maxlength' => 20000,
                'label' => 'Save wiki edit',
                'placeholder' => 'Edit wiki content…',
            ],
        ];

        foreach ($specs as $spec) {
            $form = $this->composerForm($spec['page'], $spec['instance']);
            $escapedPlaceholder = htmlspecialchars($spec['placeholder'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $escapedLabel = htmlspecialchars($spec['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            self::assertSame(1, substr_count($spec['page'], 'data-composer-instance="' . $spec['instance'] . '"'));
            self::assertMatchesRegularExpression('/^<form class="composer composer-shell(?: [^"]*)?"/s', $form);
            self::assertSame(1, preg_match_all('/<textarea\b[^>]*class="composer-input"[^>]*>/s', $form));
            self::assertStringContainsString('action="' . $spec['action'] . '"', $form);
            self::assertStringContainsString('data-composer-context="' . $spec['context'] . '"', $form);
            self::assertStringContainsString('data-composer-target-id="' . $spec['target'] . '"', $form);
            self::assertSame(1, substr_count($form, 'name="_token"'));
            self::assertSame(1, substr_count($form, 'name="idempotency_key"'));
            self::assertMatchesRegularExpression('/name="idempotency_key" value="[a-f0-9]{32}"/', $form);
            self::assertStringContainsString('maxlength="' . $spec['maxlength'] . '"', $form);
            self::assertStringContainsString('placeholder="' . $escapedPlaceholder . '"', $form);
            self::assertStringContainsString('aria-label="' . $escapedLabel . '"', $form);
            self::assertSame(1, substr_count($form, 'class="composer-box"'));
            self::assertSame(1, substr_count($form, 'data-composer-upload-tray'));
            self::assertSame(1, substr_count($form, 'class="composer-actions-start"'));
            self::assertSame(1, substr_count($form, 'class="composer-actions-end"'));
            self::assertSame(1, substr_count($form, 'data-composer-draft-slot'));
            self::assertSame(1, substr_count($form, 'data-composer-counter-slot'));
            self::assertSame(1, substr_count($form, 'data-composer-submit-status'));
        }

        $reply = $this->composerForm($threadPage->body(), 'reply-thread-' . $threadId);
        self::assertStringContainsString('id="reply"', $reply);
        self::assertStringContainsString('reply-composer thread-composer-card', $reply);
        self::assertStringContainsString('data-thread-composer', $reply);
        self::assertStringContainsString('class="composer-identity"', $reply);

        $dmReply = $this->composerForm($dmThreadPage->body(), 'dm-conversation-' . $conversationId);
        self::assertStringContainsString('dm-composer', $dmReply);
        self::assertStringContainsString('class="composer-identity"', $dmReply);

        $dmQuick = $this->composerForm($dmListPage->body(), 'dm-new-dialog');
        self::assertStringContainsString('data-no-wysiwyg', $dmQuick);

        $edit = $this->composerForm($threadPage->body(), 'edit-post-' . $postId);
        $wiki = $this->composerForm($threadPage->body(), 'wiki-post-' . $postId);
        foreach ([$edit, $wiki] as $form) {
            self::assertStringContainsString('data-no-draft', $form);
            self::assertStringNotContainsString('composer-identity', $form);
        }
        self::assertMatchesRegularExpression(
            '/<\/textarea>.*<input type="text" name="reason"[^>]*>.*data-composer-upload-tray/s',
            $wiki,
            'the wiki reason field stays inside the box after the canonical textarea',
        );
    }

    public function test_anonymity_is_labeled_described_and_mount_specific(): void
    {
        $fixture = $this->makeComposerFixture('anonymous');
        $this->actingAs($fixture['author']);

        $threadId = (int) $fixture['thread']['thread_id'];
        $replyPage = $this->get('/t/' . $threadId . '-' . $fixture['thread']['slug']);
        $reply = $this->composerForm($replyPage->body(), 'reply-thread-' . $threadId);
        $checkboxId = 'composer-anonymous-reply-thread-' . $threadId;
        $disclosureId = 'composer-anonymous-disclosure-reply-thread-' . $threadId;

        self::assertStringContainsString('id="' . $checkboxId . '"', $reply);
        self::assertStringContainsString('aria-describedby="' . $disclosureId . '"', $reply);
        self::assertStringContainsString('for="' . $checkboxId . '">Anonymous</label>', $reply);
        self::assertStringContainsString('id="' . $disclosureId . '"', $reply);
        self::assertStringContainsString('Your name is hidden from other members; moderators can still see it.', $reply);
        self::assertDoesNotMatchRegularExpression('/<input\b[^>]*name="is_anonymous"[^>]*\bchecked\b[^>]*>/', $reply);

        $failed = $this->post('/t/' . $threadId . '/reply', [
            'body' => str_repeat('x', 20001),
            'is_anonymous' => '1',
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
        $this->assertStatus(422, $failed);
        $checkedReply = $this->composerForm($failed->body(), 'reply-thread-' . $threadId);
        self::assertMatchesRegularExpression('/<input\b[^>]*name="is_anonymous"[^>]*\bchecked\b[^>]*>/', $checkedReply);
        self::assertStringContainsString('id="' . $disclosureId . '"', $checkedReply);

        $compose = $this->post('/threads', [
            'board_id' => (int) $fixture['board']['id'],
            'title' => '',
            'body' => '',
        ]);
        $this->assertStatus(422, $compose);
        $dedicated = $this->composerForm($compose->body(), 'new-thread-page');
        self::assertStringContainsString('Only takes effect on boards that allow it; your name stays visible to moderators.', $dedicated);
        self::assertStringNotContainsString('Your name is hidden from other members; moderators can still see it.', $dedicated);

        $privateBoard = $this->makeBoard($this->makeCategory('No anonymity'), [
            'slug' => 'no-anonymous-shell',
            'allow_anonymous' => 0,
        ]);
        $privatePage = $this->get('/c/' . $privateBoard['slug']);
        $privateForm = $this->composerForm($privatePage->body(), 'new-thread-board-' . (int) $privateBoard['id']);
        self::assertStringNotContainsString('name="is_anonymous"', $privateForm);
        self::assertStringNotContainsString('composer-anonymous-disclosure', $privateForm);
    }

    public function test_show_avatars_off_hides_identity_monograms_in_every_identity_bearing_mount_and_422_path(): void
    {
        $fixture = $this->makeComposerFixture('avatarprefs');
        $this->actingAs($fixture['author']);
        $this->post('/settings/preferences', [
            'show_signatures' => '1',
            'show_reactions' => '1',
            'thread_sort' => 'last_post',
        ]);

        $threadId = (int) $fixture['thread']['thread_id'];
        $boardId = (int) $fixture['board']['id'];
        $conversationId = (int) $fixture['conversation_id'];
        $pages = [
            [$this->get('/t/' . $threadId . '-' . $fixture['thread']['slug'])->body(), 'reply-thread-' . $threadId],
            [$this->get('/c/' . $fixture['board']['slug'])->body(), 'new-thread-board-' . $boardId],
            [$this->post('/threads', [
                'board_id' => $boardId,
                'title' => '',
                'body' => '',
            ])->body(), 'new-thread-page'],
            [$this->post('/messages', [
                'to' => $fixture['recipient']['username'],
                'body' => '',
            ])->body(), 'dm-new-page'],
            [$this->get('/messages')->body(), 'dm-new-dialog'],
            [$this->post('/messages/' . $conversationId, ['body' => ''])->body(), 'dm-conversation-' . $conversationId],
        ];

        foreach ($pages as [$page, $instance]) {
            $form = $this->composerForm($page, $instance);
            self::assertStringContainsString('class="composer-identity"', $form, $instance . ' keeps its identity label');
            self::assertStringNotContainsString('class="monogram', $form, $instance . ' honors show_avatars=false');
        }
    }

    public function test_failed_reply_and_edit_keep_body_error_and_expanded_shell_state(): void
    {
        $fixture = $this->makeComposerFixture('validation');
        $this->actingAs($fixture['author']);
        $tooLong = str_repeat('z', 20001);
        $threadId = (int) $fixture['thread']['thread_id'];
        $postId = (int) $fixture['post_id'];

        $replyResponse = $this->post('/t/' . $threadId . '/reply', [
            'body' => $tooLong,
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
        $this->assertStatus(422, $replyResponse);
        $reply = $this->composerForm($replyResponse->body(), 'reply-thread-' . $threadId);
        self::assertStringContainsString('thread-composer-card is-expanded', $reply);
        self::assertStringContainsString('Your post is too long.', $reply);
        self::assertStringContainsString($tooLong, $reply);

        $editResponse = $this->post('/posts/' . $postId . '/edit', ['body' => $tooLong]);
        $this->assertStatus(422, $editResponse);
        self::assertStringContainsString('id="post-edit-' . $postId . '" open', $editResponse->body());
        $edit = $this->composerForm($editResponse->body(), 'edit-post-' . $postId);
        self::assertStringContainsString('Your post is too long.', $edit);
        self::assertStringContainsString($tooLong, $edit);
        self::assertStringContainsString('data-no-draft', $edit);
    }

    /** @return array{author:array<string,mixed>,recipient:array<string,mixed>,board:array<string,mixed>,thread:array{thread_id:int,slug:string},title:string,post_id:int,conversation_id:int} */
    private function makeComposerFixture(string $suffix): array
    {
        $author = $this->makeAdmin([
            'username' => 'shellauthor' . $suffix,
            'display_name' => 'Shell Author ' . $suffix,
        ]);
        $recipient = $this->makeUser([
            'username' => 'shellfriend' . $suffix,
            'display_name' => 'Shell Friend ' . $suffix,
        ]);
        $board = $this->makeBoard($this->makeCategory('Composer ' . $suffix), [
            'slug' => 'composer-' . $suffix,
            'allow_anonymous' => 1,
        ]);
        $this->db->run('UPDATE boards SET wiki_enabled = 1 WHERE id = ?', [(int) $board['id']]);
        $board['wiki_enabled'] = 1;

        $title = 'Shell “fixture” "<& ' . $suffix;
        $thread = $this->makeThread($board, $author, $title, 'Opening body.');
        $postId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [(int) $thread['thread_id']],
        );
        $this->db->run('UPDATE posts SET is_wiki = 1 WHERE id = ?', [$postId]);

        $conversationId = (new ConversationRepository($this->db))->findOrCreateBetween(
            (int) $author['id'],
            (int) $recipient['id'],
        );
        (new DmMessageRepository($this->db))->create(
            $conversationId,
            (int) $recipient['id'],
            'A private hello.',
            '<p>A private hello.</p>',
        );

        return [
            'author' => $author,
            'recipient' => $recipient,
            'board' => $board,
            'thread' => $thread,
            'title' => $title,
            'post_id' => $postId,
            'conversation_id' => $conversationId,
        ];
    }

    private function composerForm(string $html, string $instance): string
    {
        $pattern = '/<form\b(?=[^>]*data-composer-instance="'
            . preg_quote($instance, '/')
            . '")[^>]*>.*?<\/form>/s';
        self::assertSame(1, preg_match($pattern, $html, $matches), 'missing composer instance ' . $instance);

        return $matches[0];
    }
}
