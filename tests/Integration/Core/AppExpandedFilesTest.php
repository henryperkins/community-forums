<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\AttachmentRepository;
use Tests\Support\TestCase;

final class AppExpandedFilesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_expanded_file_upload_is_dark_by_default(): void
    {
        $user = $this->makeUser(['username' => 'filedark']);
        $this->actingAs($user);

        $res = $this->postFile('/upload/file', 'file', $this->fakeUpload('hello', 'note.txt', 'text/plain'));

        $this->assertStatus(404, $res);
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM attachments WHERE kind = 'file'"));
    }

    public function test_text_family_file_download_waits_for_clean_scan_and_is_attachment_only(): void
    {
        $this->setFlags(['expanded_files' => true]);
        $user = $this->makeUser(['username' => 'fileauthor']);
        $this->actingAs($user);

        $upload = $this->postFile('/upload/file', 'file', $this->fakeUpload("# Notes\n", 'notes.md', 'text/markdown'));
        $this->assertStatus(200, $upload);
        $json = json_decode($upload->body(), true);
        $id = (int) $json['id'];
        self::assertSame('pending', $json['scan_status']);

        $pending = $this->get('/media/' . $id . '/download');
        $this->assertStatus(404, $pending);

        (new AttachmentRepository($this->db))->markScanClean($id);
        $download = $this->get('/media/' . $id . '/download');

        $this->assertStatus(200, $download);
        self::assertSame('application/octet-stream', $download->getHeader('content-type'));
        self::assertStringContainsString('attachment; filename="notes.md"', (string) $download->getHeader('content-disposition'));
        self::assertSame('nosniff', $download->getHeader('x-content-type-options'));
        self::assertSame("# Notes\n", $download->body());
    }

    public function test_binary_spoofed_text_file_is_rejected(): void
    {
        $this->setFlags(['expanded_files' => true]);
        $user = $this->makeUser(['username' => 'filespoof']);
        $this->actingAs($user);

        $res = $this->postFile('/upload/file', 'file', $this->fakeUpload("abc\0def", 'payload.txt', 'text/plain'));

        $this->assertStatus(422, $res);
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM attachments WHERE kind = 'file'"));
    }

    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new \App\Repository\SettingRepository($this->db))->set('features', $flags);
    }
}
