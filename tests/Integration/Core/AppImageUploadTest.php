<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * P3-04: image upload intake. Valid images are accepted, re-encoded, and served;
 * content-spoofed and oversized uploads are rejected by sniffing/dimension
 * checks (not by the client filename), and guests cannot upload.
 */
final class AppImageUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(); // satisfy the first-run setup gate
    }

    public function test_valid_png_upload_is_stored_and_served(): void
    {
        $user = $this->makeUser(['username' => 'uploader']);
        $this->actingAs($user);

        $res = $this->postFile('/upload', 'image', $this->fakeUpload($this->pngBytes(16, 16)));
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        self::assertTrue($json['ok']);
        self::assertSame('/media/' . $json['id'], $json['url']);

        // Stored as a temp attachment owned by the uploader.
        $att = $this->db->fetch('SELECT * FROM attachments WHERE id = ?', [(int) $json['id']]);
        self::assertSame('temp', $att['status']);
        self::assertSame((int) $user['id'], (int) $att['user_id']);
        self::assertSame('image/png', $att['mime']);

        // The owner can fetch the bytes; an unbound temp upload is private/no-store.
        $img = $this->get('/media/' . (int) $json['id']);
        $this->assertStatus(200, $img);
        self::assertSame('image/png', $img->getHeader('content-type'));
        self::assertNotSame('', $img->body());
        self::assertStringContainsString('no-store', (string) $img->getHeader('cache-control'));
    }

    public function test_public_board_media_is_publicly_cacheable(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'pubmedia']);
        $user = $this->makeUser(['username' => 'pubuploader']);
        $this->actingAs($user);

        $json = json_decode($this->postFile('/upload', 'image', $this->fakeUpload($this->pngBytes()))->body(), true);
        $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'pic', 'body' => "![](/media/{$json['id']})"]);

        // A guest can fetch public post media, served with a long public cache.
        $this->logoutClient();
        $img = $this->get('/media/' . (int) $json['id']);
        $this->assertStatus(200, $img);
        self::assertStringContainsString('public', (string) $img->getHeader('cache-control'));
        self::assertStringContainsString('immutable', (string) $img->getHeader('cache-control'));
    }

    public function test_content_spoofed_upload_is_rejected(): void
    {
        $user = $this->makeUser(['username' => 'spoofer']);
        $this->actingAs($user);

        // Not actually an image, despite the .png name + image/png type.
        $res = $this->postFile('/upload', 'image', $this->fakeUpload('definitely not an image', 'evil.png', 'image/png'));
        $this->assertStatus(422, $res);
        $json = json_decode($res->body(), true);
        self::assertFalse($json['ok']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM attachments'));
    }

    public function test_polyglot_image_is_reencoded_before_serving(): void
    {
        $user = $this->makeUser(['username' => 'polyglot']);
        $this->actingAs($user);

        $payload = $this->pngBytes(12, 12) . "<?php echo 'owned'; ?><script>alert(1)</script>";
        $res = $this->postFile('/upload', 'image', $this->fakeUpload($payload, 'polyglot.png', 'image/png'));
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        self::assertTrue($json['ok']);

        $img = $this->get('/media/' . (int) $json['id']);
        $this->assertStatus(200, $img);
        self::assertStringNotContainsString('<?php', $img->body());
        self::assertStringNotContainsString('<script', $img->body());
    }

    public function test_oversized_dimensions_rejected(): void
    {
        $user = $this->makeUser(['username' => 'bigpic']);
        $this->actingAs($user);

        // 5000px wide exceeds the 4096 dimension cap.
        $res = $this->postFile('/upload', 'image', $this->fakeUpload($this->pngBytes(5000, 2)));
        $this->assertStatus(422, $res);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM attachments'));
    }

    public function test_guest_cannot_upload(): void
    {
        // A guest has no CSRF secret, so the request is rejected before the
        // controller (403); either way nothing is stored.
        $res = $this->postFile('/upload', 'image', $this->fakeUpload($this->pngBytes()));
        self::assertNotSame(200, $res->status());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM attachments'));
    }

    public function test_upload_flag_pauses_new_uploads_but_existing_media_still_reads(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'pausedmedia']);
        $user = $this->makeUser(['username' => 'pauseduploader']);
        $this->actingAs($user);

        $json = json_decode($this->postFile('/upload', 'image', $this->fakeUpload($this->pngBytes()))->body(), true);
        $mediaId = (int) $json['id'];
        $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'pic', 'body' => "![](/media/{$mediaId})"]);

        (new SettingRepository($this->db))->set('features', ['uploads' => false]);
        $blocked = $this->postFile('/upload', 'image', $this->fakeUpload($this->pngBytes()));
        $this->assertStatus(403, $blocked);

        $this->logoutClient();
        $this->assertStatus(200, $this->get('/media/' . $mediaId));
    }

    public function test_held_post_media_is_never_publicly_cacheable(): void
    {
        // A public board that holds new threads for approval.
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'heldmedia']);
        $this->db->run('UPDATE boards SET require_approval = 1 WHERE id = ?', [(int) $board['id']]);

        $author = $this->makeUser(['username' => 'helduploader']);
        $this->actingAs($author);
        $json = json_decode($this->postFile('/upload', 'image', $this->fakeUpload($this->pngBytes()))->body(), true);
        $mediaId = (int) $json['id'];
        $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'held pic', 'body' => "![](/media/{$mediaId})"]);

        // The image is bound to a held (pending) post, even though the board is public.
        $att = $this->db->fetch('SELECT * FROM attachments WHERE id = ?', [$mediaId]);
        self::assertSame('finalized', $att['status']);

        // The owner can still view it, but cacheability is decided by the LIVE
        // authorization: held media must be private/no-store (never the public,
        // immutable, 1-year header) so a cache can't outlive a later rejection.
        $img = $this->get('/media/' . $mediaId);
        $this->assertStatus(200, $img);
        self::assertStringContainsString('no-store', (string) $img->getHeader('cache-control'));
        self::assertStringNotContainsString('immutable', (string) $img->getHeader('cache-control'));

        // A stranger cannot see held media at all (404, not a tell-tale 403).
        $stranger = $this->makeUser(['username' => 'heldstranger']);
        $this->actingAs($stranger);
        self::assertSame(404, $this->get('/media/' . $mediaId)->status());
    }

    public function test_editing_a_post_to_add_an_image_finalizes_and_serves_it(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'editmedia']);
        $author = $this->makeUser(['username' => 'editoruser']);
        $this->actingAs($author);

        // A live thread with no image yet.
        $thread = $this->makeThread($board, $author, 'Edit me', 'Original body.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        // Upload an image, then EDIT the post to reference it. The regression: edit
        // skipped finalize, so the image stayed 'temp' — invisible to other readers
        // and reclaimed by the orphan sweep while the live post still linked it.
        $json = json_decode($this->postFile('/upload', 'image', $this->fakeUpload($this->pngBytes()))->body(), true);
        $mediaId = (int) $json['id'];
        $res = $this->post('/posts/' . $opId . '/edit', ['body' => "Now with a picture ![](/media/{$mediaId})"]);
        $this->assertRedirectContains($res, '/t/' . $thread['thread_id']);

        // The attachment is now finalized and bound to the edited post.
        $att = $this->db->fetch('SELECT * FROM attachments WHERE id = ?', [$mediaId]);
        self::assertSame('finalized', $att['status']);
        self::assertSame($opId, (int) $att['post_id']);

        // A guest viewing the public post can fetch the image (no longer temp).
        $this->logoutClient();
        $this->assertStatus(200, $this->get('/media/' . $mediaId));
    }

    public function test_per_post_image_cap_is_enforced(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'manyimg']);
        $author = $this->makeUser(['username' => 'imagehoarder']);
        $this->actingAs($author);

        // Referencing more than uploads.per_post_max (default 10) distinct images is
        // rejected wholesale — the over-cap thread is never created.
        $refs = '';
        for ($i = 1; $i <= 11; $i++) {
            $refs .= " ![](/media/{$i})";
        }
        $res = $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'Too many', 'body' => 'pics' . $refs]);
        $this->assertStatus(422, $res);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM threads WHERE user_id = ?', [(int) $author['id']]));
    }
}
