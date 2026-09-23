<?php

declare(strict_types=1);

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Parser\MarkdownParser;

final class PendingUploadGuard
{
    public const MESSAGE = 'An image has not finished uploading. Reattach it or remove it before sending.';

    public static function containsPendingImage(string $body): bool
    {
        if (!str_contains($body, 'rbup-')) {
            return false;
        }
        $environment = new Environment(['max_nesting_level' => 100]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $document = (new MarkdownParser($environment))->parse($body);
        foreach ($document->iterator() as $node) {
            if ($node instanceof Image && preg_match('/^rbup-[a-z0-9]+-[a-z0-9]+$/D', $node->getUrl()) === 1) {
                return true;
            }
        }
        return false;
    }
}
