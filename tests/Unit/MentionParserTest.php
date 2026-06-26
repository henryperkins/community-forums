<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MentionParser;
use PHPUnit\Framework\TestCase;

final class MentionParserTest extends TestCase
{
    public function testExtractsAndDeduplicatesCaseInsensitively(): void
    {
        self::assertSame(['bob', 'carol'], MentionParser::parse('Hey @bob and @carol and @Bob again'));
    }

    public function testIgnoresEmailsAndDoubleAt(): void
    {
        self::assertSame([], MentionParser::parse('mail me at name@example.com or @@notahandle'));
    }

    public function testIgnoresMentionsInCode(): void
    {
        self::assertSame(['real'], MentionParser::parse("`@incode` and ```\n@fenced\n``` but @real counts"));
    }

    public function testCapsAtTen(): void
    {
        $handles = [];
        for ($i = 1; $i <= 15; $i++) {
            $handles[] = '@user' . $i;
        }
        $parsed = MentionParser::parse(implode(' ', $handles));
        self::assertCount(MentionParser::MAX, $parsed);
        self::assertSame('user1', $parsed[0]);
        self::assertSame('user10', $parsed[9]);
    }

    public function testRejectsTooShortHandles(): void
    {
        self::assertSame([], MentionParser::parse('@ab is too short'));
    }
}
