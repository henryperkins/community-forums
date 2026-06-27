<?php

declare(strict_types=1);

namespace App\Support\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * Registers the `||spoiler||` syntax (P3-02).
 */
final class SpoilerExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addDelimiterProcessor(new SpoilerDelimiterProcessor());
        $environment->addRenderer(Spoiler::class, new SpoilerRenderer());
    }
}
