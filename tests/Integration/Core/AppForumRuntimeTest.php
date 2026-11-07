<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppForumRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_every_layout_loads_forum_layers_in_order_without_legacy_fonts(): void
    {
        foreach (['/', '/login', '/privacy'] as $path) {
            $response = $this->get($path);
            $this->assertStatus(200, $response);
            $body = $response->body();

            $base = strpos($body, '<link rel="stylesheet" href="/assets/dist/forum-base-style-');
            $application = strpos($body, '<link rel="stylesheet" href="/assets/dist/app-style-');
            $theme = strpos($body, '<link rel="stylesheet" href="/assets/dist/forum-theme-style-');

            self::assertNotFalse($base, $path . ' loads the Forum compatibility layer');
            self::assertNotFalse($application, $path . ' keeps application behavior');
            self::assertNotFalse($theme, $path . ' loads the active Forum theme');
            self::assertLessThan($application, $base, $path . ' establishes layers before app.css');
            self::assertLessThan($theme, $application, $path . ' loads Forum theme after app.css');
            self::assertStringNotContainsString('imladris-style-', $body);
            self::assertStringNotContainsString('imladris.css', $body);
            self::assertStringNotContainsString('as=font', (string) $response->getHeader('Link'));
            self::assertStringNotContainsString('_ds_bundle.js', $body);
        }
    }
}
