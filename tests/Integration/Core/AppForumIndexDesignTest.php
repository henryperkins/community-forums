<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppForumIndexDesignTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(['username' => 'forum_index_admin']);
    }

    public function test_guest_forum_index_totals_and_rows_only_include_listed_boards(): void
    {
        $category = $this->makeCategory('Design');
        $public = $this->makeBoard($category, ['slug' => 'public-design-board', 'description' => 'Visible design work.']);
        $hidden = $this->makeBoard($category, ['slug' => 'hidden-design-board', 'visibility' => 'hidden']);
        $this->db->run('UPDATE boards SET thread_count = 7, post_count = 42 WHERE id = ?', [(int) $public['id']]);
        $this->db->run('UPDATE boards SET thread_count = 90, post_count = 900 WHERE id = ?', [(int) $hidden['id']]);

        $response = $this->get('/');
        $body = $response->body();

        $this->assertStatus(200, $response);
        self::assertStringContainsString('class="forum-directory__hero"', $body);
        self::assertStringContainsString('data-directory-pane="boards"', $body);
        self::assertStringContainsString('>Every board in the valley<', $body);
        self::assertStringContainsString('Your own cross-board queue is the', $body);
        self::assertStringContainsString('href="/inbox"', $body);
        self::assertStringContainsString('href="/?pane=boards"', $body);
        self::assertStringContainsString('href="/?pane=tags"', $body);
        self::assertStringContainsString('href="/?pane=notices"', $body);
        self::assertStringContainsString('href="/?pane=connections"', $body);
        self::assertStringContainsString('data-forum-total="boards">1 board', $body);
        self::assertStringContainsString('data-forum-total="topics">7 topics', $body);
        self::assertStringContainsString('data-forum-total="posts">42 posts', $body);
        self::assertStringContainsString('href="/c/public-design-board"', $body);
        self::assertStringContainsString('data-directory-board="public-design-board"', $body);
        self::assertStringNotContainsString('hidden-design-board', $body);
        self::assertStringNotContainsString('data-inbox-list', $body);
        self::assertStringNotContainsString('composer-details', $body);
    }

    public function test_signed_in_shared_navigation_places_and_marks_each_primary_route(): void
    {
        $this->actingAs($this->makeUser(['username' => 'route_reader']));

        $home = $this->get('/');
        $inbox = $this->get('/inbox');
        $messages = $this->get('/messages');

        $this->assertStatus(200, $home);
        $this->assertStatus(200, $inbox);
        $this->assertStatus(200, $messages);

        self::assertStringContainsString('data-primary-route="boards"', $home->body());
        self::assertStringContainsString('data-primary-route="inbox"', $inbox->body());
        self::assertStringContainsString('data-primary-route="messages"', $messages->body());

        $this->assertActiveRoute($home->body(), '/');
        $this->assertActiveRoute($inbox->body(), '/inbox');
        $this->assertActiveRoute($messages->body(), '/messages');
    }

    private function assertActiveRoute(string $body, string $href): void
    {
        self::assertMatchesRegularExpression(
            '~<a\\b(?=[^>]*href="' . preg_quote($href, '~') . '")(?=[^>]*aria-current="page")[^>]*>~',
            $body,
        );
    }
}
