<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class RequestUploadTest extends TestCase
{
    public function test_upload_errors_survive_without_becoming_successful_files(): void
    {
        foreach ([UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_FILE,
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION, 99] as $error) {
            $request = new Request('POST', '/upload', files: ['image' => [
                'name' => 'photo.png', 'type' => 'image/png', 'tmp_name' => '', 'error' => $error, 'size' => 0,
            ]]);
            self::assertSame($error, $request->fileError('image'));
            self::assertNull($request->file('image'));
        }
    }

    public function test_malformed_fields_are_rejected_without_php_warnings(): void
    {
        $valid = ['name' => 'photo.png', 'type' => '', 'tmp_name' => '/tmp/example', 'error' => 0, 'size' => 1];
        foreach ([[], 'invalid', ['error' => 0], array_replace($valid, ['error' => [0]]),
            array_replace($valid, ['name' => ['nested']]), array_replace($valid, ['tmp_name' => '']),
            array_replace($valid, ['size' => -1]), array_replace($valid, ['type' => []])] as $file) {
            $request = new Request('POST', '/upload', files: ['image' => $file]);
            self::assertSame(Request::UPLOAD_ERR_INVALID, $request->fileError('image'));
            self::assertNull($request->file('image'));
        }
        $request = new Request('POST', '/upload', files: ['image' => $valid]);
        self::assertSame(UPLOAD_ERR_OK, $request->fileError('image'));
        self::assertSame($valid, $request->file('image'));
        self::assertNull($request->fileError('missing'));
    }

    public function test_only_reliable_content_lengths_are_used(): void
    {
        foreach ([['0', 0], ['2673378', 2673378], ['0005', 5], ['', null], ['-1', null],
            ['1.5', null], ['1e8', null], [' 5', null], [str_repeat('9', 30), null]] as [$raw, $expected]) {
            self::assertSame($expected, (new Request('POST', '/upload', server: ['CONTENT_LENGTH' => $raw]))->contentLength());
        }
        self::assertNull((new Request('POST', '/upload'))->contentLength());
    }
}
