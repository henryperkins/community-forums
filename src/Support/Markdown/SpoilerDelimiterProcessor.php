<?php

declare(strict_types=1);

namespace App\Support\Markdown;

use League\CommonMark\Delimiter\DelimiterInterface;
use League\CommonMark\Delimiter\Processor\DelimiterProcessorInterface;
use League\CommonMark\Node\Inline\AbstractStringContainer;

/**
 * `||spoiler||` delimiter processor (P3-02). Pairs runs of exactly two `|`
 * characters and wraps the enclosed inlines in a {@see Spoiler} node. Modelled on
 * CommonMark's StrikethroughDelimiterProcessor.
 */
final class SpoilerDelimiterProcessor implements DelimiterProcessorInterface
{
    public function getOpeningCharacter(): string
    {
        return '|';
    }

    public function getClosingCharacter(): string
    {
        return '|';
    }

    public function getMinLength(): int
    {
        return 2;
    }

    public function getDelimiterUse(DelimiterInterface $opener, DelimiterInterface $closer): int
    {
        // Require at least a pair on each side; consume exactly two.
        if ($opener->getLength() >= 2 && $closer->getLength() >= 2) {
            return 2;
        }
        return 0;
    }

    public function process(AbstractStringContainer $opener, AbstractStringContainer $closer, int $delimiterUse): void
    {
        $spoiler = new Spoiler();

        $tmp = $opener->next();
        while ($tmp !== null && $tmp !== $closer) {
            $next = $tmp->next();
            $spoiler->appendChild($tmp);
            $tmp = $next;
        }

        $opener->insertAfter($spoiler);
    }
}
