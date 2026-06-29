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
 *    a (href only), h2, h3, safe table markup, and disabled task-list inputs.
 *  - Headings are clamped into [h2, h3] (h1→h2, h4–h6→h3) so a post can't
 *    impersonate page chrome (COMPOSER.md: only ## and ###).
 *  - Dangerous elements (script, style, iframe, img, table, form, svg, …) are
 *    removed with their subtree.
 *  - Unknown elements are unwrapped (their text is kept, the tag is dropped).
 *  - <a href> is restricted to http/https/mailto + relative/fragment URLs and
 *    gets rel="nofollow ugc noopener noreferrer"; all other attributes (incl.
 *    every on* handler) are stripped from every element.
 */
final class HtmlSanitizer
{
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

        // Images (P3-04): only same-origin /media/{id} sources survive; an <img>
        // without a safe src is dropped entirely so it can't carry an onerror
        // payload or hotlink an off-site tracker.
        if ($tag === 'img') {
            $src = $this->safeImageSrc((string) $el->getAttribute('src'));
            $alt = (string) $el->getAttribute('alt');
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
            return;
        }

        if ($tag === 'input') {
            $type = strtolower((string) $el->getAttribute('type'));
            $checked = $el->hasAttribute('checked');
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
    }

    private function hasSpoilerClass(string $class): bool
    {
        return in_array('spoiler', preg_split('/\s+/', trim($class)) ?: [], true);
    }

    /** Only same-origin media or operator-managed static emoji assets are valid image srcs. */
    private function safeImageSrc(string $src): ?string
    {
        $src = trim($src);
        if (preg_match('~^/media/\d+(?:\?[^\s"\'<>]*)?$~', $src) === 1) {
            return $src;
        }
        return preg_match('~^/emoji/[A-Za-z0-9_.-]+\.(?:png|webp)$~', $src) === 1 ? $src : null;
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
