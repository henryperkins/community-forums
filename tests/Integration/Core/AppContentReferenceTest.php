<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardMemberRepository;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppContentReferenceTest extends TestCase
{
    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_post_references_are_persisted_and_rendered_through_read_gate(): void
    {
        $this->makeAdmin();
        $this->setFlags(['content_references' => true]);
        $author = $this->makeUser(['username' => 'refauthor']);
        $member = $this->makeUser(['username' => 'refmember']);
        $category = $this->makeCategory('References');
        $publicBoard = $this->makeBoard($category, ['slug' => 'public-ref-board', 'name' => 'Public refs']);
        $privateBoard = $this->makeBoard($category, ['slug' => 'private-ref-board', 'name' => 'Private refs', 'visibility' => 'private']);
        (new BoardMemberRepository($this->db))->add((int) $privateBoard['id'], (int) $member['id'], null);

        $publicTarget = $this->makeThread($publicBoard, $author, 'Public Target Visible', 'public body');
        $privateTarget = $this->makeThread($privateBoard, $member, 'Private Target Secret', 'private body');

        $this->actingAs($author);
        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $publicBoard['id'],
            'title' => 'Source references',
            'body' => 'See [the public thread](/t/' . $publicTarget['thread_id'] . '-' . $publicTarget['slug'] . ') and [the restricted thread](/t/' . $privateTarget['thread_id'] . '-' . $privateTarget['slug'] . ').',
        ]));
        $source = $this->db->fetch("SELECT id AS thread_id, slug FROM threads WHERE title = 'Source references' ORDER BY id DESC LIMIT 1");
        self::assertIsArray($source);
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$source['thread_id']]);

        self::assertSame(2, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM content_references WHERE source_type = 'post' AND source_id = ?",
            [$opId],
        ));

        $this->logoutClient();
        $guestPage = $this->get('/t/' . $source['thread_id'] . '-' . $source['slug']);
        $this->assertStatus(200, $guestPage);
        self::assertStringContainsString('Public Target Visible', $guestPage->body());
        self::assertStringNotContainsString('Private Target Secret', $guestPage->body());

        $this->actingAs($member);
        $memberPage = $this->get('/t/' . $source['thread_id'] . '-' . $source['slug']);
        $this->assertStatus(200, $memberPage);
        self::assertStringContainsString('Public Target Visible', $memberPage->body());
        self::assertStringContainsString('Private Target Secret', $memberPage->body());
    }
}
