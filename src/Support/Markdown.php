<?php

declare(strict_types=1);

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Renders canonical Markdown (posts.body, bios) to the cached sanitised HTML
 * stored in posts.body_html. CommonMark is configured to ESCAPE any raw HTML
 * and to drop unsafe link schemes; the output then passes through the allowlist
 * HtmlSanitizer for defense in depth and to enforce the Phase 1 tag subset
 * (no images/tables; headings clamped to ##/###).
 */
final class Markdown
{
    private MarkdownConverter $converter;

    public function __construct(private HtmlSanitizer $sanitizer)
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 25,
            'renderer' => [
                'soft_break' => "\n",
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new AutolinkExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    /** Render to sanitised HTML suitable for direct, unescaped output. */
    public function render(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }
        $html = $this->converter->convert($markdown)->getContent();
        return $this->sanitizer->sanitize($html);
    }
}
