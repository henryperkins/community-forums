<?php

declare(strict_types=1);

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use App\Support\Markdown\SpoilerExtension;
use App\Service\CustomEmojiService;

/**
 * Renders canonical Markdown (posts.body, bios) to the cached sanitised HTML
 * stored in posts.body_html. CommonMark is configured to ESCAPE any raw HTML
 * and to drop unsafe link schemes; the output then passes through the allowlist
 * HtmlSanitizer for defense in depth.
 */
final class Markdown
{
    private MarkdownConverter $converter;

    public function __construct(
        private HtmlSanitizer $sanitizer,
        private ?CustomEmojiService $customEmoji = null,
        private ?MentionLinker $mentionLinker = null,
    ) {
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
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new SpoilerExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Render to sanitised HTML suitable for direct, unescaped output.
     *
     * @param array{link_mentions?:bool} $options
     */
    public function render(string $markdown, array $options = []): string
    {
        if (trim($markdown) === '') {
            return '';
        }
        $html = $this->converter->convert($markdown)->getContent();
        $html = $this->renderEmojiShortcodes($html);
        $html = $this->sanitizer->sanitize($html);
        // Post-sanitizer pass: preserves class="mention" (which the sanitizer
        // would otherwise strip) and only adds same-origin /u/{username} anchors.
        if (!empty($options['link_mentions'])) {
            $html = $this->mentionLinker?->link($html) ?? $html;
        }
        return $html;
    }

    private function renderEmojiShortcodes(string $html): string
    {
        if (!str_contains($html, ':')) {
            return $html;
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $ok = $doc->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        if (!$ok) {
            return $html;
        }

        $map = [
            ':smile:' => '😄',
            ':heart:' => '❤️',
            ':thumbsup:' => '👍',
            ':thumbs_up:' => '👍',
            ':tada:' => '🎉',
        ];
        $walker = function (\DOMNode $node) use (&$walker, $map): void {
            if ($node instanceof \DOMText) {
                $parent = $node->parentNode;
                while ($parent !== null) {
                    $name = strtolower($parent->nodeName);
                    if ($name === 'code' || $name === 'pre') {
                        return;
                    }
                    $parent = $parent->parentNode;
                }
                $node->nodeValue = strtr($node->nodeValue, $map);
                return;
            }
            foreach (iterator_to_array($node->childNodes) as $child) {
                $walker($child);
            }
        };
        $walker($doc);
        $this->customEmoji?->renderInto($doc);

        $out = $doc->saveHTML();
        if (!is_string($out)) {
            return $html;
        }
        return preg_replace('/^<\?xml encoding="UTF-8"\?>\s*/', '', $out) ?? $out;
    }
}
