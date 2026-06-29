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

    public function test_dm_message_references_are_persisted_and_rendered_through_read_gate(): void
    {
        $this->makeAdmin();
        $this->setFlags(['content_references' => true]);
        $sender = $this->makeUser(['username' => 'dmrefsender']);
        $recipient = $this->makeUser(['username' => 'dmrefrecipient']);
        $privateMember = $this->makeUser(['username' => 'dmrefmember']);
        $category = $this->makeCategory('DM References');
        $publicBoard = $this->makeBoard($category, ['slug' => 'dm-public-ref', 'name' => 'DM public refs']);
        $privateBoard = $this->makeBoard($category, ['slug' => 'dm-private-ref', 'name' => 'DM private refs', 'visibility' => 'private']);
        (new BoardMemberRepository($this->db))->add((int) $privateBoard['id'], (int) $privateMember['id'], null);

        $publicTarget = $this->makeThread($publicBoard, $sender, 'DM Public Target', 'public body');
        $privateTarget = $this->makeThread($privateBoard, $privateMember, 'DM Private Target', 'private body');

        $this->actingAs($sender);
        $this->assertRedirect($this->post('/messages', [
            'to' => 'dmrefrecipient',
            'body' => 'See [public](/t/' . $publicTarget['thread_id'] . '-' . $publicTarget['slug'] . ') and [private](/t/' . $privateTarget['thread_id'] . '-' . $privateTarget['slug'] . ').',
        ]));
        $conversationId = (int) $this->db->fetchValue('SELECT id FROM conversations ORDER BY id DESC LIMIT 1');
        $messageId = (int) $this->db->fetchValue('SELECT id FROM dm_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 1', [$conversationId]);

        self::assertSame(2, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM content_references WHERE source_type = 'dm_message' AND source_id = ?",
            [$messageId],
        ));

        $this->actingAs($recipient);
        $recipientPage = $this->get('/messages/' . $conversationId);
        $this->assertStatus(200, $recipientPage);
        self::assertStringContainsString('DM Public Target', $recipientPage->body());
        self::assertStringNotContainsString('DM Private Target', $recipientPage->body());
    }

    public function test_summary_references_are_persisted_and_rendered_through_read_gate(): void
    {
        $this->setFlags(['content_references' => true, 'community_memory' => true]);
        $admin = $this->makeAdmin(['username' => 'summaryadmin']);
        $author = $this->makeUser(['username' => 'summaryauthor']);
        $member = $this->makeUser(['username' => 'summarymember']);
        $category = $this->makeCategory('Summary References');
        $sourceBoard = $this->makeBoard($category, ['slug' => 'summary-source', 'name' => 'Summary source']);
        $publicBoard = $this->makeBoard($category, ['slug' => 'summary-public-ref', 'name' => 'Summary public refs']);
        $privateBoard = $this->makeBoard($category, ['slug' => 'summary-private-ref', 'name' => 'Summary private refs', 'visibility' => 'private']);
        (new BoardMemberRepository($this->db))->add((int) $privateBoard['id'], (int) $member['id'], null);

        $source = $this->makeThread($sourceBoard, $author, 'Summary source topic', 'source body');
        $publicTarget = $this->makeThread($publicBoard, $author, 'Summary Public Target', 'public body');
        $privateTarget = $this->makeThread($privateBoard, $member, 'Summary Private Target', 'private body');

        $this->actingAs($admin);
        $this->assertRedirect($this->post('/t/' . $source['thread_id'] . '/summary', [
            'body' => 'Summary cites [public](/t/' . $publicTarget['thread_id'] . '-' . $publicTarget['slug'] . ') and [private](/t/' . $privateTarget['thread_id'] . '-' . $privateTarget['slug'] . ').',
            'source_post_ids' => '',
        ]));
        $summaryId = (int) $this->db->fetchValue('SELECT id FROM thread_summaries WHERE thread_id = ? ORDER BY id DESC LIMIT 1', [$source['thread_id']]);

        self::assertSame(2, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM content_references WHERE source_type = 'summary' AND source_id = ?",
            [$summaryId],
        ));

        $this->logoutClient();
        $guestPage = $this->get('/t/' . $source['thread_id'] . '-' . $source['slug']);
        $this->assertStatus(200, $guestPage);
        self::assertStringContainsString('Summary Public Target', $guestPage->body());
        self::assertStringNotContainsString('Summary Private Target', $guestPage->body());

        $this->actingAs($member);
        $memberPage = $this->get('/t/' . $source['thread_id'] . '-' . $source['slug']);
        $this->assertStatus(200, $memberPage);
        self::assertStringContainsString('Summary Public Target', $memberPage->body());
        self::assertStringContainsString('Summary Private Target', $memberPage->body());
    }
}
