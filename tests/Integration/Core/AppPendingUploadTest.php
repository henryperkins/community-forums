<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppPendingUploadTest extends TestCase
{
    private const BODY = 'Preserve this text ![uploading](rbup-abc123-def456)';

    public function test_wiki_edit_on_a_later_page_preserves_its_body_and_returns_to_the_post(): void
    {
        $author = $this->makeAdmin();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author);
        $posts = new \App\Repository\PostRepository($this->db);
        for ($index = 0; $index < 20; $index++) {
            $postId = $posts->create(['thread_id' => $thread['thread_id'], 'user_id' => $author['id'],
                'body' => 'Page filler.', 'body_html' => '<p>Page filler.</p>']);
        }
        $this->db->run('UPDATE boards SET wiki_enabled = 1 WHERE id = ?', [$board['id']]);
        $this->db->run('UPDATE posts SET is_wiki = 1 WHERE id = ?', [$postId]);
        $this->actingAs($author);
        $path = '/posts/' . $postId . '/wiki/edit';
        $rejected = $this->post($path, ['body' => self::BODY, 'reason' => 'Keep later-page reason']);
        $this->assertStatus(422, $rejected);
        self::assertStringContainsString(self::BODY, $rejected->body());
        self::assertStringContainsString('Keep later-page reason', $rejected->body());
        $accepted = $this->post($path, ['body' => 'Completed later-page wiki edit.']);
        $this->assertStatus(303, $accepted);
        self::assertStringContainsString('?page=2#p' . $postId, (string) $accepted->getHeader('location'));
    }

    public function test_wiki_edit_preserves_rejected_input_and_finalizes_accepted_images(): void
    {
        $author = $this->makeAdmin();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author);
        $this->db->run('UPDATE boards SET wiki_enabled = 1 WHERE id = ?', [$board['id']]);
        $this->db->run('UPDATE posts SET is_wiki = 1 WHERE id = ?', [$thread['post_id']]);
        $this->actingAs($author);
        $path = '/posts/' . $thread['post_id'] . '/wiki/edit';
        $rejected = $this->post($path, ['body' => self::BODY, 'reason' => 'Preserve my reason']);
        $this->assertStatus(422, $rejected);
        self::assertStringContainsString(self::BODY, $rejected->body());
        self::assertStringContainsString('Preserve my reason', $rejected->body());
        self::assertSame('Opening post body.', $this->db->fetchValue('SELECT body FROM posts WHERE id = ?', [$thread['post_id']]));
        $file = $this->fakeUpload(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWP4z8AAAAMBAQCc479ZAAAAAElFTkSuQmCC'));
        $upload = $this->postFile('/upload', 'image', $file);
        $this->assertStatus(200, $upload);
        $id = json_decode($upload->body(), true)['id'];
        $this->assertStatus(303, $this->post($path, ['body' => 'Wiki photo ![](/media/' . $id . ')', 'reason' => 'Add photo']));
        $attachment = $this->db->fetch('SELECT status, post_id FROM attachments WHERE id = ?', [$id]);
        self::assertSame('finalized', $attachment['status']);
        self::assertSame($thread['post_id'], (int) $attachment['post_id']);
    }

    public function test_topics_replies_and_edits_preserve_input_and_do_not_publish_pending_images(): void
    {
        $author = $this->makeAdmin();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author);
        $this->actingAs($author);
        foreach ([['/threads', ['board_id' => $board['id'], 'title' => 'Keep my title']],
            ['/t/' . $thread['thread_id'] . '/reply', []], ['/posts/' . $thread['post_id'] . '/edit', []]] as [$path, $fields]) {
            $response = $this->post($path, $fields + ['body' => self::BODY]);
            $this->assertStatus(422, $response);
            self::assertStringContainsString(self::BODY, $response->body());
            self::assertStringContainsString('not finished uploading', $response->body());
        }
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM posts'));
        self::assertSame('Opening post body.', $this->db->fetchValue('SELECT body FROM posts WHERE id = ?', [$thread['post_id']]));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT reply_count FROM threads WHERE id = ?', [$thread['thread_id']]));
        $this->assertStatus(303, $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => '`' . self::BODY . '`']));
    }

    public function test_dm_start_group_start_and_reply_reject_pending_images_without_losing_text(): void
    {
        $this->actingAs($this->makeAdmin());
        $bob = $this->makeUser(['username' => 'uploadbob']);
        $carol = $this->makeUser(['username' => 'uploadcarol']);
        foreach (['uploadbob', 'uploadbob, uploadcarol'] as $recipients) {
            $response = $this->post('/messages', ['to' => $recipients, 'body' => self::BODY]);
            $this->assertStatus(422, $response);
            self::assertStringContainsString(self::BODY, $response->body());
        }
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM dm_messages'));
        $start = $this->post('/messages', ['to' => 'uploadbob', 'body' => 'A completed message.']);
        $this->assertStatus(303, $start);
        $path = (string) $start->getHeader('location');
        $response = $this->post($path, ['body' => self::BODY]);
        $this->assertStatus(422, $response);
        self::assertStringContainsString(self::BODY, $response->body());
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM dm_messages'));
    }
}
