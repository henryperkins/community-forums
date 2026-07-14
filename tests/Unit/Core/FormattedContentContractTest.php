<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class FormattedContentContractTest extends TestCase
{
    public function test_every_markdown_surface_opts_into_the_shared_content_contract(): void
    {
        $root = dirname(__DIR__, 3);

        self::assertStringContainsString(
            'class="post-body formatted-content"',
            (string) file_get_contents($root . '/templates/partials/post.php'),
        );
        self::assertStringContainsString(
            'class="dm-body formatted-content"',
            (string) file_get_contents($root . '/templates/dm/show.php'),
        );
        self::assertStringContainsString(
            'class="post-body formatted-content"',
            (string) file_get_contents($root . '/templates/partials/living_brief.php'),
        );
        self::assertStringContainsString(
            "pane.className = 'composer-preview formatted-content';",
            (string) file_get_contents($root . '/public/assets/composer.js'),
        );
    }

    public function test_shared_css_contains_the_responsive_media_table_and_prose_contracts(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 3) . '/public/assets/app.css');

        foreach ([
            '.formatted-content img:not(.custom-emoji)',
            '.formatted-content .custom-emoji',
            '.formatted-content .formatted-table',
            '.formatted-content .formatted-table:focus-visible',
            '.formatted-content .formatted-table > table',
            '.formatted-content li:has(> input[type="checkbox"])',
            '.formatted-content h2',
            '.formatted-content h3',
            '.formatted-content hr',
        ] as $selector) {
            self::assertStringContainsString($selector, $css, 'missing formatted-content rule: ' . $selector);
        }

        self::assertMatchesRegularExpression('/img:not\(\.custom-emoji\)[^{]*\{[^}]*max-inline-size:\s*100%/s', $css);
        self::assertMatchesRegularExpression('/\.custom-emoji[^{]*\{[^}]*inline-size:\s*1\.2em/s', $css);
        self::assertMatchesRegularExpression('/\.formatted-table[^{]*\{[^}]*overflow-x:\s*auto/s', $css);
        self::assertMatchesRegularExpression('/\.formatted-table\s*>\s*table[^{]*\{[^}]*min-inline-size:\s*100%/s', $css);
    }
}
