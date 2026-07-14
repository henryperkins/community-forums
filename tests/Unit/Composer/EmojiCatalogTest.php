<?php

declare(strict_types=1);

namespace Tests\Unit\Composer;

use App\Support\EmojiCatalog;
use PHPUnit\Framework\TestCase;

final class EmojiCatalogTest extends TestCase
{
    private const CATEGORIES = [
        'Smileys & emotion',
        'People & body',
        'Animals & nature',
        'Food & drink',
        'Activities',
        'Travel & places',
        'Objects',
        'Symbols',
        'Flags',
    ];

    public function test_catalog_is_bounded_unique_and_well_formed(): void
    {
        $rows = EmojiCatalog::all();
        self::assertGreaterThanOrEqual(280, count($rows));
        self::assertLessThanOrEqual(320, count($rows));

        $primary = [];
        $seenCategories = [];
        foreach ($rows as $row) {
            self::assertNotSame('', trim($row['emoji']));
            self::assertNotSame('', trim($row['name']));
            self::assertNotEmpty($row['shortcodes']);
            self::assertNotEmpty($row['keywords']);
            self::assertContains($row['category'], self::CATEGORIES);
            $seenCategories[$row['category']] = true;

            foreach ($row['shortcodes'] as $shortcode) {
                self::assertMatchesRegularExpression('/^[a-z0-9_+-]{2,40}$/', $shortcode);
            }
            foreach ($row['keywords'] as $keyword) {
                self::assertNotSame('', trim($keyword));
            }

            $first = $row['shortcodes'][0];
            self::assertArrayNotHasKey($first, $primary, 'primary shortcode must be unique: ' . $first);
            $primary[$first] = true;
        }

        self::assertSame(self::CATEGORIES, array_values(array_intersect(self::CATEGORIES, array_keys($seenCategories))));
    }

    public function test_search_preserves_plus_and_ranks_deterministically(): void
    {
        $plus = EmojiCatalog::search('+1');
        self::assertNotEmpty($plus);
        self::assertSame('👍', $plus[0]['emoji']);
        self::assertContains('+1', $plus[0]['shortcodes']);

        $thumb = EmojiCatalog::search('thumb');
        self::assertNotEmpty($thumb);
        self::assertSame($thumb, EmojiCatalog::search('THUMB'));

        $party = EmojiCatalog::search('party');
        self::assertNotEmpty($party);
        self::assertSame('🎉', $party[0]['emoji']);
        self::assertSame($party, EmojiCatalog::search('PARTY'));
    }
}
