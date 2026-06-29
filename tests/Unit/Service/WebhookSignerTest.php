<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\Webhook\WebhookSigner;
use PHPUnit\Framework\TestCase;

final class WebhookSignerTest extends TestCase
{
    public function test_headers_contain_signature_over_timestamp_dot_body(): void
    {
        $h = WebhookSigner::headers('ping', 'evt_1', 1782680000, '{"a":1}', ['secretA']);
        $expected = 'sha256=' . hash_hmac('sha256', '1782680000.{"a":1}', 'secretA');
        self::assertSame($expected, $h['X-RetroBoards-Signature']);
        self::assertSame('ping', $h['X-RetroBoards-Event']);
        self::assertSame('evt_1', $h['X-RetroBoards-Delivery']);
        self::assertSame('1782680000', $h['X-RetroBoards-Timestamp']);
        self::assertSame('application/json', $h['Content-Type']);
    }

    public function test_two_secrets_emit_two_comma_separated_signatures(): void
    {
        $h = WebhookSigner::headers('ping', 'evt_2', 1782680000, 'body', ['newS', 'oldS']);
        $new = 'sha256=' . hash_hmac('sha256', '1782680000.body', 'newS');
        $old = 'sha256=' . hash_hmac('sha256', '1782680000.body', 'oldS');
        self::assertSame($new . ', ' . $old, $h['X-RetroBoards-Signature']);
    }
}
