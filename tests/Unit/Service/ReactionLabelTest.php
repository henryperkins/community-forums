<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\ReactionService;
use PHPUnit\Framework\TestCase;

final class ReactionLabelTest extends TestCase
{
    public function test_shipped_glyphs_use_council_words_or_a_plain_name(): void
    {
        self::assertSame('Commend', ReactionService::label('👍'));
        self::assertSame('Kindled', ReactionService::label('🔥'));
        self::assertSame('Seconded', ReactionService::label('💯'));
        self::assertSame('Illuminating', ReactionService::label('👀'));
        self::assertSame('Laugh', ReactionService::label('😂'));
    }

    public function test_custom_shortcodes_become_words_and_unknown_glyphs_stay(): void
    {
        self::assertSame('Mallorn leaf', ReactionService::label(':mallorn_leaf:'));
        self::assertSame('✦', ReactionService::label('✦'));
    }
}
