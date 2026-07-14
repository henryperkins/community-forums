<?php

declare(strict_types=1);

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Allowlist HTML sanitizer (the P0 XSS defense). It runs over the output of the
 * Markdown renderer — which already escapes raw HTML — and keeps only a fixed,
 * known-safe tag set. Everything else is dropped or unwrapped:
 *
 *  - Allowed: p, br, hr, strong, em, del, s, code, pre, blockquote, ul, ol, li,
 *    a, h2, h3, restricted images, safe table markup and its exact generated
 *    scroll wrapper, and disabled task-list inputs.
 *  - Headings are clamped into [h2, h3] (h1→h2, h4–h6→h3) so a post can't
 *    impersonate page chrome (COMPOSER.md: only ## and ###).
 *  - Dangerous elements (script, style, iframe, form, svg, etc.) are removed
 *    with their subtree.
 *  - Unknown elements are unwrapped (their text is kept, the tag is dropped).
 *  - <a href> is restricted to http/https/mailto + relative/fragment URLs and
 *    gets rel="nofollow ugc noopener noreferrer". A small, tag-specific set of
 *    renderer semantics is retained; all other attributes, including every
 *    on* handler, are stripped.
 */
final class HtmlSanitizer
{
    public function __construct(private bool $allowGiphyImages = false)
    {
    }

    /** @var array<string,true> */
    private const ALLOWED = [
        'p' => true, 'br' => true, 'hr' => true,
        'strong' => true, 'em' => true, 'del' => true, 's' => true,
        'code' => true, 'pre' => true, 'blockquote' => true,
        'ul' => true, 'ol' => true, 'li' => true,
        'a' => true, 'h2' => true, 'h3' => true,
        'table' => true, 'thead' => true, 'tbody' => true, 'tfoot' => true,
        'tr' => true, 'td' => true, 'th' => true,
        'input' => true,
        // P3-04: uploaded images, referenced from Markdown as /media/{id}.
        'img' => true, 'span' => true,
        // Retained only for the exact generated formatted-table scroll wrapper.
        'div' => true,
    ];

    /** @var array<string,true> elements dropped together with their content */
    private const DROP = [
        'script' => true, 'style' => true, 'iframe' => true, 'object' => true,
        'embed' => true, 'svg' => true, 'math' => true,
        'caption' => true, 'colgroup' => true,
        'col' => true, 'form' => true, 'textarea' => true,
        'button' => true, 'select' => true, 'option' => true, 'label' => true,
        'link' => true, 'meta' => true, 'base' => true, 'title' => true,
        'head' => true, 'body' => true, 'html' => true, 'audio' => true,
        'video' => true, 'source' => true, 'track' => true, 'canvas' => true,
        'applet' => true, 'frame' => true, 'frameset' => true,
        'noscript' => true, 'template' => true, 'picture' => true, 'map' => true,
        'area' => true,
    ];

    public function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="rb-sanitize-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = null;
        foreach ($doc->childNodes as $node) {
            if ($node instanceof DOMElement && $node->getAttribute('id') === 'rb-sanitize-root') {
                $root = $node;
                break;
            }
        }
        if ($root === null) {
            return '';
        }

        $this->cleanChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue; // text is inert
            }
            if ($child instanceof DOMElement) {
                $this->cleanElement($child);
                continue;
            }
            // Comments, processing instructions, CDATA: remove.
            if ($child instanceof DOMComment || $child->parentNode !== null) {
                $child->parentNode?->removeChild($child);
            }
        }
    }

    private function cleanElement(DOMElement $el): void
    {
        $tag = strtolower($el->localName ?? $el->nodeName);

        // Clamp headings into the allowed [h2, h3] range.
        if ($tag === 'h1' || $tag === 'h4' || $tag === 'h5' || $tag === 'h6') {
            $el = $this->rename($el, $tag === 'h1' ? 'h2' : 'h3');
            $tag = strtolower($el->localName ?? $el->nodeName);
        }

        if (isset(self::DROP[$tag])) {
            $el->parentNode?->removeChild($el);
            return;
        }

        if (!isset(self::ALLOWED[$tag])) {
            // Unknown but not dangerous: clean its children, then unwrap.
            $this->cleanChildren($el);
            $this->unwrap($el);
            return;
        }

        if ($tag === 'div') {
            if (!$this->isFormattedTableWrapper($el)) {
                $this->cleanChildren($el);
                $this->unwrap($el);
                return;
            }
            foreach (iterator_to_array($el->attributes ?? []) as $attr) {
                $el->removeAttribute($attr->nodeName);
            }
            $el->setAttribute('class', 'formatted-table');
            $el->setAttribute('tabindex', '0');
            $el->setAttribute('role', 'region');
            $el->setAttribute('aria-label', 'Scrollable table');
            $this->cleanChildren($el);
            return;
        }

        // Only approved media, operator emoji, or configured GIPHY sources
        // survive. An <img> without a safe src is dropped entirely so it can't
        // carry an onerror payload or hotlink an arbitrary off-site tracker.
        if ($tag === 'img') {
            $src = $this->safeImageSrc((string) $el->getAttribute('src'));
            $alt = (string) $el->getAttribute('alt');
            $customEmoji = preg_match('~^/emoji/[A-Za-z0-9_.-]+\.(?:png|webp)$~', (string) $src) === 1
                && trim((string) $el->getAttribute('class')) === 'custom-emoji';
            foreach (iterator_to_array($el->attributes ?? []) as $attr) {
                $el->removeAttribute($attr->nodeName);
            }
            if ($src === null) {
                $el->parentNode?->removeChild($el);
                return;
            }
            $el->setAttribute('src', $src);
            $el->setAttribute('alt', mb_substr($alt, 0, 255));
            $el->setAttribute('loading', 'lazy');
            if ($customEmoji) {
                $el->setAttribute('class', 'custom-emoji');
            }
            return;
        }

        if ($tag === 'input') {
            $type = strtolower((string) $el->getAttribute('type'));
            $checked = $el->hasAttribute('checked');
            $label = trim((string) ($el->parentNode?->textContent ?? ''));
            $label = preg_replace('/\s+/u', ' ', $label) ?? '';
            $label = $label !== '' ? mb_substr($label, 0, 255) : 'Task item';
            foreach (iterator_to_array($el->attributes ?? []) as $attr) {
                $el->removeAttribute($attr->nodeName);
            }
            if ($type !== 'checkbox') {
                $el->parentNode?->removeChild($el);
                return;
            }
            $el->setAttribute('type', 'checkbox');
            $el->setAttribute('disabled', 'disabled');
            if ($checked) {
                $el->setAttribute('checked', 'checked');
            }
            $el->setAttribute('aria-label', $label);
            return;
        }

        $this->scrubAttributes($el, $tag);
        $this->cleanChildren($el);
    }

    private function scrubAttributes(DOMElement $el, string $tag): void
    {
        // Capture href before stripping (links are the only tag that keeps one).
        $href = $tag === 'a' ? $this->safeHref((string) $el->getAttribute('href')) : null;
        // Spoilers (P3-02): a <span> keeps only class="spoiler"; all else is dropped.
        $spoiler = $tag === 'span' && $this->hasSpoilerClass((string) $el->getAttribute('class'));
        $listStart = $tag === 'ol' ? $this->safeListStart((string) $el->getAttribute('start')) : null;
        $languageClass = $tag === 'code' ? $this->safeLanguageClass((string) $el->getAttribute('class')) : null;
        $alignment = in_array($tag, ['th', 'td'], true)
            ? $this->safeTableAlignment((string) $el->getAttribute('align'))
            : null;

        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $el->removeAttribute($attr->nodeName);
        }

        if ($tag === 'a' && $href !== null) {
            $el->setAttribute('href', $href);
            $el->setAttribute('rel', 'nofollow ugc noopener noreferrer');
        }
        if ($spoiler) {
            $el->setAttribute('class', 'spoiler');
            $el->setAttribute('tabindex', '0');
        }
        if ($listStart !== null) {
            $el->setAttribute('start', $listStart);
        }
        if ($languageClass !== null) {
            $el->setAttribute('class', $languageClass);
        }
        if ($alignment !== null) {
            $el->setAttribute('align', $alignment);
        }
    }

    private function isFormattedTableWrapper(DOMElement $el): bool
    {
        if ($el->attributes === null || $el->attributes->length !== 4
            || (string) $el->getAttribute('class') !== 'formatted-table'
            || (string) $el->getAttribute('tabindex') !== '0'
            || (string) $el->getAttribute('role') !== 'region'
            || (string) $el->getAttribute('aria-label') !== 'Scrollable table') {
            return false;
        }

        $elements = [];
        foreach ($el->childNodes as $child) {
            if ($child instanceof DOMText && trim($child->nodeValue ?? '') === '') {
                continue;
            }
            if (!$child instanceof DOMElement) {
                return false;
            }
            $elements[] = strtolower($child->localName ?? $child->nodeName);
        }
        return $elements === ['table'];
    }

    private function safeListStart(string $start): ?string
    {
        $start = trim($start);
        return preg_match('/^(?:0|[1-9]\d{0,8})$/', $start) === 1 ? $start : null;
    }

    private function safeLanguageClass(string $class): ?string
    {
        $class = trim($class);
        return preg_match('/^language-[A-Za-z0-9_.+#-]{1,64}$/', $class) === 1 ? $class : null;
    }

    private function safeTableAlignment(string $alignment): ?string
    {
        $alignment = strtolower(trim($alignment));
        return in_array($alignment, ['left', 'center', 'right'], true) ? $alignment : null;
    }

    private function hasSpoilerClass(string $class): bool
    {
        return in_array('spoiler', preg_split('/\s+/', trim($class)) ?: [], true);
    }

    /** Only same-origin media, operator emoji, or GIPHY media assets are valid image srcs. */
    private function safeImageSrc(string $src): ?string
    {
        $src = trim($src);
        if (preg_match('~^/media/\d+(?:\?[^\s"\'<>]*)?$~', $src) === 1) {
            return $src;
        }
        if (preg_match('~^/emoji/[A-Za-z0-9_.-]+\.(?:png|webp)$~', $src) === 1) {
            return $src;
        }
        if (!$this->allowGiphyImages || preg_match('/[\x00-\x1F\x7F"\'<>]/', $src) === 1) {
            return null;
        }
        $scheme = parse_url($src, PHP_URL_SCHEME);
        $host = parse_url($src, PHP_URL_HOST);
        $path = parse_url($src, PHP_URL_PATH);
        if (is_string($scheme) && strtolower($scheme) === 'https'
            && is_string($host) && str_ends_with(strtolower($host), '.giphy.com')
            && is_string($path) && $path !== '') {
            return $src;
        }
        return null;
    }

    private function safeHref(string $href): ?string
    {
        $href = trim($href);
        if ($href === '' || preg_match('/[\x00-\x1F\x7F]/', $href) === 1) {
            return null;
        }
        // Reject protocol-relative URLs in all slash/backslash forms (//host,
        // /\host, \/host, \\host) — browsers normalise backslashes, so these
        // would navigate off-site.
        if (preg_match('~^[\\\\/]{2}~', $href) === 1) {
            return null;
        }
        if (str_starts_with($href, '#')) {
            return $href;
        }
        // Root-relative same-origin path.
        if (str_starts_with($href, '/')) {
            return $href;
        }
        $scheme = parse_url($href, PHP_URL_SCHEME);
        if ($scheme === null || $scheme === false) {
            return $href; // relative path like "foo/bar"
        }
        return in_array(strtolower($scheme), ['http', 'https', 'mailto'], true) ? $href : null;
    }

    private function rename(DOMElement $el, string $newName): DOMElement
    {
        $new = $el->ownerDocument->createElement($newName);
        while ($el->firstChild !== null) {
            $new->appendChild($el->firstChild);
        }
        $el->parentNode?->replaceChild($new, $el);
        return $new;
    }

    private function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;
        if ($parent === null) {
            return;
        }
        while ($el->firstChild !== null) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }
}
