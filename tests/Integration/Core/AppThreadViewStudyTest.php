<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppThreadViewStudyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_post_titles_use_the_real_title_service_and_stay_hidden_for_anonymous_posts(): void
    {
        $author = $this->makeUser([
            'username' => 'study_title_author',
        ]);
        $anonymous = $this->makeUser([
            'username' => 'study_hidden_title',
        ]);
        $this->db->run('UPDATE users SET title = ?, reputation = ? WHERE id = ?', ['Archivist', 5, $author['id']]);
        $this->db->run('UPDATE users SET title = ?, reputation = ? WHERE id = ?', ['Secret Warden', 1000, $anonymous['id']]);
        $board = $this->makeBoard($this->makeCategory('Study Titles'), ['allow_anonymous' => 1]);
        $thread = $this->makeThread($board, $author, 'Titles remain cosmetic', 'Opening record.');
        $this->posting()->reply($this->userEntity($anonymous), (int) $thread['thread_id'], [
            'body' => 'A masked contribution.',
            'is_anonymous' => 1,
        ]);

        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString('data-author-title="Archivist"', $page->body());
        self::assertStringNotContainsString('Secret Warden', $page->body());
        self::assertStringNotContainsString('data-author-title="Legend"', $page->body());
    }
}
