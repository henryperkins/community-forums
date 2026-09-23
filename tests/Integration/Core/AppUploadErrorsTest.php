<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppUploadErrorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->makeAdmin());
        (new SettingRepository($this->db))->set('features', ['uploads' => true, 'expanded_files' => true]);
    }

    public function test_intake_errors_have_specific_responses_for_both_upload_routes(): void
    {
        foreach (['/upload' => 'image', '/upload/file' => 'file'] as $path => $field) {
            foreach ([[UPLOAD_ERR_INI_SIZE, 413, 'upload_too_large'], [UPLOAD_ERR_FORM_SIZE, 413, 'upload_too_large'],
                [UPLOAD_ERR_PARTIAL, 422, 'upload_incomplete'], [UPLOAD_ERR_NO_FILE, 422, 'upload_missing'],
                [UPLOAD_ERR_NO_TMP_DIR, 503, 'upload_unavailable'], [UPLOAD_ERR_CANT_WRITE, 503, 'upload_unavailable'],
                [UPLOAD_ERR_EXTENSION, 503, 'upload_unavailable'], [99, 422, 'upload_invalid']] as [$error, $status, $code]) {
                $response = $this->postFile($path, $field, [
                    'name' => 'photo.png', 'type' => 'image/png', 'tmp_name' => '', 'error' => $error, 'size' => 0,
                ]);
                $this->assertStatus($status, $response);
                $json = json_decode($response->body(), true);
                self::assertFalse($json['ok']);
                self::assertSame($code, $json['code']);
                self::assertSame(5242880, $json['max_bytes']);
                self::assertStringNotContainsString('/tmp', $json['error']);
            }
        }
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM attachments'));
    }

    public function test_discarded_oversize_body_reports_size_without_bypassing_csrf(): void
    {
        $cap = ini_parse_quantity(ini_get('post_max_size'));
        self::assertGreaterThan(0, $cap);
        foreach (['/upload', '/upload/file'] as $path) {
            $response = $this->postWithServer($path, [], ['CONTENT_LENGTH' => (string) ($cap + 1)], false);
            $this->assertStatus(413, $response);
            self::assertSame('upload_request_too_large', json_decode($response->body(), true)['code']);
            $this->assertStatus(403, $this->postWithServer($path, [], ['CONTENT_LENGTH' => '123'], false));
            $this->assertStatus(403, $this->postWithServer($path, [], ['CONTENT_LENGTH' => 'unknown'], false));
        }
        $this->assertStatus(403, $this->postWithServer('/threads', [], ['CONTENT_LENGTH' => (string) ($cap + 1)], false));
    }

    public function test_storage_failure_has_retryable_diagnostics_without_exposing_paths(): void
    {
        $blockedRoot = tempnam(sys_get_temp_dir(), 'rb-upload-blocked');
        self::assertIsString($blockedRoot);
        $config = $this->config->all();
        $config['uploads']['storage_path'] = $blockedRoot;
        $this->app = new \App\Core\App(new \App\Core\Config($config), $this->db, $this->rateLimiter);
        try {
            $response = $this->postFile('/upload', 'image', $this->fakeUpload($this->pngBytes()));
            $this->assertStatus(503, $response);
            $json = json_decode($response->body(), true);
            self::assertSame('upload_unavailable', $json['code']);
            self::assertStringNotContainsString($blockedRoot, $response->body());
            self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM attachments'));
        } finally {
            unlink($blockedRoot);
        }
    }
}
