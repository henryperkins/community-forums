<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Str;
use PHPUnit\Framework\TestCase;

final class StrTest extends TestCase
{
    public function test_slug_is_lowercase_hyphenated_ascii(): void
    {
        self::assertSame('hello-world', Str::slug('Hello, World!'));
        self::assertSame('a-b-c', Str::slug('  a   b   c  '));
    }

    public function test_slug_falls_back_for_empty_result(): void
    {
        self::assertSame('topic', Str::slug('!!!'));
        self::assertSame('topic', Str::slug(''));
    }

    public function test_slug_is_truncated(): void
    {
        $slug = Str::slug(str_repeat('a', 300), 64);
        self::assertLessThanOrEqual(64, strlen($slug));
    }

    public function test_initials_uses_up_to_two_letters(): void
    {
        self::assertSame('JD', Str::initials('John Doe'));
        self::assertSame('AL', Str::initials('alice'));
        self::assertSame('?', Str::initials(''));
    }
}
