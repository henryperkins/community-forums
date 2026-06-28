<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use PHPUnit\Framework\TestCase;

/**
 * P0 XSS regression suite — the malicious-payload tests required by the plan.
 * Exercises both the full Markdown pipeline and the raw HTML sanitizer.
 */
final class SanitizationTest extends TestCase
{
    private Markdown $markdown;
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new HtmlSanitizer();
        $this->markdown = new Markdown($this->sanitizer);
    }

    public function test_script_tags_are_neutralised(): void
    {
        $html = $this->markdown->render('<script>alert(1)</script> hello');
        self::assertStringNotContainsString('<script', $html);
        self::assertStringContainsString('hello', $html);
    }

    public function test_event_handler_attributes_never_survive(): void
    {
        // Via Markdown, raw HTML is escaped to inert text (no live element).
        $html = $this->markdown->render('<div onclick="alert(1)">x</div>');
        self::assertStringNotContainsString('<div', $html);

        // The sanitizer strips on* handlers from any real element it processes.
        $clean = $this->sanitizer->sanitize('<p onclick="alert(1)">x</p><a href="#" onmouseover="evil()">y</a>');
        self::assertStringNotContainsString('onclick', $clean);
        self::assertStringNotContainsString('onmouseover', $clean);
        self::assertStringContainsString('<p>x</p>', $clean);
    }

    public function test_javascript_uri_links_are_stripped(): void
    {
        $html = $this->markdown->render('[click](javascript:alert(1))');
        self::assertStringNotContainsString('javascript:', $html);
    }

    public function test_data_uri_links_are_stripped(): void
    {
        $html = $this->markdown->render('[x](data:text/html,<script>alert(1)</script>)');
        self::assertStringNotContainsString('data:text/html', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    public function test_images_are_dropped(): void
    {
        $html = $this->markdown->render('![alt](https://example.com/x.png)');
        self::assertStringNotContainsString('<img', $html);
    }

    public function test_tables_render_without_unsafe_attributes(): void
    {
        $html = $this->markdown->render("| a | b |\n|---|---|\n| 1 | <script>alert(1)</script> |");
        self::assertStringContainsString('<table>', $html);
        self::assertStringContainsString('<td>1</td>', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('style=', $html);
    }

    public function test_headings_are_clamped_to_h2_and_h3(): void
    {
        $html = $this->markdown->render("# One\n## Two\n### Three\n#### Four\n##### Five");
        self::assertStringNotContainsString('<h1', $html);
        self::assertStringNotContainsString('<h4', $html);
        self::assertStringNotContainsString('<h5', $html);
        self::assertStringContainsString('<h2', $html);
        self::assertStringContainsString('<h3', $html);
    }

    public function test_protocol_relative_and_backslash_links_are_stripped(): void
    {
        foreach (['//evil.example.com', '/\\evil.example.com', '\\/evil.example.com'] as $href) {
            $clean = $this->sanitizer->sanitize('<a href="' . $href . '">x</a>');
            self::assertStringNotContainsString('evil.example.com', $clean, "leaked: $href");
        }
        // A genuine same-site path is still allowed.
        $ok = $this->sanitizer->sanitize('<a href="/t/123-hello">x</a>');
        self::assertStringContainsString('href="/t/123-hello"', $ok);
    }

    public function test_safe_links_get_nofollow_noopener(): void
    {
        $html = $this->markdown->render('[ok](https://example.com)');
        self::assertStringContainsString('href="https://example.com"', $html);
        self::assertStringContainsString('rel="nofollow ugc noopener noreferrer"', $html);
    }

    public function test_basic_formatting_is_preserved(): void
    {
        $html = $this->markdown->render('**bold** _italic_ `code` and ~~strike~~');
        self::assertStringContainsString('<strong>bold</strong>', $html);
        self::assertStringContainsString('<em>italic</em>', $html);
        self::assertStringContainsString('<code>code</code>', $html);
        self::assertStringContainsString('<del>strike</del>', $html);
    }

    public function test_code_blocks_escape_their_contents(): void
    {
        $html = $this->markdown->render("```\n<script>alert(1)</script>\n```");
        self::assertStringContainsString('<pre>', $html);
        self::assertStringNotContainsString('<script>alert', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_raw_html_sanitizer_strips_iframes_and_svg(): void
    {
        $dirty = '<p>ok</p><iframe src="evil"></iframe><svg onload="alert(1)"></svg><a href="vbscript:x">v</a>';
        $clean = $this->sanitizer->sanitize($dirty);
        self::assertStringContainsString('<p>ok</p>', $clean);
        self::assertStringNotContainsString('<iframe', $clean);
        self::assertStringNotContainsString('<svg', $clean);
        self::assertStringNotContainsString('vbscript:', $clean);
    }

    public function test_empty_input_renders_empty(): void
    {
        self::assertSame('', $this->markdown->render('   '));
        self::assertSame('', $this->sanitizer->sanitize(''));
    }
}
