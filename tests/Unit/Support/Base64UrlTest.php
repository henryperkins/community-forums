<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Base64Url;
use PHPUnit\Framework\TestCase;

final class Base64UrlTest extends TestCase
{
    public function test_round_trips_binary_including_url_unsafe_bytes(): void
    {
        $raw = "\xfb\xff\xfe" . random_bytes(61);
        $encoded = Base64Url::encode($raw);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $encoded);
        self::assertStringNotContainsString('=', $encoded);
        self::assertSame($raw, Base64Url::decode($encoded));
    }

    public function test_decodes_known_vector(): void
    {
        self::assertSame('f', Base64Url::decode('Zg'));
        self::assertSame('Zg', Base64Url::encode('f'));
        self::assertSame('', Base64Url::decode(''));
    }

    public function test_rejects_invalid_input(): void
    {
        self::assertNull(Base64Url::decode('a'));
        self::assertNull(Base64Url::decode('Zg=='));
        self::assertNull(Base64Url::decode('Zg+/'));
        self::assertNull(Base64Url::decode("Zg\n"));
        self::assertNull(Base64Url::decode('AB'));
        self::assertNull(Base64Url::decode('Zh'));
    }
}
