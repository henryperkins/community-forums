<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\UserPreferenceRepository;
use Tests\Support\TestCase;

final class AppThreadMobileRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_only_a_successful_submission_confirms_the_matching_draft_once(): void
    {
        $board = $this->makeBoard($this->makeCategory());
        $author = $this->makeUser();
        $thread = $this->makeThread($board, $author);
        $this->actingAs($author);
        $context = '/t/' . $thread['thread_id'] . '/reply';
        $url = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];

        $failed = $this->post($context, ['body' => '', 'idempotency_key' => 'failed-mobile-send']);
        $this->assertStatus(422, $failed);
        $this->assertDontSeeText($failed, 'data-composer-completion=');
        $this->assertDontSeeText($this->get($url), 'data-composer-completion=');

        // Local recovery must also work when the optional remote draft store is off.
        $this->db->run("INSERT INTO settings (`key`, value, updated_at) VALUES ('features', ?, UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = UTC_TIMESTAMP()", ['{"server_drafts":false}']);
        $sent = $this->post($context, ['body' => 'Confirmed reply', 'idempotency_key' => 'confirmed-mobile-send']);
        $this->assertRedirect($sent);
        $page = $this->get($url);
        $this->assertSeeText($page, 'data-composer-completion=');
        $this->assertSeeText($page, 'confirmed-mobile-send');
        $this->assertSeeText($page, 'Confirmed reply');
        $this->assertDontSeeText($this->get($url), 'data-composer-completion=');
    }

    public function test_a_completion_cannot_be_replayed_in_another_login_session(): void
    {
        $board = $this->makeBoard($this->makeCategory());
        $author = $this->makeUser();
        $thread = $this->makeThread($board, $author);
        $this->actingAs($author);
        $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'Sent once', 'idempotency_key' => 'old-session']);
        $this->actingAs($author); // rotates the session while retaining other cookies
        $this->assertDontSeeText($this->get('/t/' . $thread['thread_id']), 'data-composer-completion=');
    }

    public function test_success_discards_only_the_matching_remote_draft(): void
    {
        $board = $this->makeBoard($this->makeCategory());
        $author = $this->makeUser();
        $thread = $this->makeThread($board, $author);
        $this->actingAs($author);
        $context = '/t/' . $thread['thread_id'] . '/reply';
        $key = str_replace('%', '~', rawurlencode($context));
        $this->post('/api/drafts/' . $key, ['revision' => 0, 'body' => 'Matching submitted draft']);
        $this->assertRedirect($this->post($context, ['body' => 'Matching submitted draft', 'idempotency_key' => 'matching-remote']));
        $after = json_decode($this->get('/api/drafts/' . $key)->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertNull($after['draft']);

        $this->post('/api/drafts/' . $key, ['revision' => 0, 'body' => 'Newer draft from another tab']);
        $this->assertRedirect($this->post($context, ['body' => 'Older submitted draft', 'idempotency_key' => 'older-remote']));
        $after = json_decode($this->get('/api/drafts/' . $key)->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Newer draft from another tab', $after['draft']['body']);
    }

    public function test_answer_jump_uses_each_readers_pagination_even_after_catching_up(): void
    {
        $board = $this->makeBoard($this->makeCategory());
        $author = $this->makeUser();
        $reader = $this->makeUser();
        $thread = $this->makeThread($board, $author);
        $answer = 0;
        for ($i = 0; $i < 20; $i++) {
            $answer = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Reply ' . $i]);
        }
        $this->db->run('UPDATE threads SET accepted_answer_post_id = ? WHERE id = ?', [$answer, $thread['thread_id']]);
        $url = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];
        $guest = $this->get($url);
        $this->assertSeeText($guest, 'href="' . $url . '?page=2#p' . $answer . '"');
        $this->assertSeeText($guest, '>View answer</a>');

        (new UserPreferenceRepository($this->db))->merge((int) $reader['id'], ['posts_per_page' => 10]);
        $this->actingAs($reader);
        $this->get($url . '?page=3');
        $page = $this->get($url);
        $this->assertSeeText($page, 'href="' . $url . '?page=3#p' . $answer . '"');
        $this->assertSeeText($page, '>View answer</a>');
    }

    public function test_answer_jump_honours_the_staff_deleted_post_stream_and_hides_pending_answers(): void
    {
        $board = $this->makeBoard($this->makeCategory());
        $author = $this->makeUser();
        $admin = $this->makeAdmin();
        $thread = $this->makeThread($board, $author);
        $first = 0;
        $answer = 0;
        for ($i = 0; $i < 10; $i++) {
            $answer = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Reply ' . $i]);
            $first = $first ?: $answer;
        }
        $this->db->run('UPDATE posts SET is_deleted = 1 WHERE id = ?', [$first]);
        $this->db->run('UPDATE threads SET accepted_answer_post_id = ? WHERE id = ?', [$answer, $thread['thread_id']]);
        (new UserPreferenceRepository($this->db))->merge((int) $admin['id'], ['posts_per_page' => 10]);
        $this->actingAs($admin);
        $url = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];
        $this->assertSeeText($this->get($url), 'href="' . $url . '?page=2#p' . $answer . '"');

        $this->db->run('UPDATE posts SET is_pending = 1 WHERE id = ?', [$answer]);
        $this->assertDontSeeText($this->get($url), '>View answer</a>');
    }
}
