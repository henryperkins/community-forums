<?php

declare(strict_types=1);

namespace App\Support\Markdown;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Renders a {@see Spoiler} node to <span class="spoiler" tabindex="0">. The
 * sanitizer keeps exactly this shape; the CSS hides the text until clicked or
 * focused (keyboard accessible).
 */
final class SpoilerRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        return new HtmlElement(
            'span',
            ['class' => 'spoiler', 'tabindex' => '0'],
            $childRenderer->renderNodes($node->children()),
        );
    }
}
