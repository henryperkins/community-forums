<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardMemberRepository;
use Tests\Support\TestCase;

/**
 * P3-04: a media URL from a private board is authorization-gated on every
 * request. A guest, non-member, or removed member gets nothing; the owner and
 * current members get the bytes.
 */
final class AppPrivateMediaAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_private_board_media_is_access_gated(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'secret', 'visibility' => 'private']);
        $members = new BoardMemberRepository($this->db);

        $author = $this->makeUser(['username' => 'insider']);
        $members->add((int) $board['id'], (int) $author['id'], null);
        $this->actingAs($author);

        // Upload, then post a thread that references the image in the private board.
        $up = json_decode($this->postFile('/upload', 'image', $this->fakeUpload($this->pngBytes()))->body(), true);
        $mediaId = (int) $up['id'];
        $this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Secret pic',
            'body' => "Here it is ![pic](/media/{$mediaId})",
        ]);

        // Finalized: bound to the post + private.
        $att = $this->db->fetch('SELECT * FROM attachments WHERE id = ?', [$mediaId]);
        self::assertSame('finalized', $att['status']);
        self::assertSame('private', $att['visibility']);
        self::assertNotNull($att['post_id']);

        // Owner (a member) can view — and private media is never shared-cacheable.
        $ownerView = $this->get('/media/' . $mediaId);
        $this->assertStatus(200, $ownerView);
        self::assertStringContainsString('private', (string) $ownerView->getHeader('cache-control'));
        self::assertStringContainsString('no-store', (string) $ownerView->getHeader('cache-control'));

        // A non-member is denied.
        $outsider = $this->makeUser(['username' => 'outsider']);
        $this->actingAs($outsider);
        self::assertContains($this->get('/media/' . $mediaId)->status(), [403, 404]);

        // A guest is denied.
        $this->logoutClient();
        self::assertContains($this->get('/media/' . $mediaId)->status(), [403, 404]);

        // Access revoked after upload → denied even though they once were a member.
        $members->add((int) $board['id'], (int) $outsider['id'], null);
        $this->actingAs($outsider);
        $this->assertStatus(200, $this->get('/media/' . $mediaId)); // now a member
        $members->remove((int) $board['id'], (int) $outsider['id']);
        self::assertContains($this->get('/media/' . $mediaId)->status(), [403, 404]); // revoked
    }

    public function test_dm_media_is_restricted_to_participants(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'dmboard']);

        $alice = $this->makeUser(['username' => 'alice']);
        $bob = $this->makeUser(['username' => 'bob', 'email' => 'bob@x.test']);
        $carol = $this->makeUser(['username' => 'carol']);
        // Alice posts once so she's past the new-account DM throttle.
        $this->makeThread($board, $alice, 'warmup', 'hi');

        $this->actingAs($alice);
        $up = json_decode($this->postFile('/upload', 'image', $this->fakeUpload($this->pngBytes()), ['purpose' => 'dm'])->body(), true);
        $mediaId = (int) $up['id'];
        $this->post('/messages', ['to' => 'bob', 'body' => "look ![](/media/{$mediaId})"]);

        $att = $this->db->fetch('SELECT * FROM attachments WHERE id = ?', [$mediaId]);
        self::assertSame('finalized', $att['status']);
        self::assertSame('private', $att['visibility']);
        self::assertNotNull($att['dm_message_id']);

        // Sender (participant) sees it.
        $this->assertStatus(200, $this->get('/media/' . $mediaId));
        // Recipient (participant) sees it.
        $this->actingAs($bob);
        $this->assertStatus(200, $this->get('/media/' . $mediaId));
        // An unrelated user with a copied URL gets nothing.
        $this->actingAs($carol);
        self::assertContains($this->get('/media/' . $mediaId)->status(), [403, 404]);
        // A guest gets nothing.
        $this->logoutClient();
        self::assertContains($this->get('/media/' . $mediaId)->status(), [403, 404]);
    }
}
