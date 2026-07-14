<?php

declare(strict_types=1);

namespace App\Support\Markdown;

use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/** Wraps Markdown tables in the keyboard-focusable overflow region used by every content surface. */
final class ScrollableTableRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        Table::assertInstanceOf($node);

        $separator = $childRenderer->getInnerSeparator();
        $children = $childRenderer->renderNodes($node->children());
        $table = new HtmlElement(
            'table',
            $node->data->get('attributes'),
            $separator . trim($children) . $separator,
        );

        return new HtmlElement('div', [
            'class' => 'formatted-table',
            'tabindex' => '0',
            'role' => 'region',
            'aria-label' => 'Scrollable table',
        ], $table);
    }
}
