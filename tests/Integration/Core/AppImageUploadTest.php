<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

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
}
