<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use App\Search\SearchQuery;
use PHPUnit\Framework\TestCase;

final class SearchQueryTest extends TestCase
{
    public function testItNormalizesAndValidatesImmutableSearchOptions(): void
    {
        $query = new SearchQuery('  council archive  ', 'not-a-scope', 'not-an-order', 500);

        self::assertSame('council archive', $query->query);
        self::assertSame('everything', $query->scope);
        self::assertSame('relevance', $query->order);
        self::assertSame(50, $query->limit);
        self::assertNull($query->validationError());
        self::assertSame(
            '/search?q=council%20archive&scope=topics&order=newest',
            $query->url('topics', 'newest'),
        );

        self::assertSame('Enter a search phrase.', (new SearchQuery(''))->validationError());
        self::assertSame('Search phrases must be at least 3 characters.', (new SearchQuery('ab'))->validationError());
        self::assertSame(1, (new SearchQuery('valid', limit: -10))->limit);
    }
}
