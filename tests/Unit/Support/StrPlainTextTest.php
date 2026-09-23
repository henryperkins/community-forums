<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use App\Support\Str;
use PHPUnit\Framework\TestCase;

final class StrPlainTextTest extends TestCase
{
    private function words(string $markdown): string
    {
        return Str::plainText((new Markdown(new HtmlSanitizer()))->render($markdown));
    }

    public function testMarkdownPunctuationNeverReachesThePlainText(): void
    {
        self::assertSame("echo 'hi'; done", $this->words("```php\necho 'hi';\n```\n\ndone"));
        self::assertSame('Run worker:packages now', $this->words('Run `worker:packages` now'));
        self::assertSame('first second', $this->words("- first\n- second"));
        self::assertSame('one two', $this->words("1. one\n2. two"));
        self::assertSame('Read the runbook today', $this->words('Read [the runbook](https://example.com/runbook) today'));
        self::assertSame('bold and em and gone', $this->words('**bold** and _em_ and ~~gone~~'));
        self::assertSame('Heading Quoted', $this->words("## Heading\n\n> Quoted"));
    }

    public function testBlocksAreSeparatedButInlineMarkupNeverSplitsAWord(): void
    {
        self::assertSame('para one para two', Str::plainText('<p>para one</p><p>para two</p>'));
        self::assertSame('a b', Str::plainText('a<br>b'));
        self::assertSame('unbelievable', Str::plainText('un<em>believ</em>able'));
        self::assertSame('a b', Str::plainText("<p>a</p>\n\n  <p>b</p>"));
    }

    public function testImagesKeepTheirAltTextAndEntitiesDecode(): void
    {
        self::assertSame('Look: a <map> here', Str::plainText('<p>Look: <img src="/media/1" alt="a &lt;map&gt;" loading="lazy"> here</p>'));
        self::assertSame('x y', Str::plainText('<p>x <img src="/media/2"> y</p>'));
        self::assertSame('Tom & Jerry "quoted" it\'s', Str::plainText('<p>Tom &amp; Jerry &quot;quoted&quot; it&#039;s</p>'));
        self::assertSame('<b>raw</b>', Str::plainText('<p>&lt;b&gt;raw&lt;/b&gt;</p>'));
        self::assertSame('', Str::plainText(''));
    }
}
