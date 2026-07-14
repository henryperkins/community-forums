<?php

declare(strict_types=1);

namespace App\Support;

interface MarkdownRenderer
{
    /** @param array{link_mentions?:bool} $options */
    public function render(string $markdown, array $options = []): string;
}
