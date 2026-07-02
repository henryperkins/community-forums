<?php

declare(strict_types=1);

namespace Tests\Unit\Composer;

use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * P3-02 round-trip corpus: canonical Markdown is the source of truth. Rendering
 * is deterministic and the supported constructs survive the render+sanitize
 * pipeline; dangerous input is neutralised. (The raw canonical text itself is
 * stored verbatim — see AppComposerTest for the storage round-trip.)
 */
final class MarkdownRoundTripTest extends TestCase
{
    private function md(): Markdown
    {
        return new Markdown(new HtmlSanitizer());
    }

    /** @return array<string,array{0:string,1:string}> [markdown, expected-substring-in-html] */
    public static function corpus(): array
    {
        return [
            'bold' => ['**bold**', '<strong>bold</strong>'],
            'italic' => ['*it*', '<em>it</em>'],
            'strike' => ['~~no~~', '<del>no</del>'],
            'inline code' => ['`x=1`', '<code>x=1</code>'],
            'blockquote' => ['> quoted', '<blockquote>'],
            'bullet list' => ["- a\n- b", '<ul>'],
            'ordered list' => ["1. a\n2. b", '<ol>'],
            'fenced code' => ["```\ncode\n```", '<pre><code>'],
            'link nofollow' => ['[t](https://example.com)', 'rel="nofollow ugc noopener noreferrer"'],
            'h2 kept' => ['## Heading', '<h2>Heading</h2>'],
            'h1 clamped to h2' => ['# Top', '<h2>Top</h2>'],
            'h4 clamped to h3' => ['#### Deep', '<h3>Deep</h3>'],
            'spoiler' => ['||hidden||', '<span class="spoiler" tabindex="0">hidden</span>'],
            'emoji shortcode' => ['Hello :smile:', 'Hello 😄'],
            'media image' => ['![cat](/media/3)', '<img src="/media/3" alt="cat" loading="lazy">'],
        ];
    }

    #[DataProvider('corpus')]
    public function test_construct_renders_as_expected(string $markdown, string $expected): void
    {
        self::assertStringContainsString($expected, $this->md()->render($markdown));
    }

    #[DataProvider('corpus')]
    public function test_render_is_deterministic(string $markdown): void
    {
        $md = $this->md();
        self::assertSame($md->render($markdown), $md->render($markdown));
    }

    public function test_supported_markdown_fixtures_render_semantically_after_editor_round_trip(): void
    {
        $markdown = implode("\n\n", [
            '## Heading',
            '**bold** *italic* ~~strike~~ `code`',
            '> quote',
            "- [x] task\n- item",
            "| A | B |\n| - | - |\n| 1 | 2 |",
            '||spoiler||',
            '@alice',
            '[#general](/c/general)',
        ]);

        $html = $this->md()->render($markdown);
        self::assertStringContainsString('<table>', $html);
        self::assertStringContainsString('class="spoiler"', $html);
    }

    /** @return array<string,array{0:string}> */
    public static function xssCorpus(): array
    {
        return [
            'raw script' => ['<script>alert(1)</script>'],
            'js link' => ['[x](javascript:alert(1))'],
            'js image' => ['![x](javascript:alert(1))'],
            'onerror img' => ['<img src=x onerror=alert(1)>'],
            'offsite img' => ['![x](https://evil.example/a.png)'],
            'vbscript link' => ['<a href="vbscript:msgbox(1)">x</a>'],
            'event handler div' => ['<div onclick="alert(1)">hi</div>'],
            'data uri img' => ['![x](data:text/html,<script>alert(1)</script>)'],
        ];
    }

    #[DataProvider('xssCorpus')]
    public function test_dangerous_input_is_neutralised(string $markdown): void
    {
        $html = $this->md()->render($markdown);
        // The only permitted image srcs are same-origin media/emoji assets and
        // the explicit GIPHY media host carve-out used by the slash picker.
        self::assertStringNotContainsString('evil.example', $html);

        // Parse the RENDERED output as a browser would: escaped raw HTML becomes
        // inert text, so there must be no live script element, no on* handler, and
        // no dangerous-scheme href/src on any real element.
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        foreach ($doc->getElementsByTagName('*') as $el) {
            self::assertNotSame('script', strtolower($el->nodeName));
            foreach (iterator_to_array($el->attributes ?? []) as $attr) {
                $name = strtolower($attr->nodeName);
                self::assertStringStartsNotWith('on', $name, "live event handler: $name");
                if (in_array($name, ['href', 'src'], true)) {
                    $value = strtolower(trim((string) $attr->nodeValue));
                    foreach (['javascript:', 'vbscript:', 'data:'] as $bad) {
                        self::assertStringStartsNotWith($bad, $value, "dangerous scheme in $name");
                    }
                }
            }
        }
    }

    public function test_empty_renders_empty(): void
    {
        self::assertSame('', $this->md()->render(''));
        self::assertSame('', $this->md()->render("   \n  "));
    }

    public function test_giphy_images_are_only_allowed_when_picker_media_is_enabled(): void
    {
        $markdown = '![cat](https://media4.giphy.com/media/cat/giphy.gif)';
        self::assertStringNotContainsString('<img', $this->md()->render($markdown));

        $html = (new Markdown(new HtmlSanitizer(allowGiphyImages: true)))->render($markdown);
        self::assertStringContainsString(
            '<img src="https://media4.giphy.com/media/cat/giphy.gif" alt="cat" loading="lazy">',
            $html,
        );
    }

    public function test_emoji_shortcodes_do_not_change_code(): void
    {
        $html = $this->md()->render('`:smile:`');
        self::assertStringContainsString('<code>:smile:</code>', $html);
        self::assertStringNotContainsString('😄', $html);
    }
}
